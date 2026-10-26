<?php

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\ClearModelCache;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;

class ClearModelCacheTest extends TestCase
{
    private string $modelDir;
    private string $modelFile;
    private string $class;
    private bool $createdModelDir = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelDir = app_path('Models');
        if (! is_dir($this->modelDir)) {
            $this->createdModelDir = true;
            mkdir($this->modelDir, 0777, true);
        }

        $this->modelClass = 'CacheDummy'.substr(md5(uniqid('', true)), 0, 8);
        $this->modelFile = $this->modelDir.DIRECTORY_SEPARATOR.$this->modelClass.'.php';

        $code = sprintf(<<<'PHP'
<?php

namespace App\Models;

class %1$s
{
    public static array $rows = [];
    public static array $deletedIds = [];

    public int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function deleteRowCache(): bool
    {
        static::$deletedIds[] = $this->id;

        return true;
    }

    public static function noCacheQuery(): %1$sQuery
    {
        return new %1$sQuery(static::$rows);
    }
}

class %1$sQuery
{
    private array $rows;
    private ?array $ids = null;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function whereIn($column, $ids): self
    {
        if ($ids instanceof \Illuminate\Support\Collection) {
            $ids = $ids->all();
        }

        $this->ids = array_map('intval', is_array($ids) ? $ids : (array) $ids);

        return $this;
    }

    public function clone(): self
    {
        return clone $this;
    }

    public function count(): int
    {
        return count($this->getRows());
    }

    public function eachById(callable $callback): void
    {
        foreach ($this->getRows() as $row) {
            $callback($row);
        }
    }

    private function getRows(): array
    {
        if ($this->ids === null) {
            return $this->rows;
        }

        $filtered = [];
        foreach ($this->rows as $row) {
            if (in_array($row->getKey(), $this->ids, true)) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }
}
PHP, $this->modelClass);

        file_put_contents($this->modelFile, $code);
        require_once $this->modelFile;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->modelFile)) {
            unlink($this->modelFile);
        }

        if ($this->createdModelDir && is_dir($this->modelDir)) {
            @rmdir($this->modelDir);
        }

        parent::tearDown();
    }

    public function testHandleClearsSelectedScopes()
    {
        $class = $this->getModelClass();
        $class::$rows = [new $class(1), new $class(2), new $class(3)];
        $class::$deletedIds = [];

        $command = new class($class, '1,3') extends ClearModelCache
        {
            public array $choices = [];
            public array $infoMessages = [];
            private string $model;
            private string $scopes;

            public function __construct(string $model, string $scopes)
            {
                parent::__construct();
                $this->model = $model;
                $this->scopes = $scopes;
            }

            public function choice($question, array $choices, $default = null, $attempts = null, $multiple = false)
            {
                $this->choices = $choices;

                return $this->model;
            }

            public function ask($question, $default = null)
            {
                return $this->scopes;
            }

            public function info($string, $verbosity = null)
            {
                $this->infoMessages[] = $string;
            }
        };

        $command->handle(new Schedule($this->app));

        $this->assertContains($class, $command->choices);
        $this->assertSame([1, 3], $class::$deletedIds);
    }

    public function testHandleClearsAllScopesWhenWildcardProvided()
    {
        $class = $this->getModelClass();
        $class::$rows = [new $class(10), new $class(20)];
        $class::$deletedIds = [];

        $command = new class($class, '*') extends ClearModelCache
        {
            private string $model;
            private string $scopes;

            public function __construct(string $model, string $scopes)
            {
                parent::__construct();
                $this->model = $model;
                $this->scopes = $scopes;
            }

            public function choice($question, array $choices, $default = null, $attempts = null, $multiple = false)
            {
                return $this->model;
            }

            public function ask($question, $default = null)
            {
                return $this->scopes;
            }

            public function info($string, $verbosity = null)
            {
            }
        };

        $command->handle(new Schedule($this->app));

        $this->assertSame([10, 20], $class::$deletedIds);
    }

    private function getModelClass(): string
    {
        return 'App\\Models\\'.$this->modelClass;
    }
}
