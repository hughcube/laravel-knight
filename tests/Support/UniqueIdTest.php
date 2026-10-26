<?php

namespace HughCube\Laravel\Knight\Tests\Support;

use HughCube\Base\Base;
use HughCube\Laravel\Knight\Support\UniqueId;
use HughCube\Laravel\Knight\Tests\TestCase;

class UniqueIdTest extends TestCase
{
    public function testReturnsNonEmptyBase36String()
    {
        foreach ($this->methodProvider() as $name => $args) {
            $method = $args[0];
            $id = UniqueId::$method();
            $this->assertNotEmpty($id, "$name: ID should not be empty");
            $this->assertIsString($id, "$name: ID should be a string");
            $this->assertTrue(1 === preg_match('/^[0-9a-z]+$/', $id), "$name: ID should be base36");
        }
    }

    public function testUniqueness()
    {
        foreach ($this->methodProvider() as $name => $args) {
            $method = $args[0];
            $ids = [];
            for ($i = 0; $i < 10000; $i++) {
                $ids[] = UniqueId::$method();
            }

            $this->assertCount(10000, array_unique($ids), "$name: 10000 IDs should be unique");
        }
    }

    public function testLengthByMethod()
    {
        $expectedLengths = [
            'short' => 13,
            'process' => 27,
            'distributed' => 48,
            'secure' => 64,
        ];

        foreach ($this->methodProvider() as $name => $args) {
            $method = $args[0];
            $id = UniqueId::$method();
            $length = strlen($id);

            $this->assertSame($expectedLengths[$method], $length, "$name: length mismatch");
        }
    }

    public function testShortIsShortest()
    {
        $shortLen = strlen(UniqueId::short());
        $processLen = strlen(UniqueId::process());
        $distributedLen = strlen(UniqueId::distributed());
        $secureLen = strlen(UniqueId::secure());

        $this->assertLessThanOrEqual($processLen, $shortLen);
        $this->assertLessThanOrEqual($distributedLen, $processLen);
        $this->assertLessThanOrEqual($secureLen, $distributedLen);
    }

    public function testBase62Encoding()
    {
        $id = UniqueId::distributed(UniqueId::BASE62);
        $this->assertNotEmpty($id);
        $this->assertTrue(1 === preg_match('/^[0-9a-zA-Z]+$/', $id));
    }

    public function testBase36IsLongerThanBase62()
    {
        $base36 = UniqueId::short(UniqueId::BASE36);
        $base62 = UniqueId::short(UniqueId::BASE62);

        $this->assertGreaterThanOrEqual(strlen($base62), strlen($base36));
    }

    public function testTimeOrdering()
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = UniqueId::short();
        }

        $decimals = array_map(function ($id) {
            return Base::conv($id, UniqueId::BASE36, '0123456789');
        }, $ids);

        for ($i = 1; $i < count($decimals); $i++) {
            $this->assertTrue(
                bccomp($decimals[$i - 1], $decimals[$i]) <= 0,
                sprintf('ID decimal %s should be <= %s', $decimals[$i - 1], $decimals[$i])
            );
        }
    }

    public function testSecureRandomness()
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = UniqueId::secure();
        }

        $this->assertCount(100, array_unique($ids));
    }

    public function testShortWithBase62()
    {
        $id = UniqueId::short(UniqueId::BASE62);
        $this->assertTrue(1 === preg_match('/^[0-9a-zA-Z]+$/', $id));
    }

    public function testFixedLengthConsistency()
    {
        foreach ($this->methodProvider() as $name => $args) {
            $method = $args[0];
            $lengths = [];
            for ($i = 0; $i < 100; $i++) {
                $lengths[] = strlen(UniqueId::$method());
            }

            $this->assertCount(1, array_unique($lengths), "$method should always return the same length");
        }
    }

    public function testVariableLengthMode()
    {
        foreach ($this->methodProvider() as $name => $args) {
            $method = $args[0];
            $id = UniqueId::$method(UniqueId::BASE36, false);
            $this->assertNotEmpty($id, "$name: variable length ID should not be empty");
            $this->assertTrue(1 === preg_match('/^[0-9a-z]+$/', $id), "$name: should be base36");
        }
    }

    public function testUnorderedBase36()
    {
        foreach ($this->methodProvider() as $name => $args) {
            $method = $args[0];
            $id = UniqueId::$method(UniqueId::UNORDERED_BASE36);
            $this->assertNotEmpty($id, "$name: unordered base36 ID should not be empty");
            $this->assertTrue(1 === preg_match('/^[0-9a-z]+$/', $id), "$name: should be base36");
        }
    }

    public function testUnorderedBase62()
    {
        foreach ($this->methodProvider() as $name => $args) {
            $method = $args[0];
            $id = UniqueId::$method(UniqueId::UNORDERED_BASE62);
            $this->assertNotEmpty($id, "$name: unordered base62 ID should not be empty");
            $this->assertTrue(1 === preg_match('/^[0-9a-zA-Z]+$/', $id), "$name: should be base62");
        }
    }

    public function testUnorderedNotLexicographicOrdered()
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = UniqueId::distributed(UniqueId::UNORDERED_BASE36);
        }

        $sorted = $ids;
        sort($sorted);
        // 无序编码下, 时间递增的 ID 不应该恰好是字典序递增的
        $this->assertNotSame($ids, $sorted);
    }

    public function testFixedLengthLexicographicOrdering()
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = UniqueId::process();
        }

        for ($i = 1; $i < count($ids); $i++) {
            $this->assertTrue(
                strcmp($ids[$i - 1], $ids[$i]) <= 0,
                sprintf('ID %s should be <= %s in lexicographic order', $ids[$i - 1], $ids[$i])
            );
        }
    }

    /**
     * 用最小值(全0)和最大值(全9)双重验证: 十进制位数 → 编码长度 的映射是否正确.
     *
     * 核心断言:
     * - 最大值(全9)自然编码长度 = 期望长度 (不溢出)
     * - 最大值(全9)自然编码长度 = 期望长度 (不浪费, 少1位会装不下)
     * - 最小值(全0)补齐后长度 = 期望长度
     */
    public function testMinMaxDecimalBoundary()
    {
        foreach ($this->decimalDigitsAndLengthProvider() as $caseName => $args) {
            $decimalDigits = $args[0];
            $encoding = $args[1];
            $expectedLen = $args[2];

            $maxDecimal = str_repeat('9', $decimalDigits);
            $base = strlen($encoding);

            // 验证公式: ceil(digits * log(10) / log(base)) = expectedLen
            $calculatedLen = (int) ceil($decimalDigits * log(10) / log($base));
            $this->assertSame(
                $expectedLen,
                $calculatedLen,
                "[$caseName] Formula check: $decimalDigits digits in base$base should need $expectedLen chars"
            );

            // 最大值 (全9): 自然编码长度恰好等于期望长度 (不溢出 + 不浪费)
            $maxEncoded = Base::conv($maxDecimal, '0123456789', $encoding);
            $this->assertSame(
                $expectedLen,
                strlen($maxEncoded),
                "[$caseName] Max($decimalDigits digits) base$base: natural length must be exactly $expectedLen"
            );

            // 最大值补齐后长度不变 (已经是最大长度, pad 不应该增长)
            $maxPadded = str_pad($maxEncoded, $expectedLen, $encoding[0], STR_PAD_LEFT);
            $this->assertSame(
                $expectedLen,
                strlen($maxPadded),
                "[$caseName] Max($decimalDigits digits) base$base: padded length must still be $expectedLen"
            );

            // 最小值 ("1"): 编码后短于期望长度, 补齐后等于期望长度
            $minEncoded = Base::conv('1', '0123456789', $encoding);
            $this->assertLessThan(
                $expectedLen,
                strlen($minEncoded),
                "[$caseName] Min(1) base$base: natural length must be less than $expectedLen"
            );
            $minPadded = str_pad($minEncoded, $expectedLen, $encoding[0], STR_PAD_LEFT);
            $this->assertSame(
                $expectedLen,
                strlen($minPadded),
                "[$caseName] Min(1) base$base: padded length must be $expectedLen"
            );

            // 数学证明 expectedLen 是最小必要长度:
            // base^(expectedLen-1) < 10^decimalDigits  (len-1 不够用)
            // 10^decimalDigits <= base^expectedLen      (len 刚好够用)
            $basePowLen = bcpow((string) $base, (string) $expectedLen);
            $basePowLenMinus1 = bcpow((string) $base, (string) ($expectedLen - 1));
            $tenPowDigits = bcpow('10', (string) $decimalDigits);

            $this->assertTrue(
                bccomp($basePowLenMinus1, $tenPowDigits) < 0,
                "[$caseName] base$base^".($expectedLen - 1)." must be < 10^$decimalDigits (len-1 would overflow)"
            );
            $this->assertTrue(
                bccomp($tenPowDigits, $basePowLen) <= 0,
                "[$caseName] 10^$decimalDigits must be <= base$base^$expectedLen (len is sufficient)"
            );
        }
    }

    public function decimalDigitsAndLengthProvider()
    {
        return [
            // short = time(14) + counter(5) = 19 位十进制
            'short_base36' => [19, UniqueId::BASE36, 13],
            'short_base62' => [19, UniqueId::BASE62, 11],
            'short_unordered36' => [19, UniqueId::UNORDERED_BASE36, 13],
            'short_unordered62' => [19, UniqueId::UNORDERED_BASE62, 11],
            // process = time(14) + pid(10) + seed(12) + counter(5) = 41 位十进制
            'process_base36' => [41, UniqueId::BASE36, 27],
            'process_base62' => [41, UniqueId::BASE62, 23],
            'process_unordered36' => [41, UniqueId::UNORDERED_BASE36, 27],
            'process_unordered62' => [41, UniqueId::UNORDERED_BASE62, 23],
            // distributed = time(14) + machine(19) + pid(10) + seed(12) + counter(5) + random(14) = 74 位十进制
            'distributed_base36' => [74, UniqueId::BASE36, 48],
            'distributed_base62' => [74, UniqueId::BASE62, 42],
            'distributed_unordered36' => [74, UniqueId::UNORDERED_BASE36, 48],
            'distributed_unordered62' => [74, UniqueId::UNORDERED_BASE62, 42],
            // secure = time(14) + machine(19) + pid(10) + seed(12) + counter(5) + random(39) = 99 位十进制
            'secure_base36' => [99, UniqueId::BASE36, 64],
            'secure_base62' => [99, UniqueId::BASE62, 56],
            'secure_unordered36' => [99, UniqueId::UNORDERED_BASE36, 64],
            'secure_unordered62' => [99, UniqueId::UNORDERED_BASE62, 56],
        ];
    }

    /**
     * 验证各字段的实际最大值不超过其声明的十进制位宽.
     *
     * 如果字段值超过 pad 宽度, toStringWithPad 输出会比声明的长,
     * 导致总十进制位数增加, 编码后长度突破固定长度.
     */
    public function testFieldMaxValuesFitInDeclaredWidth()
    {
        // time: 14 位十进制, 可用约 3170 年, 当前相对纪元的时间远小于上限
        $currentRelativeTime = (int) (microtime(true) * 1000) - 1577836800000;
        $this->assertLessThanOrEqual(
            14,
            strlen((string) $currentRelativeTime),
            'Current relative time must fit in 14 decimal digits'
        );

        // machineId: 63-bit, 贴合 PHP_INT_MAX (2^63-1 = 9223372036854775807), 必须 ≤ 19 位十进制
        $maxMachineId = PHP_INT_MAX;
        $this->assertLessThanOrEqual(
            19,
            strlen((string) $maxMachineId),
            'machineId max (PHP_INT_MAX) must fit in 19 decimal digits'
        );
        // 并且 < 10^19 (确保 toStringWithPad 不会输出 20 位)
        $this->assertTrue(
            bccomp((string) $maxMachineId, bcpow('10', '19')) < 0,
            'machineId max must be < 10^19'
        );

        // PID: Windows 最大 4294967295 (32-bit unsigned), 必须 ≤ 10 位
        $this->assertLessThanOrEqual(
            10,
            strlen('4294967295'),
            'PID max must fit in 10 decimal digits'
        );

        // seed: max = 999999999999, 正好 12 位
        $this->assertSame(
            12,
            strlen('999999999999'),
            'seed max must be exactly 12 decimal digits'
        );

        // counter: max = 99999, 正好 5 位
        $this->assertSame(
            5,
            strlen((string) UniqueId::MAX_COUNTER),
            'MAX_COUNTER must be exactly 5 decimal digits'
        );

        // random: secure 使用 13 位, distributed 使用 14 位
        $this->assertSame(
            13,
            strlen('9999999999999'),
            'random (secure) max must be exactly 13 decimal digits'
        );
        $this->assertSame(
            14,
            strlen('99999999999999'),
            'random (distributed) max must be exactly 14 decimal digits'
        );
    }

    /**
     * 验证 toStringWithPad 输出长度与声明一致.
     */
    public function testToStringWithPadProducesExactWidth()
    {
        $fields = [
            [0, 14],
            [99999999999999, 14],
            [0, 19],
            [PHP_INT_MAX, 19],  // 2^63 - 1 = 9223372036854775807
            [0, 10],
            [4294967295, 10],
            [0, 12],
            [999999999999, 12],
            [0, 5],
            [99999, 5],
            [0, 13],
            [9999999999999, 13],
            [99999999999999, 14],
        ];

        foreach ($fields as [$value, $width]) {
            $padded = Base::toStringWithPad($value, $width);
            $this->assertSame(
                $width,
                strlen($padded),
                "toStringWithPad($value, $width) should produce exactly $width chars, got '$padded'"
            );
        }
    }

    /**
     * 验证 UNORDERED 字符集是 BASE 字符集的有效排列 (不多不少不重复).
     */
    public function testUnorderedAlphabetsAreValidPermutations()
    {
        // UNORDERED_BASE36 必须是 BASE36 的排列 (相同字符, 不同顺序)
        $this->assertSame(strlen(UniqueId::BASE36), strlen(UniqueId::UNORDERED_BASE36));
        $original36 = str_split(UniqueId::BASE36);
        $shuffled36 = str_split(UniqueId::UNORDERED_BASE36);
        sort($original36);
        sort($shuffled36);
        $this->assertSame(
            $original36,
            $shuffled36,
            'UNORDERED_BASE36 must contain exactly the same chars as BASE36'
        );
        $this->assertNotSame(
            UniqueId::BASE36,
            UniqueId::UNORDERED_BASE36,
            'UNORDERED_BASE36 must not equal BASE36'
        );

        // UNORDERED_BASE62 必须是 BASE62 的排列 (相同字符, 不同顺序)
        $this->assertSame(strlen(UniqueId::BASE62), strlen(UniqueId::UNORDERED_BASE62));
        $original62 = str_split(UniqueId::BASE62);
        $shuffled62 = str_split(UniqueId::UNORDERED_BASE62);
        sort($original62);
        sort($shuffled62);
        $this->assertSame(
            $original62,
            $shuffled62,
            'UNORDERED_BASE62 must contain exactly the same chars as BASE62'
        );
        $this->assertNotSame(
            UniqueId::BASE62,
            UniqueId::UNORDERED_BASE62,
            'UNORDERED_BASE62 must not equal BASE62'
        );
    }

    public function testShortInitializesPidSnapshotForForkSafety()
    {
        $this->setUniqueIdState('processSeed', null);
        $this->setUniqueIdState('seedPid', null);
        $this->setUniqueIdState('fallbackPid', null);
        $this->setUniqueIdState('counter', 0);
        $this->setUniqueIdState('lastTimestamp', 0);

        UniqueId::short();

        $seedPid = $this->getUniqueIdState('seedPid');
        $this->assertIsInt($seedPid, 'short() should initialize pid snapshot');
        $this->assertGreaterThan(0, $seedPid, 'pid snapshot should be positive');
    }

    public function testForkDetectionResetsStateForShortMode()
    {
        $fakePid = -1;

        $this->setUniqueIdState('processSeed', null);
        $this->setUniqueIdState('fallbackPid', null);
        $this->setUniqueIdState('counter', 12345);
        $this->setUniqueIdState('lastTimestamp', 123456789);
        $this->setUniqueIdState('seedPid', $fakePid);

        UniqueId::short();

        $this->assertIsInt($this->getUniqueIdState('seedPid'));
        $this->assertNotSame(
            $fakePid,
            $this->getUniqueIdState('seedPid'),
            'fork detection should refresh pid snapshot for current process'
        );
        $this->assertNull(
            $this->getUniqueIdState('processSeed'),
            'short mode should keep process seed null after fork state reset'
        );
    }

    public function testShortKeepsProcessSeedNullWhenNoFork()
    {
        $this->setUniqueIdState('processSeed', null);
        $this->setUniqueIdState('seedPid', null);
        $this->setUniqueIdState('fallbackPid', null);

        UniqueId::short();
        UniqueId::short();

        $this->assertNull(
            $this->getUniqueIdState('processSeed'),
            'short mode should not initialize process seed'
        );
    }

    public function testShortClearsExistingProcessSeedWhenForkDetected()
    {
        $this->setUniqueIdState('processSeed', 123456789012);
        $this->setUniqueIdState('seedPid', -1);
        $this->setUniqueIdState('fallbackPid', null);
        $this->setUniqueIdState('counter', 12345);
        $this->setUniqueIdState('lastTimestamp', 123456789);

        UniqueId::short();

        $this->assertNull(
            $this->getUniqueIdState('processSeed'),
            'short mode should clear inherited process seed after fork detection'
        );
        $this->assertSame(
            $this->callUniqueIdMethod('getProcessId'),
            $this->getUniqueIdState('seedPid'),
            'pid snapshot should be refreshed to current process'
        );
        $this->assertTrue(
            $this->getUniqueIdState('counter') >= 0 && $this->getUniqueIdState('counter') <= UniqueId::MAX_COUNTER,
            'counter should stay inside declared range'
        );
        $this->assertGreaterThan(
            0,
            $this->getUniqueIdState('lastTimestamp'),
            'timestamp should be refreshed after state reset'
        );
    }

    public function testCheckForkDoesNotResetStateWhenPidUnchanged()
    {
        $pid = $this->callUniqueIdMethod('getProcessId');

        $this->setUniqueIdState('processSeed', 123456);
        $this->setUniqueIdState('seedPid', $pid);
        $this->setUniqueIdState('counter', 987);
        $this->setUniqueIdState('lastTimestamp', 123456789);

        $this->callUniqueIdMethod('checkFork');

        $this->assertSame(123456, $this->getUniqueIdState('processSeed'));
        $this->assertSame($pid, $this->getUniqueIdState('seedPid'));
        $this->assertSame(987, $this->getUniqueIdState('counter'));
        $this->assertSame(123456789, $this->getUniqueIdState('lastTimestamp'));
    }

    public function testProcessRegeneratesProcessSeedAfterForkDetected()
    {
        $this->setUniqueIdState('processSeed', -1);
        $this->setUniqueIdState('seedPid', -1);
        $this->setUniqueIdState('fallbackPid', null);
        $this->setUniqueIdState('counter', 12345);
        $this->setUniqueIdState('lastTimestamp', 123456789);

        $id = UniqueId::process();

        $this->assertNotEmpty($id);
        $this->assertNotSame(
            -1,
            $this->getUniqueIdState('processSeed'),
            'process mode should regenerate process seed after fork detection'
        );
        $this->assertSame(
            $this->callUniqueIdMethod('getProcessId'),
            $this->getUniqueIdState('seedPid'),
            'process mode should refresh pid snapshot to current process'
        );
        $this->assertTrue(
            $this->getUniqueIdState('counter') >= 0 && $this->getUniqueIdState('counter') <= UniqueId::MAX_COUNTER,
            'counter should stay inside declared range'
        );
    }

    public function testProcessIdSegmentMatchesCurrentPid()
    {
        $id = UniqueId::process();
        $decimal = Base::conv($id, UniqueId::BASE36, '0123456789');
        $decimal = str_pad($decimal, 41, '0', STR_PAD_LEFT);

        $expectedPid = Base::toStringWithPad($this->callUniqueIdMethod('getProcessId'), 10);
        $actualPid = substr($decimal, 14, 10);

        $this->assertSame($expectedPid, $actualPid, 'process ID should embed current pid in fixed field');
    }

    public function testDistributedMachineSegmentIsStable()
    {
        $id1 = UniqueId::distributed();
        $id2 = UniqueId::distributed();

        $decimal1 = str_pad(Base::conv($id1, UniqueId::BASE36, '0123456789'), 74, '0', STR_PAD_LEFT);
        $decimal2 = str_pad(Base::conv($id2, UniqueId::BASE36, '0123456789'), 74, '0', STR_PAD_LEFT);

        $machineSegment1 = substr($decimal1, 14, 19);
        $machineSegment2 = substr($decimal2, 14, 19);
        $expectedMachine = Base::toStringWithPad($this->callUniqueIdMethod('getMachineId'), 19);

        $this->assertSame(
            $machineSegment1,
            $machineSegment2,
            'distributed mode should keep machine segment stable across calls'
        );
        $this->assertSame(
            $expectedMachine,
            $machineSegment1,
            'distributed mode should use current cached machine id'
        );
    }

    public function testGetProcessIdReturnsPositiveInteger()
    {
        $pid = $this->callUniqueIdMethod('getProcessId');

        $this->assertIsInt($pid);
        $this->assertGreaterThan(0, $pid);
        $this->assertLessThanOrEqual(4294967295, $pid);
    }

    public function testGetMachineIdIsStableAndWithinRange()
    {
        $this->setUniqueIdState('machineId', null);

        $machine1 = $this->callUniqueIdMethod('getMachineId');
        $machine2 = $this->callUniqueIdMethod('getMachineId');

        $this->assertIsInt($machine1);
        $this->assertSame($machine1, $machine2, 'machine id should be cached and stable');
        $this->assertGreaterThanOrEqual(0, $machine1);
        $this->assertLessThanOrEqual(PHP_INT_MAX, $machine1);
        $this->assertTrue(
            strlen((string) $machine1) <= (PHP_INT_SIZE >= 8 ? 19 : 10),
            'machine id should stay within declared decimal width'
        );
    }

    public function testMachineIdNamespaceEnvCanPartitionIdDomain()
    {
        $env = 'KNIGHT_UNIQUE_ID_NAMESPACE';
        $original = getenv($env);

        try {
            putenv($env.'=namespace_a');
            $this->setUniqueIdState('machineId', null);
            $machineA1 = $this->callUniqueIdMethod('getMachineId');

            // Reset cache and re-read under the same namespace; value should remain stable.
            $this->setUniqueIdState('machineId', null);
            $machineA2 = $this->callUniqueIdMethod('getMachineId');

            putenv($env.'=namespace_b');
            $this->setUniqueIdState('machineId', null);
            $machineB = $this->callUniqueIdMethod('getMachineId');

            $this->assertSame($machineA1, $machineA2, 'machine id should be stable in one namespace');
            $this->assertNotSame($machineA1, $machineB, 'different namespaces should produce different machine ids');
        } finally {
            if (false === $original) {
                putenv($env);
            } else {
                putenv($env.'='.$original);
            }
            $this->setUniqueIdState('machineId', null);
        }
    }

    public function testPadToFixedLengthUsesFirstEncodingChar()
    {
        $encoding = UniqueId::UNORDERED_BASE36;
        $padded = $this->callUniqueIdMethod('padToFixedLength', '1', 19, $encoding);

        $this->assertSame(13, strlen($padded));
        $this->assertSame('1', substr($padded, -1));
        $this->assertSame(str_repeat($encoding[0], 12), substr($padded, 0, 12));

        $alreadyMax = str_repeat('z', 13);
        $this->assertSame(
            $alreadyMax,
            $this->callUniqueIdMethod('padToFixedLength', $alreadyMax, 19, UniqueId::BASE36)
        );
    }

    public function testVariableLengthNeverExceedsFixedLength()
    {
        foreach ($this->methodProvider() as $name => $args) {
            $method = $args[0];
            $fixedLength = strlen(UniqueId::$method(UniqueId::BASE36, true));

            for ($i = 0; $i < 100; $i++) {
                $variable = UniqueId::$method(UniqueId::BASE36, false);
                $this->assertTrue(
                    strlen($variable) <= $fixedLength,
                    "$name: variable length should never exceed fixed length"
                );
            }
        }
    }

    /**
     * @param  mixed  ...$args
     * @return mixed
     *
     * @throws \ReflectionException
     */
    protected function callUniqueIdMethod(string $method, ...$args)
    {
        $reflection = new \ReflectionClass(UniqueId::class);
        $instance = $reflection->getMethod($method);
        $instance->setAccessible(true);

        return $instance->invokeArgs(null, $args);
    }

    /**
     * @return mixed
     *
     * @throws \ReflectionException
     */
    protected function getUniqueIdState(string $property)
    {
        $reflection = new \ReflectionClass(UniqueId::class);
        $instance = $reflection->getProperty($property);
        $instance->setAccessible(true);

        return $instance->getValue();
    }

    /**
     * @param  mixed  $value
     *
     * @throws \ReflectionException
     */
    protected function setUniqueIdState(string $property, $value): void
    {
        $reflection = new \ReflectionClass(UniqueId::class);
        $instance = $reflection->getProperty($property);
        $instance->setAccessible(true);
        $instance->setValue($value);
    }

    public function methodProvider()
    {
        return [
            'short' => ['short'],
            'process' => ['process'],
            'distributed' => ['distributed'],
            'secure' => ['secure'],
        ];
    }
}
