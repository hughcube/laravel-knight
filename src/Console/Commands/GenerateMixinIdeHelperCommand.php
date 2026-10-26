<?php

namespace HughCube\Laravel\Knight\Console\Commands;

use Closure;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

class GenerateMixinIdeHelperCommand extends \HughCube\Laravel\Knight\Console\Command
{
    protected $signature = 'ide:generate-mixin-helper
                            {--path=app/Mixin : Mixin 类所在目录}
                            {--pattern= : 文件匹配模式，默认 *Mixin.php}
                            {--output=_ide_helper.knight.php : 输出文件路径}
                            {--dry-run : 仅显示将要生成的内容，不实际写入文件}';

    protected string $defaultPattern = '*Mixin.php';

    protected $description = '为 Mixin 类生成 IDE 辅助文件';

    protected Filesystem $files;

    /** @var array<string, array{mixinClass: string, methods: array}> 按目标类分组的方法 */
    protected array $targetClassMethods = [];

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $path = $this->resolvePath($this->option('path'));
        $pattern = $this->option('pattern') ?: $this->defaultPattern;
        $outputFile = $this->resolvePath($this->option('output'));
        $dryRun = $this->option('dry-run');

        $this->line("扫描目录: {$path}");
        $this->line("匹配模式: {$pattern}");
        $this->line("输出文件: {$outputFile}");

        if (! $this->files->isDirectory($path)) {
            $this->error("目录不存在: {$path}");

            return self::FAILURE;
        }

        $files = $this->findMatchingFiles($path, $pattern);
        $this->line('找到 '.count($files).' 个匹配文件');

        if (empty($files)) {
            $this->warn("未找到匹配 {$pattern} 的文件");

            return self::FAILURE;
        }

        $processed = 0;

        foreach ($files as $file) {
            $this->line("处理文件: {$file}");

            $className = $this->getClassNameFromFile($file);
            if ($className === null) {
                $this->warn('  无法解析类名');
                continue;
            }

            $this->line("  类名: {$className}");

            if (! class_exists($className)) {
                $this->warn('  类不存在，尝试加载...');
            }

            if (! class_exists($className)) {
                $this->warn("  无法加载类: {$className}");
                continue;
            }

            if ($this->collectMixinMethods($className)) {
                $processed++;
            }
        }

        if (empty($this->targetClassMethods)) {
            $this->warn('未找到任何可导出的 Mixin 方法');

            return self::FAILURE;
        }

        $this->writeIdeHelperFile($outputFile, $dryRun);

        $this->info("已处理 {$processed} 个 Mixin 类，生成到 {$outputFile}");

        return self::SUCCESS;
    }

    /**
     * 递归查找匹配模式的文件.
     *
     * @param  string  $path
     * @param  string  $pattern
     * @return array<string>
     */
    protected function findMatchingFiles(string $path, string $pattern): array
    {
        $files = [];
        $allFiles = $this->files->allFiles($path);

        foreach ($allFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if ($this->matchesPattern($file->getFilename(), $pattern)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * 检查文件名是否匹配模式.
     *
     * @param  string  $filename
     * @param  string  $pattern
     * @return bool
     */
    protected function matchesPattern(string $filename, string $pattern): bool
    {
        return fnmatch($pattern, $filename, FNM_CASEFOLD);
    }

    /**
     * 解析路径，支持相对路径和绝对路径.
     *
     * @param  string  $path
     * @return string
     */
    protected function resolvePath(string $path): string
    {
        // 检查是否是绝对路径 (Unix: / 开头, Windows: C:\ 或 C:/ 或 \\ 开头)
        if (preg_match('#^(/|[a-zA-Z]:[/\\\\]|\\\\\\\\)#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    protected function getClassNameFromFile(string $filePath): ?string
    {
        $content = $this->files->get($filePath);

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace.'\\'.$class;
        }

        return null;
    }

    protected function collectMixinMethods(string $mixinClass): bool
    {
        $reflection = new ReflectionClass($mixinClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $this->line('  获取 @mixin 目标类...');

        // 获取 Mixin 类的 @mixin-target 注解，确定目标类（只读取当前类）
        $targetClasses = $this->getTargetClasses($reflection);
        if (empty($targetClasses)) {
            $this->warn("  未找到 {$mixinClass} 的目标类 (@mixin-target 注解)");

            return false;
        }

        $this->line('  目标类: '.implode(', ', $targetClasses));

        $methodInfos = [];
        foreach ($methods as $method) {
            if ($method->class !== $mixinClass) {
                continue;
            }

            $info = $this->extractMethodInfo($method, $mixinClass);
            if ($info !== null) {
                $methodInfos[] = $info;
                $this->line("  方法: {$info['name']}() -> {$info['returnType']}");
            }
        }

        if (empty($methodInfos)) {
            $this->warn('  未找到可导出的公共方法');

            return false;
        }

        // 将方法添加到对应的目标类
        foreach ($targetClasses as $targetClass) {
            if (! isset($this->targetClassMethods[$targetClass])) {
                $this->targetClassMethods[$targetClass] = [
                    'mixinClass' => $mixinClass,
                    'methods' => [],
                ];
            }
            $this->targetClassMethods[$targetClass]['methods'] = array_merge(
                $this->targetClassMethods[$targetClass]['methods'],
                $methodInfos
            );
        }

        return true;
    }

    protected function getTargetClasses(ReflectionClass $reflection): array
    {
        // 直接从源文件读取，因为 OPcache 可能禁用了注释保存
        $filename = $reflection->getFileName();
        if ($filename === false) {
            $this->line('    无法获取文件路径');

            return [];
        }

        $content = $this->files->get($filename);
        $className = $reflection->getShortName();

        // 找到类定义的位置
        $classPattern = '/\bclass\s+'.preg_quote($className, '/').'\b/';
        if (! preg_match($classPattern, $content, $classMatch, PREG_OFFSET_CAPTURE)) {
            $this->line('    未找到类定义');

            return [];
        }

        $classPos = $classMatch[0][1];

        // 从类定义位置向前查找最近的文档注释
        $beforeClass = substr($content, 0, $classPos);

        // 查找最后一个 /** ... */ 块
        if (! preg_match_all('/\/\*\*[\s\S]*?\*\//', $beforeClass, $docMatches, PREG_OFFSET_CAPTURE)) {
            $this->line('    未找到文档注释');

            return [];
        }

        // 取最后一个文档注释（最靠近类定义的）
        $lastDoc = end($docMatches[0]);
        $docBlock = $lastDoc[0];
        $docEndPos = $lastDoc[1] + strlen($docBlock);

        // 确保文档注释和类定义之间只有空白
        $between = substr($content, $docEndPos, $classPos - $docEndPos);
        if (trim($between) !== '') {
            $this->line('    文档注释和类定义之间有其他代码');

            return [];
        }

        $classes = [];
        if (preg_match_all('/@mixin-target\s+([^\s\*]+)/', $docBlock, $matches)) {
            $uses = $this->getUseStatements($reflection);

            foreach ($matches[1] as $class) {
                $this->line("    发现 @mixin-target: {$class}");

                // 处理相对命名空间
                if (strpos($class, '\\') !== 0) {
                    $namespace = $reflection->getNamespaceName();

                    // 首先检查 use 语句
                    if (isset($uses[$class])) {
                        $resolved = $uses[$class];
                    } else {
                        $resolved = $namespace.'\\'.$class;
                    }

                    $this->line("    解析为: {$resolved}");

                    if (class_exists($resolved) || interface_exists($resolved)) {
                        $classes[] = $resolved;
                    } elseif (class_exists($class) || interface_exists($class)) {
                        $classes[] = $class;
                    } else {
                        $this->warn("    类不存在: {$resolved}");
                    }
                } else {
                    $classes[] = ltrim($class, '\\');
                }
            }
        } else {
            $this->line('    未找到 @mixin-target 注解');
        }

        return $classes;
    }

    protected function getUseStatements(ReflectionClass $reflection): array
    {
        $filename = $reflection->getFileName();
        if ($filename === false) {
            return [];
        }

        $content = $this->files->get($filename);
        $uses = [];

        if (preg_match_all('/use\s+([^;]+)\s*;/', $content, $matches)) {
            foreach ($matches[1] as $use) {
                $use = trim($use);
                if (strpos($use, ' as ') !== false) {
                    [$fullClass, $alias] = explode(' as ', $use);
                    $uses[trim($alias)] = trim($fullClass);
                } else {
                    $parts = explode('\\', $use);
                    $shortName = end($parts);
                    $uses[$shortName] = $use;
                }
            }
        }

        return $uses;
    }

    protected function extractMethodInfo(ReflectionMethod $method, string $mixinClass): ?array
    {
        // 实例化 mixin 类来获取闭包
        try {
            $instance = new $mixinClass();
            $closure = $method->invoke($instance);

            if (! $closure instanceof Closure) {
                return null;
            }

            $closureReflection = new ReflectionFunction($closure);

            // 获取参数
            $parameters = [];
            foreach ($closureReflection->getParameters() as $param) {
                $paramStr = '';
                $type = $param->getType();

                if ($type !== null) {
                    $paramStr .= $this->formatType($type).' ';
                }

                $paramStr .= '$'.$param->getName();

                if ($param->isDefaultValueAvailable()) {
                    $default = $param->getDefaultValue();
                    $paramStr .= ' = '.$this->formatDefaultValue($default);
                }

                $parameters[] = $paramStr;
            }

            // 获取返回类型
            $returnType = $closureReflection->getReturnType();
            $returnTypeStr = $returnType !== null ? $this->formatType($returnType) : 'mixed';

            // 获取方法文档注释
            $docComment = $method->getDocComment() ?: '';

            // 从 docblock 中提取 @return 注解
            $docReturnType = null;
            if (preg_match('/@return\s+([^\n\*]+)/', $docComment, $returnMatch)) {
                $rawReturn = trim($returnMatch[1]);

                // 处理 @return Closure(...): Type 格式，提取实际返回类型
                if (preg_match('/^Closure\s*\([^)]*\)\s*:\s*(.+)$/', $rawReturn, $closureMatch)) {
                    $docReturnType = trim($closureMatch[1]);
                } elseif (stripos($rawReturn, 'Closure') !== 0) {
                    // 不是 Closure 开头的，直接使用
                    $docReturnType = $rawReturn;
                }
            }

            return [
                'name' => $method->getName(),
                'parameters' => $parameters,
                'returnType' => $returnTypeStr,
                'docReturnType' => $docReturnType,
                'docComment' => $docComment,
                'mixinClass' => $mixinClass,
            ];
        } catch (\Throwable $e) {
            $this->warn("无法解析方法 {$method->getName()}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @param  ReflectionType|null  $type
     * @param  bool  $isUnionPart
     * @return string
     */
    protected function formatType($type, bool $isUnionPart = false): string
    {
        if ($type === null) {
            return 'mixed';
        }

        // PHP 8.0+ ReflectionUnionType
        if (class_exists('ReflectionUnionType') && $type instanceof \ReflectionUnionType) {
            $types = array_map(function ($t) {
                return $this->formatType($t, true);
            }, $type->getTypes());

            return implode('|', $types);
        }

        // PHP 8.1+ ReflectionIntersectionType
        if (class_exists('ReflectionIntersectionType') && $type instanceof \ReflectionIntersectionType) {
            $types = array_map(function ($t) {
                return $this->formatType($t, true);
            }, $type->getTypes());

            return implode('&', $types);
        }

        // ReflectionNamedType (PHP 7.1+)
        if (! $type instanceof ReflectionNamedType) {
            return 'mixed';
        }

        $name = $type->getName();

        // 内置类型不需要前缀
        if ($type->isBuiltin()) {
            $typeStr = $name;
        } else {
            $typeStr = '\\'.$name;
        }

        // 只有非 union 部分才添加 ? 前缀
        if (! $isUnionPart && $type->allowsNull() && $name !== 'null' && $name !== 'mixed') {
            $typeStr = '?'.$typeStr;
        }

        return $typeStr;
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    protected function formatDefaultValue($value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            return "'".addslashes($value)."'";
        }
        if (is_array($value)) {
            return '[]';
        }

        return (string) $value;
    }

    protected function writeIdeHelperFile(string $outputFile, bool $dryRun = false): void
    {
        $content = $this->buildIdeHelperContent();

        if ($dryRun) {
            $this->line("[dry-run] 将生成: {$outputFile}");
            $this->line($content);

            return;
        }

        $this->files->put($outputFile, $content);
        $this->info("生成: {$outputFile}");
    }

    protected function buildIdeHelperContent(): string
    {
        $namespaceBlocks = [];

        foreach ($this->targetClassMethods as $targetClass => $data) {
            $parts = explode('\\', $targetClass);
            $className = array_pop($parts);
            $namespace = implode('\\', $parts);

            $methods = $this->buildMethodsForClass($data['methods'], $data['mixinClass']);

            $namespaceBlocks[$namespace][] = [
                'className' => $className,
                'mixinClass' => $data['mixinClass'],
                'methods' => $methods,
            ];
        }

        $output = <<<'PHP'
<?php
/* @noinspection ALL */
// @formatter:off
// phpcs:ignoreFile

/**
 * IDE helper for App Mixin methods.
 *
 * 此文件由 ide:generate-mixin-helper 命令自动生成，请勿手动修改。
 */

PHP;

        foreach ($namespaceBlocks as $namespace => $classes) {
            $output .= "\nnamespace {$namespace} {\n";

            foreach ($classes as $classData) {
                $output .= "\n    /**\n";
                $output .= "     * @see \\{$classData['mixinClass']}\n";
                $output .= "     */\n";
                $output .= "    class {$classData['className']}\n";
                $output .= "    {\n";
                $output .= $classData['methods'];
                $output .= "    }\n";
            }

            $output .= "}\n";
        }

        return $output;
    }

    protected function buildMethodsForClass(array $methodInfos, string $mixinClass): string
    {
        $methods = [];

        foreach ($methodInfos as $info) {
            $paramStr = implode(', ', $info['parameters']);
            $returnType = $info['returnType'];

            // 当 Closure 没有返回类型声明（mixed）且 docblock 有 @return 注解时，使用 docblock 的返回类型
            $effectiveReturnType = $returnType;
            if ($returnType === 'mixed' && ! empty($info['docReturnType'])) {
                $effectiveReturnType = $info['docReturnType'];
            }

            $docLines = [];
            if (! empty($info['docComment'])) {
                // 提取原始注释中的描述部分
                if (preg_match('/\/\*\*\s*\n\s*\*\s*([^@\n]+)/', $info['docComment'], $matches)) {
                    $docLines[] = trim($matches[1]);
                }
            }
            $docLines[] = "@see \\{$info['mixinClass']}::{$info['name']}()";

            // 添加 @return 注解（优先使用 docblock 中的 @return）
            if (! empty($info['docReturnType'])) {
                $docLines[] = "@return {$info['docReturnType']}";
            }

            $docComment = "        /**\n";
            foreach ($docLines as $line) {
                $docComment .= "         * {$line}\n";
            }
            $docComment .= '         */';

            $methods[] = <<<METHOD
{$docComment}
        public function {$info['name']}({$paramStr}): {$effectiveReturnType}
        {
        }
METHOD;
        }

        return implode("\n\n", $methods)."\n";
    }
}
