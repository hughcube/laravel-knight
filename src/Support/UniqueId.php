<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/2/7
 * Time: 10:00.
 */

namespace HughCube\Laravel\Knight\Support;

use HughCube\Base\Base;

/**
 * 唯一短字符串 ID 生成器.
 *
 * 通过组合 毫秒时间戳 + 进程PID + 主机指纹哈希 + 进程种子 + 递增计数器 + 随机数 等要素,
 * 使用 Base62/Base36 编码生成尽可能短的唯一 ID.
 *
 * 提供四个生成方法, 不同方法有不同的唯一性保证范围和 ID 长度:
 * - short():       单进程内唯一, 13 字符 (Base36, 固定长度)
 * - process():     同机器多进程唯一, 27 字符 (Base36, 固定长度)
 * - distributed(): 分布式跨机器唯一, 48 字符 (Base36, 固定长度, 推荐)
 * - secure():      分布式唯一且防枚举, 64 字符 (Base36, 固定长度)
 *
 * 唯一性保护机制:
 * - 计数器溢出保护: 同毫秒内计数器达到上限时自旋等待到下一毫秒, 避免回绕碰撞
 * - 时钟回拨保护: 检测到系统时钟倒退时自旋等待, 避免与历史 ID 碰撞
 * - 进程种子: 进程启动时生成一次性随机数(12位, ~40 bit), 解决 OS 回收复用 PID 导致的碰撞风险
 * - fork 安全: 检测 PID 变化自动重置状态, 避免 fork 后父子进程产生相同 ID 序列
 * - 主机标识混入 hostname/php_uname/MAC/machine-id/cgroup/容器环境变量, Hash 取 63-bit(19位十进制), 同机器稳定, 碰撞概率极低
 * - 可选环境变量 KNIGHT_UNIQUE_ID_NAMESPACE/UNIQUE_ID_NAMESPACE 可人为分域, 降低多集群混布时的碰撞风险
 * - 收集所有网卡 MAC 地址(非仅第一块), 最大化机器区分度
 * - 混入容器编排环境变量(POD_NAME/POD_IP 等), 解决容器间 hostname 和 MAC 都相同的碰撞风险
 * - PID 使用 10 位固定宽度, 覆盖 32-bit 无符号整数上限(兼容 Windows/Linux)
 * - 每个字段使用 Base::toStringWithPad() 固定宽度, 杜绝字段边界错位
 * - 每毫秒计数器随机化起始值(0-49999), 降低多进程同毫秒首个 ID 碰撞的概率
 * - 优先使用 hrtime 单调时钟, 避免 NTP 跳变和 microtime 精度抖动
 *
 * 唯一性分析 (distributed 模式, 约 245 bit 信息量):
 * - 碰撞需同时满足: 相同毫秒 + 主机哈希碰撞 + 相同 PID + 相同种子 + 相同计数器 + 随机数碰撞
 * - 联合概率远低于 UUID v4 碰撞概率, 工程上可视为绝对唯一
 *
 * 注意事项:
 * - short()/process() 模式不包含主机信息, 仅适用于其声明的唯一性范围
 */
class UniqueId
{
    const BASE36 = '0123456789abcdefghijklmnopqrstuvwxyz';
    const BASE62 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * 字符集打乱, ID 不具备字典序排列特性, 外部无法通过排序推断生成顺序.
     *
     * 注意: 这仅是混淆(obfuscation), 不是加密. 字符集为公开常量, 知道字符集即可还原原始值.
     * 防枚举场景请使用 secure() 模式, 其 ~130 bit 随机熵才是真正的安全保障.
     */
    const UNORDERED_BASE36 = '5m3k1izgxevctar8p6n4l2j0hyfwdubs9q7o';
    const UNORDERED_BASE62 = 'bMnYzaLmXy9KlWx8JkVw7IjUv6HiTu5GhSt4FgRs3EfQr2DePq1CdOp0BcNoZA';

    /**
     * 自定义纪元: 2020-01-01 00:00:00 UTC (毫秒)
     * 使用相对时间戳减小数值位数, 从而缩短编码后的字符串长度.
     */
    const EPOCH = 1577836800000;

    /**
     * 计数器最大值 (5位十进制: 0-99999)
     * 单进程每毫秒最多生成约 50000 个 ID (计数器随机起始 0-49999, 保留充足递增空间).
     */
    const MAX_COUNTER = 99999;

    /**
     * 静态计数器, 同毫秒同进程内递增.
     *
     * @var int
     */
    protected static int $counter = 0;

    /**
     * 上次生成 ID 的毫秒时间戳, 用于计数器递增和时钟回拨检测.
     *
     * @var int
     */
    protected static int $lastTimestamp = 0;

    /**
     * 进程级随机种子, 进程生命周期内不变.
     *
     * 解决 PID 复用问题: OS 回收已退出进程的 PID 后分配给新进程,
     * 若两个进程在同一毫秒内拥有相同 PID, 种子可将它们区分开.
     * 同时增加了外部不可观测的熵, 即使攻击者知道 PID 和主机名也无法推断种子.
     *
     * @var int|null
     */
    protected static ?int $processSeed = null;

    /**
     * 当前进程 PID 快照, 用于 fork 检测和短模式状态隔离.
     *
     * fork() 后子进程继承父进程的静态状态, 通过对比当前 PID 与快照 PID,
     * 检测 fork 并重置所有状态, 避免父子进程产生相同的 ID 序列.
     *
     * @var int|null
     */
    protected static ?int $seedPid = null;

    /**
     * getmypid() 不可用时的进程标识回退值
     *
     * @var int|null
     */
    protected static ?int $fallbackPid = null;

    /**
     * 缓存的主机标识 (Hash 取 63-bit, 贴合 PHP_INT_MAX, 19 位十进制).
     *
     * @var int|null
     */
    protected static ?int $machineId = null;

    /**
     * 短模式: time(14) + counter(5) = 19 位十进制.
     *
     * 组成: 毫秒时间戳(相对纪元, 14位) + 递增计数器(5位, 随机起始)
     * 长度: 13 字符 (Base36, 固定长度)
     * 唯一性: 仅在单个进程内保证唯一
     * 适用: 脚本、单线程任务、临时文件名等无需跨进程唯一的场景
     *
     * @param  string  $encoding  编码字符集, 默认 BASE36
     * @param  bool  $fixedLength  是否返回固定长度字符串, 默认 true
     * @return string
     *
     * @throws
     */
    public static function short(string $encoding = self::BASE36, bool $fixedLength = true): string
    {
        [$time, $counter] = static::tick();

        $decimal = Base::toStringWithPad($time, 14)
            .Base::toStringWithPad($counter, 5);

        $encoded = Base::conv($decimal, '0123456789', $encoding);

        return $fixedLength ? static::padToFixedLength($encoded, 19, $encoding) : $encoded;
    }

    /**
     * 进程模式: time(14) + pid(10) + seed(12) + counter(5) = 41 位十进制.
     *
     * 组成: 毫秒时间戳(相对纪元, 14位) + 进程PID(10位) + 进程种子(12位, ~40 bit) + 递增计数器(5位, 随机起始)
     * 长度: 27 字符 (Base36, 固定长度)
     * 唯一性: 同一台机器上多进程间保证唯一, 含种子防 PID 复用碰撞, fork 安全
     * 适用: 队列 worker、多进程 CLI 任务等同机器多进程场景
     *
     * @param  string  $encoding  编码字符集, 默认 BASE36
     * @param  bool  $fixedLength  是否返回固定长度字符串, 默认 true
     * @return string
     *
     * @throws
     */
    public static function process(string $encoding = self::BASE36, bool $fixedLength = true): string
    {
        [$time, $counter] = static::tick();

        $decimal = Base::toStringWithPad($time, 14)
            .Base::toStringWithPad(static::getProcessId(), 10)
            .Base::toStringWithPad(static::getProcessSeed(), 12)
            .Base::toStringWithPad($counter, 5);

        $encoded = Base::conv($decimal, '0123456789', $encoding);

        return $fixedLength ? static::padToFixedLength($encoded, 41, $encoding) : $encoded;
    }

    /**
     * 分布式模式 (推荐): time(14) + machine(19) + pid(10) + seed(12) + counter(5) + random(14) = 74 位十进制.
     *
     * 组成: 毫秒时间戳(相对纪元, 14位) + 机器特征Hash取63-bit(19位) + 进程PID(10位)
     *       + 进程种子(12位, ~40 bit) + 递增计数器(5位, 随机起始) + 随机数(14位, ~46 bit)
     * 长度: 48 字符 (Base36, 固定长度)
     * 唯一性: 跨机器分布式环境下唯一, 含种子防 PID 复用碰撞, fork 安全, 额外 46 bit 随机熵进一步降低碰撞概率
     * 适用: 分布式系统、微服务、多节点部署等需要全局唯一的场景
     * 注意: 本模式的 ~46 bit 随机不足以防枚举, 对外暴露的 ID 请使用 secure() 模式
     *
     * @param  string  $encoding  编码字符集, 默认 BASE36
     * @param  bool  $fixedLength  是否返回固定长度字符串, 默认 true
     * @return string
     *
     * @throws
     */
    public static function distributed(string $encoding = self::BASE36, bool $fixedLength = true): string
    {
        [$time, $counter] = static::tick();

        $decimal = Base::toStringWithPad($time, 14)
            .Base::toStringWithPad(static::getMachineId(), 19)
            .Base::toStringWithPad(static::getProcessId(), 10)
            .Base::toStringWithPad(static::getProcessSeed(), 12)
            .Base::toStringWithPad($counter, 5)
            .Base::toStringWithPad(random_int(0, 99999999999999), 14);

        $encoded = Base::conv($decimal, '0123456789', $encoding);

        return $fixedLength ? static::padToFixedLength($encoded, 74, $encoding) : $encoded;
    }

    /**
     * 安全模式: time(14) + machine(19) + pid(10) + seed(12) + counter(5) + random(39) = 99 位十进制.
     *
     * 组成: 毫秒时间戳(相对纪元, 14位) + 机器特征Hash取63-bit(19位) + 进程PID(10位)
     *       + 进程种子(12位, ~40 bit) + 递增计数器(5位, 随机起始) + 随机数(39位, ~130 bit)
     * 长度: 64 字符 (Base36, 固定长度)
     * 唯一性: 分布式唯一 + 39位随机数防枚举(~130 bit 熵, 暴力枚举需 10^39 次)
     * 适用: 对外暴露的 ID(如订单号、文件URL)、防止用户猜测和遍历的场景
     *
     * @param  string  $encoding  编码字符集, 默认 BASE36
     * @param  bool  $fixedLength  是否返回固定长度字符串, 默认 true
     * @return string
     *
     * @throws
     */
    public static function secure(string $encoding = self::BASE36, bool $fixedLength = true): string
    {
        [$time, $counter] = static::tick();

        $decimal = Base::toStringWithPad($time, 14)
            .Base::toStringWithPad(static::getMachineId(), 19)
            .Base::toStringWithPad(static::getProcessId(), 10)
            .Base::toStringWithPad(static::getProcessSeed(), 12)
            .Base::toStringWithPad($counter, 5)
            .Base::toStringWithPad(random_int(0, 9999999999999), 13)
            .Base::toStringWithPad(random_int(0, 9999999999999), 13)
            .Base::toStringWithPad(random_int(0, 9999999999999), 13);

        $encoded = Base::conv($decimal, '0123456789', $encoding);

        return $fixedLength ? static::padToFixedLength($encoded, 99, $encoding) : $encoded;
    }

    /**
     * 获取主机标识 (Hash 取 63-bit, 惰性初始化, 同机器所有进程相同).
     *
     * 混入 hostname + php_uname + 所有网卡MAC + /etc/machine-id + /proc/self/cgroup + 容器环境变量 + 可选命名空间,
     * 使用 SHA-256 取 63-bit(贴合 PHP_INT_MAX).
     *
     * 不混入随机熵, 保持机器级稳定性:
     * - 同一台机器/容器上所有进程 machineId 相同, 可用于来源追溯
     * - 进程级唯一性由 processSeed (~40 bit) 保证, 无需 machineId 承担
     * - fork 后无需重新计算, 父子进程在同一台机器上, machineId 相同是正确语义
     *
     * @return int
     */
    protected static function getMachineId(): int
    {
        if (static::$machineId === null) {
            // 结构化组装机器指纹, 避免简单字符串拼接带来的边界歧义
            $parts = [
                'hostname='.(string) gethostname(),
                'uname='.php_uname(),
            ];

            // 混入所有网卡 MAC 地址并排序, 保证跨进程稳定
            $macAddresses = [];
            if (function_exists('net_get_interfaces')) {
                $interfaces = net_get_interfaces();
                if (is_array($interfaces)) {
                    foreach ($interfaces as $iface) {
                        if (! empty($iface['mac']) && $iface['mac'] !== '00:00:00:00:00:00') {
                            $macAddresses[] = strtolower((string) $iface['mac']);
                        }
                    }
                }
            }
            sort($macAddresses, SORT_STRING);
            foreach ($macAddresses as $macAddress) {
                $parts[] = 'mac='.$macAddress;
            }

            // 以下为 Linux/容器 专属路径, Windows 上跳过避免无意义的 I/O
            if (DIRECTORY_SEPARATOR === '/') {
                // /etc/machine-id (systemd 生成的持久随机 ID, 每台机器/容器唯一)
                if (is_readable('/etc/machine-id')) {
                    $machineId = trim((string) file_get_contents('/etc/machine-id'));
                    if ('' !== $machineId) {
                        $parts[] = 'machine-id='.$machineId;
                    }
                }

                // /proc/self/cgroup (包含容器 ID, 即使 hostname 和 MAC 相同也能区分)
                if (is_readable('/proc/self/cgroup')) {
                    $cgroup = (string) file_get_contents('/proc/self/cgroup', false, null, 0, 512);
                    if ('' !== $cgroup) {
                        $parts[] = 'cgroup='.$cgroup;
                    }
                }
            }

            // Optional namespace: lets deployments provide an explicit isolation domain.
            // Example: KNIGHT_UNIQUE_ID_NAMESPACE=prod-shanghai-a
            foreach ([
                'KNIGHT_UNIQUE_ID_NAMESPACE',
                'UNIQUE_ID_NAMESPACE',
            ] as $env) {
                $val = getenv($env);
                if (false !== $val && '' !== $val) {
                    $parts[] = 'namespace:'.$env.'='.$val;
                }
            }

            // 混入容器/编排环境标识, 解决容器间 hostname 和 MAC 都可能相同的碰撞风险
            foreach ([
                'POD_NAME', 'POD_IP', 'CONTAINER_ID', 'INSTANCE_ID',
                'HOSTNAME', 'K8S_NODE_NAME',
                'ECS_CONTAINER_METADATA_URI', 'CLOUD_RUN_JOB',
            ] as $env) {
                $val = getenv($env);
                if (false !== $val) {
                    $parts[] = 'env:'.$env.'='.$val;
                }
            }

            // 取 Hash 的高位, 贴合 PHP_INT_MAX
            $identity = implode("\n", $parts);
            $hash = hash('sha256', $identity);
            if (PHP_INT_SIZE >= 8) {
                // 64-bit PHP: 取 63-bit (2^63 - 1 = 9223372036854775807, 19 位十进制)
                $hi = hexdec(substr($hash, 0, 8));
                $lo = hexdec(substr($hash, 8, 8));
                static::$machineId = (($hi & 0x7FFFFFFF) << 32) | $lo;
            } else {
                // 32-bit PHP: 取 31-bit (2^31 - 1 = 2147483647, 10 位十进制)
                static::$machineId = hexdec(substr($hash, 0, 8)) & 0x7FFFFFFF;
            }
        }

        return static::$machineId;
    }

    /**
     * 获取进程级随机种子 (惰性初始化, 进程生命周期内只生成一次).
     *
     * 12 位十进制(~40 bit 熵), 生日碰撞阈值约 100 万个进程.
     *
     * @return int
     *
     * @throws
     */
    protected static function getProcessSeed(): int
    {
        if (static::$processSeed === null) {
            static::$processSeed = random_int(0, 999999999999);
            static::$seedPid = static::getProcessId();
        }

        return static::$processSeed;
    }

    /**
     * 获取当前进程 PID.
     *
     * 某些 SAPI 中 getmypid() 可能返回 false. 这时退化为进程内随机 PID,
     * 避免类型错误和固定 PID(如 0) 带来的碰撞放大.
     *
     * @return int
     *
     * @throws
     */
    protected static function getProcessId(): int
    {
        $pid = getmypid();
        if (is_int($pid) && $pid > 0) {
            return $pid;
        }

        if (static::$fallbackPid === null) {
            // 32-bit PHP 上 random_int 上限不能超过 PHP_INT_MAX
            $maxPid = PHP_INT_SIZE >= 8 ? 4294967295 : PHP_INT_MAX;
            static::$fallbackPid = random_int(1, $maxPid);
        }

        return static::$fallbackPid;
    }

    /**
     * Fork 检测: 当 PID 发生变化时重置进程级状态
     *
     * fork() 后子进程继承父进程的所有静态变量, 如果不重置将导致:
     * - 父子进程共享相同的种子, 仅靠 PID 不同来区分(short 模式无 PID 字段, 会直接碰撞)
     * - 父子进程共享相同的计数器和时间戳状态, 可能产生相同的计数器序列
     *
     * machineId 不重置: 它是机器级标识, 父子进程在同一台机器上, 相同是正确语义.
     *
     * @return void
     *
     * @throws
     */
    protected static function checkFork(): void
    {
        $currentPid = static::getProcessId();

        // 首次调用先建立 PID 快照, 让 short() 也具备 fork 状态隔离能力
        if (static::$seedPid === null) {
            static::$seedPid = $currentPid;

            return;
        }

        if (static::$seedPid !== $currentPid) {
            static::$processSeed = null;
            static::$seedPid = $currentPid;
            static::$counter = 0;
            static::$lastTimestamp = 0;
        }
    }

    /**
     * 推进时间序列, 返回当前相对时间戳和计数器.
     *
     * 包含 fork 检测、时钟回拨保护、计数器溢出保护和计数器随机化:
     * - fork 检测: PID 变化时重置所有状态, 避免父子进程产生相同 ID
     * - 时钟回拨: 系统时钟倒退时自旋等待, 避免与历史 ID 碰撞
     * - 计数器溢出: 同毫秒超出上限时自旋等待到下一毫秒, 避免回绕碰撞
     * - 计数器随机化: 每毫秒随机起始值(0-49999), 降低多进程同毫秒首个 ID 碰撞概率
     *
     * @return array [int $relativeTime, int $counter]
     *
     * @throws
     */
    protected static function tick(): array
    {
        static::checkFork();

        $timestamp = static::currentTimeMillis();

        // 时钟回拨保护: 加 usleep 避免极端回拨时忙等占满 CPU
        while ($timestamp < static::$lastTimestamp) {
            usleep(100);
            $timestamp = static::currentTimeMillis();
        }

        if ($timestamp === static::$lastTimestamp) {
            static::$counter++;

            // 计数器溢出保护: 等待下一毫秒, 加 usleep 避免忙等
            if (static::$counter > self::MAX_COUNTER) {
                while ($timestamp <= static::$lastTimestamp) {
                    usleep(100);
                    $timestamp = static::currentTimeMillis();
                }
                static::$counter = random_int(0, 49999);
            }
        } else {
            static::$counter = random_int(0, 49999);
        }

        static::$lastTimestamp = $timestamp;

        return [$timestamp - static::EPOCH, static::$counter];
    }

    /**
     * 将编码后的字符串左补齐到固定长度.
     *
     * 使用编码字符集的首字符('0')左补齐, 保证:
     * - 所有 ID 长度固定, 适合数据库定长字段存储
     * - 补齐字符 '0' 是字典序最小字符, 不影响排序, ID 仍保持时间递增有序
     *
     * @param  string  $encoded  编码后的字符串
     * @param  int  $decimalDigits  原始十进制数的位数
     * @param  string  $encoding  编码字符集
     * @return string
     */
    protected static function padToFixedLength(string $encoded, int $decimalDigits, string $encoding): string
    {
        $base = strlen($encoding);
        $maxLength = (int) ceil($decimalDigits * log(10) / log($base));

        return str_pad($encoded, $maxLength, $encoding[0], STR_PAD_LEFT);
    }

    /**
     * 当前毫秒时间戳.
     *
     * @return int
     */
    protected static function currentTimeMillis(): int
    {
        // 优先使用 hrtime 单调时钟, 避免 NTP 跳变和精度抖动
        if (function_exists('hrtime')) {
            static $offset = null;
            if ($offset === null) {
                $offset = (int) (microtime(true) * 1000) - intdiv(hrtime(true), 1000000);
            }

            return $offset + intdiv(hrtime(true), 1000000);
        }

        return (int) (microtime(true) * 1000);
    }
}
