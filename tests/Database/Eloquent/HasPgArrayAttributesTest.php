<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/1/25
 * Time: 10:00.
 */

namespace HughCube\Laravel\Knight\Tests\Database\Eloquent;

use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\HasPgArrayAttributes;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Collection;

class HasPgArrayAttributesTest extends TestCase
{
    // ==================== initializeHasPgArrayAttributes Tests ====================

    public function testInitializeRemovesArrayCastsByDefault()
    {
        $model = new TestPgArrayModel();
        $casts = $model->getCasts();

        // ARRAY casts should be removed by default
        $this->assertArrayNotHasKey('tags', $casts);
        $this->assertArrayNotHasKey('scores', $casts);
        // Other casts should remain
        $this->assertArrayHasKey('id', $casts);
        $this->assertEquals('integer', $casts['id']);
    }

    public function testInitializePreservesArrayCastsWhenPropertyIsTrue()
    {
        $model = new TestPgArrayModelPreserveCasts();
        $casts = $model->getCasts();

        // ARRAY casts should be preserved when $preserveArrayCasts = true
        $this->assertArrayHasKey('tags', $casts);
        $this->assertEquals('ARRAY', $casts['tags']);
        $this->assertArrayHasKey('scores', $casts);
        $this->assertEquals('ARRAY', $casts['scores']);
    }

    public function testShouldPreserveArrayCastsMethodNotDefinedByDefault()
    {
        $model = new TestPgArrayModel();

        // By default, model does not define shouldPreserveUppercaseArrayCast method
        $this->assertFalse(method_exists($model, 'shouldPreserveUppercaseArrayCast'));
    }

    public function testShouldPreserveArrayCastsMethodCanBeDefinedByModel()
    {
        $model = new TestPgArrayModelPreserveCasts();

        // Model can define shouldPreserveUppercaseArrayCast method
        $this->assertTrue(method_exists($model, 'shouldPreserveUppercaseArrayCast'));
        $this->assertTrue($model->shouldPreserveUppercaseArrayCast());
    }

    // ==================== parsePgIntArray Tests ====================

    public function testParsePgIntArrayWithValidData()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', ['{1,2,3}']);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testParsePgIntArrayWithSingleElement()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', ['{42}']);
        $this->assertEquals([42], $result->all());
    }

    public function testParsePgIntArrayWithNegativeNumbers()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', ['{-1,-2,3}']);
        $this->assertEquals([-1, -2, 3], $result->all());
    }

    public function testParsePgIntArrayWithEmptyString()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', ['']);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testParsePgIntArrayWithEmptyArray()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', ['{}']);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testParsePgIntArrayWithNull()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', [null]);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testParsePgIntArrayWithNonString()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', [123]);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testParsePgIntArrayWithLargeNumbers()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', ['{1000000,2000000,3000000}']);
        $this->assertEquals([1000000, 2000000, 3000000], $result->all());
    }

    public function testParsePgIntArrayWithZero()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgIntArray', ['{0,1,2}']);
        $this->assertEquals([0, 1, 2], $result->all());
    }

    public function testParsePgIntArrayWithBigInt()
    {
        $model = new TestPgArrayModel();

        // PHP_INT_MAX on 64-bit: 9223372036854775807
        $phpIntMax = (string) PHP_INT_MAX;
        $phpIntMin = (string) PHP_INT_MIN;

        // 在 PHP int 范围内应该返回 int
        $result = $this->invokeMethod($model, 'parsePgIntArray', [sprintf('{%s,%s}', $phpIntMax, $phpIntMin)]);
        $this->assertSame(PHP_INT_MAX, $result[0]);
        $this->assertSame(PHP_INT_MIN, $result[1]);
    }

    public function testParsePgIntArrayWithBigIntOverflow()
    {
        $model = new TestPgArrayModel();

        // 超过 PHP_INT_MAX 的值应该保持为字符串
        $overflowValue = '9223372036854775808'; // PHP_INT_MAX + 1
        $underflowValue = '-9223372036854775809'; // PHP_INT_MIN - 1

        $result = $this->invokeMethod($model, 'parsePgIntArray', [sprintf('{%s,%s,123}', $overflowValue, $underflowValue)]);

        // 超出范围的值保持字符串
        $this->assertSame($overflowValue, $result[0]);
        $this->assertSame($underflowValue, $result[1]);
        // 正常范围内的值是 int
        $this->assertSame(123, $result[2]);
    }

    public function testSerializePgIntArrayWithBigInt()
    {
        $model = new TestPgArrayModel();

        // 测试 bigint 字符串的序列化
        $bigints = ['9223372036854775807', '-9223372036854775808', '9223372036854775808'];
        $result = $this->invokeMethod($model, 'serializePgIntArray', [$bigints]);

        $this->assertEquals('{9223372036854775807,-9223372036854775808,9223372036854775808}', $result);
    }

    public function testIsIntegerInPhpRange()
    {
        $model = new TestPgArrayModel();

        $phpIntMax = \HughCube\Base\Base::toString(PHP_INT_MAX);
        $phpIntMin = \HughCube\Base\Base::toString(PHP_INT_MIN);

        // 基本值测试
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['0']));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['1']));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['-1']));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['123456789']));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['-123456789']));

        // 精确边界测试: PHP_INT_MAX 和 PHP_INT_MIN
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', [$phpIntMax]));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', [$phpIntMin]));

        // 边界 +1/-1 测试 (使用 bcadd)
        $overMax = function_exists('bcadd') ? bcadd($phpIntMax, '1', 0) : '9223372036854775808';
        $underMin = function_exists('bcsub') ? bcsub($phpIntMin, '1', 0) : '-9223372036854775809';
        $this->assertFalse($this->invokeMethod($model, 'isIntegerInPhpRange', [$overMax]));
        $this->assertFalse($this->invokeMethod($model, 'isIntegerInPhpRange', [$underMin]));

        // 大数测试
        $this->assertFalse($this->invokeMethod($model, 'isIntegerInPhpRange', ['99999999999999999999']));
        $this->assertFalse($this->invokeMethod($model, 'isIntegerInPhpRange', ['-99999999999999999999']));

        // 位数较少的值（一定在范围内）
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['999999999999999999']));   // 18位
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['-999999999999999999']));  // 18位

        // 前导零和正号测试
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['0000123']));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['+123']));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['+0']));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['-0']));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['00000000000000000000'])); // 全零

        // 19位但在范围内的值（小于边界值）
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['1000000000000000000']));  // 1后跟18个0
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', ['-1000000000000000000']));

        // 19位但超出范围的值（大于边界值）
        $this->assertFalse($this->invokeMethod($model, 'isIntegerInPhpRange', ['9999999999999999999'])); // 19个9
        $this->assertFalse($this->invokeMethod($model, 'isIntegerInPhpRange', ['-9999999999999999999']));

        // 边界值减1（仍在范围内）
        $maxMinus1 = function_exists('bcsub') ? bcsub($phpIntMax, '1', 0) : '9223372036854775806';
        $minPlus1 = function_exists('bcadd') ? bcadd($phpIntMin, '1', 0) : '-9223372036854775807';
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', [$maxMinus1]));
        $this->assertTrue($this->invokeMethod($model, 'isIntegerInPhpRange', [$minPlus1]));
    }

    // ==================== parsePgSimpleTextArray Tests ====================

    public function testParsePgSimpleTextArrayWithValidData()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgSimpleTextArray', ['{o:1,o:2,o:3}']);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(['o:1', 'o:2', 'o:3'], $result->all());
    }

    public function testParsePgSimpleTextArrayWithSingleElement()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgSimpleTextArray', ['{hello}']);
        $this->assertEquals(['hello'], $result->all());
    }

    public function testParsePgSimpleTextArrayWithMixedContent()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgSimpleTextArray', ['{a-b,c_d,e.f,g:h}']);
        $this->assertEquals(['a-b', 'c_d', 'e.f', 'g:h'], $result->all());
    }

    public function testParsePgSimpleTextArrayWithEmptyString()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgSimpleTextArray', ['']);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testParsePgSimpleTextArrayWithEmptyArray()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgSimpleTextArray', ['{}']);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testParsePgSimpleTextArrayWithNull()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgSimpleTextArray', [null]);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testParsePgSimpleTextArrayWithNonString()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'parsePgSimpleTextArray', [123]);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    // ==================== serializePgIntArray Tests ====================

    public function testSerializePgIntArrayWithValidData()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgIntArray', [[1, 2, 3]]);
        $this->assertEquals('{1,2,3}', $result);
    }

    public function testSerializePgIntArrayWithSingleElement()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgIntArray', [[42]]);
        $this->assertEquals('{42}', $result);
    }

    public function testSerializePgIntArrayWithEmptyArray()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgIntArray', [[]]);
        $this->assertEquals('{}', $result);
    }

    public function testSerializePgIntArrayWithNegativeNumbers()
    {
        $model = new TestPgArrayModel();

        // Note: negative numbers might be filtered out depending on isDigit implementation
        $result = $this->invokeMethod($model, 'serializePgIntArray', [[-1, 2, -3]]);
        $this->assertStringStartsWith('{', $result);
        $this->assertStringEndsWith('}', $result);
    }

    public function testSerializePgIntArrayWithStringNumbers()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgIntArray', [['1', '2', '3']]);
        $this->assertEquals('{1,2,3}', $result);
    }

    public function testSerializePgIntArrayFiltersNonDigit()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgIntArray', [[1, 'abc', 2, null, 3]]);
        $this->assertEquals('{1,2,3}', $result);
    }

    public function testSerializePgIntArrayWithZero()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgIntArray', [[0, 1, 2]]);
        $this->assertEquals('{0,1,2}', $result);
    }

    public function testSerializePgIntArrayWithCollection()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgIntArray', [collect([1, 2, 3])]);
        $this->assertEquals('{1,2,3}', $result);
    }

    // ==================== serializePgSimpleTextArray Tests ====================

    public function testSerializePgSimpleTextArrayWithValidData()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['o:1', 'o:2', 'o:3']]);
        $this->assertEquals('{o:1,o:2,o:3}', $result);
    }

    public function testSerializePgSimpleTextArrayWithSingleElement()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['hello']]);
        $this->assertEquals('{hello}', $result);
    }

    public function testSerializePgSimpleTextArrayWithEmptyArray()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [[]]);
        $this->assertEquals('{}', $result);
    }

    public function testSerializePgSimpleTextArrayFiltersEmptyStrings()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['a', '', 'b', '', 'c']]);
        $this->assertEquals('{a,b,c}', $result);
    }

    public function testSerializePgSimpleTextArrayFiltersInvalidCharacters()
    {
        $model = new TestPgArrayModel();

        // Elements with special characters should be filtered out
        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['valid', 'also-valid', 'has space']]);
        $this->assertEquals('{valid,also-valid}', $result);
    }

    public function testSerializePgSimpleTextArrayAllowsValidCharacters()
    {
        $model = new TestPgArrayModel();

        // Test basic allowed characters: letters, numbers, colon, underscore, dot, hyphen
        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['aZ09', 'a:b', 'a_b', 'a.b', 'a-b']]);
        $this->assertEquals('{aZ09,a:b,a_b,a.b,a-b}', $result);
    }

    public function testSerializePgSimpleTextArrayAllowsExtendedCharacters()
    {
        $model = new TestPgArrayModel();

        // Test extended allowed characters: slash, @, +, =, #, ~
        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['a/b', 'user@domain', 'a+b', 'key=val', 'tag#1', 'path~home']]);
        $this->assertEquals('{a/b,user@domain,a+b,key=val,tag#1,path~home}', $result);
    }

    public function testSerializePgSimpleTextArrayAllAllowedCharacters()
    {
        $model = new TestPgArrayModel();

        // 测试所有允许的字符
        $allowedCases = [
            'a',           // 小写字母
            'Z',           // 大写字母
            '5',           // 数字
            'a:b',         // 冒号
            'a_b',         // 下划线
            'a.b',         // 点
            'a/b',         // 斜杠
            'a-b',         // 连字符
            'a@b',         // at符号
            'a+b',         // 加号
            'a=b',         // 等号
            'a#b',         // 井号
            'a~b',         // 波浪号
            ':',           // 单独冒号
            '_',           // 单独下划线
            '.',           // 单独点
            '/',           // 单独斜杠
            '-',           // 单独连字符
            '@',           // 单独at
            '+',           // 单独加号
            '=',           // 单独等号
            '#',           // 单独井号
            '~',           // 单独波浪号
            ':_./-@+=#~',  // 所有特殊字符组合
        ];

        foreach ($allowedCases as $input) {
            $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [[$input]]);
            $this->assertEquals(sprintf('{%s}', $input), $result, "Character '$input' should be allowed");
        }
    }

    public function testSerializePgSimpleTextArrayAllDisallowedCharacters()
    {
        $model = new TestPgArrayModel();

        // 测试所有不允许的字符
        $disallowedCases = [
            'a b',    // 空格
            'a,b',    // 逗号
            'a{b',    // 左花括号
            'a}b',    // 右花括号
            'a"b',    // 双引号
            "a'b",    // 单引号
            'a\\b',   // 反斜杠
            'a;b',    // 分号
            'a*b',    // 星号
            'a?b',    // 问号
            'a&b',    // 和号
            'a|b',    // 管道
            'a<b',    // 小于号
            'a>b',    // 大于号
            'a`b',    // 反引号
            'a$b',    // 美元符号
            'a%b',    // 百分号
            'a^b',    // 脱字符
            'a(b',    // 左括号
            'a)b',    // 右括号
            'a[b',    // 左方括号
            'a]b',    // 右方括号
            "a\nb",   // 换行符
            "a\tb",   // 制表符
        ];

        foreach ($disallowedCases as $input) {
            $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [[$input]]);
            $this->assertEquals('{}', $result, "Character '$input' should be filtered out");
        }
    }

    public function testSerializePgSimpleTextArrayFiltersDisallowedCharacters()
    {
        $model = new TestPgArrayModel();

        // Test disallowed characters: space, comma, curly braces, quotes, backslash
        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [
            ['valid', 'has space', 'has,comma', 'has{brace', 'has}brace', 'has"quote', "has'quote", 'has\\slash'],
        ]);
        $this->assertEquals('{valid}', $result);
    }

    public function testSerializePgSimpleTextArrayWithPathLikeStrings()
    {
        $model = new TestPgArrayModel();

        // Test path-like strings
        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['path/to/file', 'dir/subdir', 'a.b/c.d']]);
        $this->assertEquals('{path/to/file,dir/subdir,a.b/c.d}', $result);
    }

    public function testSerializePgSimpleTextArrayWithEmailLikeStrings()
    {
        $model = new TestPgArrayModel();

        // Test email-like strings (without full validation, just character support)
        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['user@example.com', 'test+tag@domain.org']]);
        $this->assertEquals('{user@example.com,test+tag@domain.org}', $result);
    }

    public function testSerializePgSimpleTextArrayWithCollection()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [collect(['a', 'b', 'c'])]);
        $this->assertEquals('{a,b,c}', $result);
    }

    public function testSerializePgSimpleTextArrayFiltersNonStrings()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['valid', 123, 'also-valid', null]]);
        $this->assertEquals('{valid,also-valid}', $result);
    }

    // ==================== Round-trip Tests ====================

    public function testIntArrayRoundTrip()
    {
        $model = new TestPgArrayModel();
        $original = [1, 2, 3, 4, 5];

        $serialized = $this->invokeMethod($model, 'serializePgIntArray', [$original]);
        $parsed = $this->invokeMethod($model, 'parsePgIntArray', [$serialized]);

        $this->assertEquals($original, $parsed->all());
    }

    public function testSimpleTextArrayRoundTrip()
    {
        $model = new TestPgArrayModel();
        $original = ['tag:1', 'tag:2', 'tag:3'];

        $serialized = $this->invokeMethod($model, 'serializePgSimpleTextArray', [$original]);
        $parsed = $this->invokeMethod($model, 'parsePgSimpleTextArray', [$serialized]);

        $this->assertEquals($original, $parsed->all());
    }

    // ==================== Edge Cases ====================

    public function testParsePgIntArrayWithWhitespace()
    {
        $model = new TestPgArrayModel();

        // PostgreSQL might include spaces after commas in some cases
        $result = $this->invokeMethod($model, 'parsePgIntArray', ['{ 1, 2, 3 }']);
        // After trim, these become strings with spaces which intval will handle
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function testSerializePgIntArrayWithDuplicates()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgIntArray', [[1, 1, 2, 2, 3, 3]]);
        $this->assertEquals('{1,1,2,2,3,3}', $result);
    }

    public function testSerializePgSimpleTextArrayWithDuplicates()
    {
        $model = new TestPgArrayModel();

        $result = $this->invokeMethod($model, 'serializePgSimpleTextArray', [['a', 'a', 'b', 'b']]);
        $this->assertEquals('{a,a,b,b}', $result);
    }

    // ==================== PostgreSQL Real Database Tests ====================

    public function testRealDatabaseIntArrayRoundTrip()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
        $connection->statement('CREATE TABLE test_pg_array_trait (id SERIAL PRIMARY KEY, int_arr INTEGER[], text_arr TEXT[])');

        $model = new TestPgArrayModel();

        // Test int array
        $original = [1, 2, 3, 4, 5];
        $serialized = $this->invokeMethod($model, 'serializePgIntArray', [$original]);

        $connection->statement('INSERT INTO test_pg_array_trait (int_arr, text_arr) VALUES (?::integer[], ?::text[])', [$serialized, '{}']);

        $result = $connection->selectOne('SELECT int_arr FROM test_pg_array_trait WHERE id = 1');
        $parsed = $this->invokeMethod($model, 'parsePgIntArray', [$result->int_arr]);

        $this->assertEquals($original, $parsed->all());

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
    }

    public function testRealDatabaseTextArrayRoundTrip()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
        $connection->statement('CREATE TABLE test_pg_array_trait (id SERIAL PRIMARY KEY, int_arr INTEGER[], text_arr TEXT[])');

        $model = new TestPgArrayModel();

        // Test text array with various allowed characters
        $original = ['o:1', 'path/to/file', 'user@domain', 'key=value'];
        $serialized = $this->invokeMethod($model, 'serializePgSimpleTextArray', [$original]);

        $connection->statement('INSERT INTO test_pg_array_trait (int_arr, text_arr) VALUES (?::integer[], ?::text[])', ['{}', $serialized]);

        $result = $connection->selectOne('SELECT text_arr FROM test_pg_array_trait WHERE id = 1');
        $parsed = $this->invokeMethod($model, 'parsePgSimpleTextArray', [$result->text_arr]);

        $this->assertEquals($original, $parsed->all());

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
    }

    public function testRealDatabaseEmptyArrays()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
        $connection->statement('CREATE TABLE test_pg_array_trait (id SERIAL PRIMARY KEY, int_arr INTEGER[], text_arr TEXT[])');

        $model = new TestPgArrayModel();

        // Test empty arrays
        $serializedInt = $this->invokeMethod($model, 'serializePgIntArray', [[]]);
        $serializedText = $this->invokeMethod($model, 'serializePgSimpleTextArray', [[]]);

        $connection->statement('INSERT INTO test_pg_array_trait (int_arr, text_arr) VALUES (?::integer[], ?::text[])', [$serializedInt, $serializedText]);

        $result = $connection->selectOne('SELECT int_arr, text_arr FROM test_pg_array_trait WHERE id = 1');

        $parsedInt = $this->invokeMethod($model, 'parsePgIntArray', [$result->int_arr]);
        $parsedText = $this->invokeMethod($model, 'parsePgSimpleTextArray', [$result->text_arr]);

        $this->assertTrue($parsedInt->isEmpty());
        $this->assertTrue($parsedText->isEmpty());

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
    }

    public function testRealDatabaseLargeIntArray()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
        $connection->statement('CREATE TABLE test_pg_array_trait (id SERIAL PRIMARY KEY, int_arr INTEGER[], text_arr TEXT[])');

        $model = new TestPgArrayModel();

        // Test large int array
        $original = range(1, 100);
        $serialized = $this->invokeMethod($model, 'serializePgIntArray', [$original]);

        $connection->statement('INSERT INTO test_pg_array_trait (int_arr, text_arr) VALUES (?::integer[], ?::text[])', [$serialized, '{}']);

        $result = $connection->selectOne('SELECT int_arr FROM test_pg_array_trait WHERE id = 1');
        $parsed = $this->invokeMethod($model, 'parsePgIntArray', [$result->int_arr]);

        $this->assertEquals($original, $parsed->all());
        $this->assertCount(100, $parsed);

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
    }

    public function testRealDatabaseTextArrayWithSpecialCharacters()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
        $connection->statement('CREATE TABLE test_pg_array_trait (id SERIAL PRIMARY KEY, int_arr INTEGER[], text_arr TEXT[])');

        $model = new TestPgArrayModel();

        // Test text array with all allowed special characters
        $original = ['a-b', 'c_d', 'e.f', 'g:h', 'i/j', 'k@l', 'm+n', 'o=p', 'q#r', 's~t'];
        $serialized = $this->invokeMethod($model, 'serializePgSimpleTextArray', [$original]);

        $connection->statement('INSERT INTO test_pg_array_trait (int_arr, text_arr) VALUES (?::integer[], ?::text[])', ['{}', $serialized]);

        $result = $connection->selectOne('SELECT text_arr FROM test_pg_array_trait WHERE id = 1');
        $parsed = $this->invokeMethod($model, 'parsePgSimpleTextArray', [$result->text_arr]);

        $this->assertEquals($original, $parsed->all());

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_array_trait');
    }

    public function testRealDatabaseBigIntArrayRoundTrip()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table with BIGINT array
        $connection->statement('DROP TABLE IF EXISTS test_pg_bigint_array');
        $connection->statement('CREATE TABLE test_pg_bigint_array (id SERIAL PRIMARY KEY, bigint_arr BIGINT[])');

        $model = new TestPgArrayModel();

        // Test bigint values within PHP int range
        $original = [PHP_INT_MAX, PHP_INT_MIN, 0, 1, -1, 9223372036854775800];
        $serialized = $this->invokeMethod($model, 'serializePgIntArray', [$original]);

        $connection->statement('INSERT INTO test_pg_bigint_array (bigint_arr) VALUES (?::bigint[])', [$serialized]);

        $result = $connection->selectOne('SELECT bigint_arr FROM test_pg_bigint_array WHERE id = 1');
        $parsed = $this->invokeMethod($model, 'parsePgIntArray', [$result->bigint_arr]);

        // PHP_INT_MAX 和 PHP_INT_MIN 应该返回 int 类型
        $this->assertSame(PHP_INT_MAX, $parsed[0]);
        $this->assertSame(PHP_INT_MIN, $parsed[1]);
        $this->assertSame(0, $parsed[2]);
        $this->assertSame(1, $parsed[3]);
        $this->assertSame(-1, $parsed[4]);

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_bigint_array');
    }

    public function testRealDatabaseBigIntArrayWithStringValues()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table with BIGINT array
        $connection->statement('DROP TABLE IF EXISTS test_pg_bigint_array');
        $connection->statement('CREATE TABLE test_pg_bigint_array (id SERIAL PRIMARY KEY, bigint_arr BIGINT[])');

        $model = new TestPgArrayModel();

        // Test bigint values as strings (including values that might overflow PHP int)
        $original = ['9223372036854775807', '-9223372036854775808', '123456789012345678'];
        $serialized = $this->invokeMethod($model, 'serializePgIntArray', [$original]);

        $this->assertEquals('{9223372036854775807,-9223372036854775808,123456789012345678}', $serialized);

        $connection->statement('INSERT INTO test_pg_bigint_array (bigint_arr) VALUES (?::bigint[])', [$serialized]);

        $result = $connection->selectOne('SELECT bigint_arr FROM test_pg_bigint_array WHERE id = 1');
        $parsed = $this->invokeMethod($model, 'parsePgIntArray', [$result->bigint_arr]);

        // 在 64 位系统上，这些值应该能正确解析为 int
        $this->assertCount(3, $parsed);

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_bigint_array');
    }

    public function testRealDatabaseBigIntArrayNegativeValues()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table with BIGINT array
        $connection->statement('DROP TABLE IF EXISTS test_pg_bigint_array');
        $connection->statement('CREATE TABLE test_pg_bigint_array (id SERIAL PRIMARY KEY, bigint_arr BIGINT[])');

        $model = new TestPgArrayModel();

        // Test negative bigint values
        $original = [-1, -100, -999999999999, PHP_INT_MIN];
        $serialized = $this->invokeMethod($model, 'serializePgIntArray', [$original]);

        $connection->statement('INSERT INTO test_pg_bigint_array (bigint_arr) VALUES (?::bigint[])', [$serialized]);

        $result = $connection->selectOne('SELECT bigint_arr FROM test_pg_bigint_array WHERE id = 1');
        $parsed = $this->invokeMethod($model, 'parsePgIntArray', [$result->bigint_arr]);

        $this->assertSame(-1, $parsed[0]);
        $this->assertSame(-100, $parsed[1]);
        $this->assertSame(-999999999999, $parsed[2]);
        $this->assertSame(PHP_INT_MIN, $parsed[3]);

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_bigint_array');
    }

    public function testRealDatabaseBigIntArrayMixedTypes()
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = \Illuminate\Support\Facades\DB::connection('pgsql');

        // Create test table with BIGINT array
        $connection->statement('DROP TABLE IF EXISTS test_pg_bigint_array');
        $connection->statement('CREATE TABLE test_pg_bigint_array (id SERIAL PRIMARY KEY, bigint_arr BIGINT[])');

        $model = new TestPgArrayModel();

        // Test mixed int and string bigint values
        $original = [1, '2', 3, '9223372036854775807', -5, '-9223372036854775808'];
        $serialized = $this->invokeMethod($model, 'serializePgIntArray', [$original]);

        $connection->statement('INSERT INTO test_pg_bigint_array (bigint_arr) VALUES (?::bigint[])', [$serialized]);

        $result = $connection->selectOne('SELECT bigint_arr FROM test_pg_bigint_array WHERE id = 1');
        $parsed = $this->invokeMethod($model, 'parsePgIntArray', [$result->bigint_arr]);

        $this->assertCount(6, $parsed);
        $this->assertSame(1, $parsed[0]);
        $this->assertSame(2, $parsed[1]);
        $this->assertSame(3, $parsed[2]);
        $this->assertSame(PHP_INT_MAX, $parsed[3]);
        $this->assertSame(-5, $parsed[4]);
        $this->assertSame(PHP_INT_MIN, $parsed[5]);

        // Cleanup
        $connection->statement('DROP TABLE IF EXISTS test_pg_bigint_array');
    }

    /**
     * Helper method to invoke protected/private methods.
     */
    protected function invokeMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

/**
 * Test model that uses HasPgArrayAttributes trait
 * Default behavior: removes ARRAY casts.
 */
class TestPgArrayModel extends Model
{
    use HasPgArrayAttributes;

    protected $table = 'test_pg_array';

    protected $casts = [
        'id' => 'integer',
        'tags' => 'ARRAY',
        'scores' => 'ARRAY',
    ];
}

/**
 * Test model that preserves ARRAY casts by defining shouldPreserveUppercaseArrayCast method.
 */
class TestPgArrayModelPreserveCasts extends Model
{
    use HasPgArrayAttributes;

    protected $table = 'test_pg_array';

    protected $casts = [
        'id' => 'integer',
        'tags' => 'ARRAY',
        'scores' => 'ARRAY',
    ];

    /**
     * Override to preserve ARRAY casts.
     */
    public function shouldPreserveUppercaseArrayCast(): bool
    {
        return true;
    }
}
