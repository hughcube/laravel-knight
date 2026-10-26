<?php

namespace HughCube\Laravel\Knight\Tests\Database\Eloquent;

use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\SoftDeletes;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Psr\SimpleCache\CacheInterface;

class IsEqualAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('equal_test_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->default('');
            $table->string('email')->default('');
            $table->integer('age')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->text('json_data')->nullable();
            $table->text('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('equal_test_models');

        parent::tearDown();
    }

    // ===================== 基础行为 =====================

    /**
     * null 参数应返回 false.
     */
    public function testNullReturnsFalse()
    {
        $model = new EqualTestModel();
        $model->setRawAttributes(['id' => 1, 'name' => 'test']);

        $this->assertFalse($model->hasSameAttributes(null));
    }

    /**
     * 非 Model 对象应返回 false.
     */
    public function testNonModelReturnsFalse()
    {
        $model = new EqualTestModel();
        $model->setRawAttributes(['id' => 1, 'name' => 'test']);

        $this->assertFalse($model->hasSameAttributes('string'));
        $this->assertFalse($model->hasSameAttributes(123));
        $this->assertFalse($model->hasSameAttributes(new \stdClass()));
    }

    /**
     * 不同类的 Model 应返回 false (is() 检查表名和连接).
     */
    public function testDifferentModelClassReturnsFalse()
    {
        $model = new EqualTestModel();
        $model->setRawAttributes(['id' => 1, 'name' => 'test']);

        $other = new User();
        $other->setRawAttributes(['id' => 1, 'nickname' => 'test']);

        $this->assertFalse($model->hasSameAttributes($other));
    }

    /**
     * 不同主键应返回 false.
     */
    public function testDifferentPrimaryKeyReturnsFalse()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'test']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 2, 'name' => 'test']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * 未持久化模型(主键均为 null)不应被判定为同一条记录.
     */
    public function testUnsavedModelsReturnFalse()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['name' => 'test']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['name' => 'test']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * 完全相同的属性应返回 true.
     */
    public function testIdenticalAttributesReturnsTrue()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'test', 'age' => 25]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 'test', 'age' => 25]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 属性数量不同应返回 false.
     */
    public function testDifferentAttributeCountReturnsFalse()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'test', 'age' => 25]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 'test']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * deprecated 的 equal() 方法应与 hasSameAttributes() 行为一致.
     */
    public function testEqualIsAliasForIsEqualAttributes()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'test']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 'test']);

        $this->assertSame($a->equal($b), $a->hasSameAttributes($b));

        $b->setRawAttributes(['id' => 1, 'name' => 'other']);
        $this->assertSame($a->equal($b), $a->hasSameAttributes($b));
    }

    // ===================== 数值类型 =====================

    /**
     * int vs string 的数值比较: "1" === 1 应视为相等.
     */
    public function testNumericIntVsString()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'test', 'age' => 25]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 'test', 'age' => '25']);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 不同数值应返回 false.
     */
    public function testDifferentNumericValues()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'test', 'age' => 25]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 'test', 'age' => 30]);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * int vs 科学计数法字符串不应视为相等.
     */
    public function testScientificNotationStringVsIntNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'age' => 1]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'age' => '1e0']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * int vs 0e... 字符串不应视为相等.
     */
    public function testZeroExponentialStringVsIntNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'age' => 0]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'age' => '0e12345']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * int vs 超出整型范围字符串不应误判相等.
     */
    public function testIntMaxPlusOneStringNotEqual()
    {
        $intMaxString = (string) PHP_INT_MAX;
        $digits = str_split($intMaxString);
        for ($i = count($digits) - 1; $i >= 0; $i--) {
            if ('9' !== $digits[$i]) {
                $digits[$i] = (string) ((int) $digits[$i] + 1);
                break;
            }

            $digits[$i] = '0';
            if (0 === $i) {
                array_unshift($digits, '1');
            }
        }

        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'age' => PHP_INT_MAX]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'age' => implode('', $digits)]);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * float vs string: 99.99 !== "99.99", 只归一化 int 不归一化 float, 避免大数精度丢失.
     */
    public function testFloatVsStringNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'price' => 99.99]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'price' => '99.99']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * 同类型 float 相同值应相等.
     */
    public function testSameFloatEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'price' => 99.99]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'price' => 99.99]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 主键 int vs string: id=1 和 id="1" 应视为相等.
     */
    public function testPrimaryKeyIntVsString()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'test']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => '1', 'name' => 'test']);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    // ===================== 日期时间类型 =====================

    /**
     * 两个不同 Carbon 实例但相同时间应视为相等.
     */
    public function testCarbonInstancesWithSameTime()
    {
        $time = '2025-06-15 12:30:00';

        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'created_at' => Carbon::parse($time)]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'created_at' => Carbon::parse($time)]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * Carbon 对象 vs 时间字符串应视为相等.
     */
    public function testCarbonVsDatetimeString()
    {
        $time = '2025-06-15 12:30:00';

        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'created_at' => Carbon::parse($time)]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'created_at' => $time]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 字符串 vs Carbon 对象 (反向).
     */
    public function testDatetimeStringVsCarbon()
    {
        $time = '2025-06-15 12:30:00';

        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'created_at' => $time]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'created_at' => Carbon::parse($time)]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 不同时间应返回 false.
     */
    public function testDifferentDatetimeReturnsFalse()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'created_at' => '2025-06-15 12:30:00']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'created_at' => '2025-06-15 12:30:01']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * 两个不同 Carbon 实例但不同时间应返回 false.
     */
    public function testDifferentCarbonInstancesReturnFalse()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'created_at' => Carbon::parse('2025-01-01')]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'created_at' => Carbon::parse('2025-01-02')]);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * null 时间 vs null 应相等.
     */
    public function testNullDatetimeEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'deleted_at' => null]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'deleted_at' => null]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * null vs Carbon 应不相等.
     */
    public function testNullVsCarbonNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'deleted_at' => null]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'deleted_at' => Carbon::now()]);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    // ===================== 字符串类型 =====================

    /**
     * 相同字符串应相等.
     */
    public function testSameStringsEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'hello']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 'hello']);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 不同字符串应不相等.
     */
    public function testDifferentStringsNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'hello']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 'world']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * 空字符串 vs null 应不相等.
     */
    public function testEmptyStringVsNullNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => '']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => null]);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    // ===================== JSON / 数组类型 =====================

    /**
     * 相同 JSON 字符串应相等.
     */
    public function testSameJsonStringsEqual()
    {
        $json = '{"key":"value","count":5}';

        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'json_data' => $json]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'json_data' => $json]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 不同 JSON 字符串应不相等.
     */
    public function testDifferentJsonStringsNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'json_data' => '{"key":"value1"}']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'json_data' => '{"key":"value2"}']);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    /**
     * 相同数组应相等 (array 类型属性值).
     */
    public function testSameArraysEqual()
    {
        $arr = ['a', 'b', 'c'];

        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'tags' => $arr]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'tags' => $arr]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 不同数组应不相等.
     */
    public function testDifferentArraysNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'tags' => ['a', 'b']]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'tags' => ['a', 'c']]);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    // ===================== Boolean 类型 =====================

    /**
     * true vs 1: 非数值的 bool 比较不应误判.
     */
    public function testBoolVsIntNotMixed()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'is_active' => true]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'is_active' => true]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * true vs false 应不相等.
     */
    public function testDifferentBoolNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'is_active' => true]);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'is_active' => false]);

        $this->assertFalse($a->hasSameAttributes($b));
    }

    // ===================== DB 实际读写场景 =====================

    /**
     * 从 DB 加载的两个相同记录应 equal.
     */
    public function testTwoDbLoadsEqual()
    {
        EqualTestModel::create(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 30, 'price' => 99.99]);

        $a = EqualTestModel::noCacheQuery()->first();
        $b = EqualTestModel::noCacheQuery()->first();

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * DB 加载 vs 缓存加载 (经过 resetRowCache) 应 equal.
     */
    public function testDbVsCacheLoadEqual()
    {
        $model = EqualTestModel::create(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 30, 'price' => 99.99]);

        // 从 DB 加载, 写入缓存
        $dbModel = EqualTestModel::noCacheQuery()->find($model->id);
        $dbModel->deleteRowCache();
        $dbModel->resetRowCache();

        // 从缓存加载
        $cacheModel = EqualTestModel::findById($model->id);

        $this->assertTrue($dbModel->hasSameAttributes($cacheModel));
    }

    /**
     * DB 修改后, DB 记录与旧缓存应 not equal.
     */
    public function testDbModifiedVsOldCacheNotEqual()
    {
        $model = EqualTestModel::create(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 30]);

        // 建立缓存
        $dbModel = EqualTestModel::noCacheQuery()->find($model->id);
        $dbModel->deleteRowCache();
        $dbModel->resetRowCache();

        // 修改 DB
        EqualTestModel::noCacheQuery()->where('id', $model->id)->update(['name' => 'Bob']);

        // 重新从 DB 加载
        $freshModel = EqualTestModel::noCacheQuery()->find($model->id);
        // 从缓存加载 (旧数据)
        $cacheModel = EqualTestModel::findById($model->id);

        $this->assertFalse($freshModel->hasSameAttributes($cacheModel));
    }

    /**
     * 包含时间戳的记录 DB vs 缓存应 equal.
     */
    public function testTimestampFieldsDbVsCache()
    {
        $model = EqualTestModel::create([
            'name' => 'Alice',
            'email' => 'alice@test.com',
            'age' => 25,
        ]);

        // 确保 created_at/updated_at 有值
        $this->assertNotNull($model->created_at);
        $this->assertNotNull($model->updated_at);

        $dbModel = EqualTestModel::noCacheQuery()->find($model->id);
        $dbModel->deleteRowCache();
        $dbModel->resetRowCache();

        $cacheModel = EqualTestModel::findById($model->id);

        $this->assertTrue($dbModel->hasSameAttributes($cacheModel));
    }

    /**
     * 包含 null 字段的记录 DB vs 缓存应 equal.
     */
    public function testNullableFieldsDbVsCache()
    {
        $model = EqualTestModel::create([
            'name' => 'Alice',
            'email' => 'alice@test.com',
            'json_data' => null,
            'tags' => null,
        ]);

        $dbModel = EqualTestModel::noCacheQuery()->find($model->id);
        $dbModel->deleteRowCache();
        $dbModel->resetRowCache();

        $cacheModel = EqualTestModel::findById($model->id);

        $this->assertTrue($dbModel->hasSameAttributes($cacheModel));
    }

    /**
     * 含 JSON 字符串的记录 DB vs 缓存应 equal.
     */
    public function testJsonStringFieldDbVsCache()
    {
        $model = EqualTestModel::create([
            'name' => 'Alice',
            'email' => 'alice@test.com',
            'json_data' => '{"role":"admin","permissions":["read","write"]}',
        ]);

        $dbModel = EqualTestModel::noCacheQuery()->find($model->id);
        $dbModel->deleteRowCache();
        $dbModel->resetRowCache();

        $cacheModel = EqualTestModel::findById($model->id);

        $this->assertTrue($dbModel->hasSameAttributes($cacheModel));
    }

    /**
     * 多条记录逐一比较, 全部应 equal.
     */
    public function testBatchDbVsCacheEqual()
    {
        for ($i = 1; $i <= 5; $i++) {
            EqualTestModel::create([
                'name' => "User{$i}",
                'email' => "user{$i}@test.com",
                'age' => 20 + $i,
                'price' => 10.50 + $i,
            ]);
        }

        $models = EqualTestModel::noCacheQuery()->get();
        foreach ($models as $model) {
            $model->deleteRowCache();
            $model->resetRowCache();
        }

        foreach ($models as $model) {
            $cacheModel = EqualTestModel::findById($model->id);
            $this->assertTrue($model->hasSameAttributes($cacheModel), "Model ID {$model->id} DB vs Cache 不一致");
        }
    }

    // ===================== isEqualAttributeValue 边界 =====================

    /**
     * int vs string 的宽松匹配: PDO 返回 "1" vs 代码赋值 1.
     */
    public function testIntVsStringLooseMatch()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => '1', 'name' => 'test', 'age' => '25']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 'test', 'age' => 25]);

        $this->assertTrue($a->hasSameAttributes($b));
    }

    /**
     * 非数值的 string vs int 不应误判为相等.
     */
    public function testStringVsIntWithNonNumericNotEqual()
    {
        $a = new EqualTestModel();
        $a->setRawAttributes(['id' => 1, 'name' => 'abc']);

        $b = new EqualTestModel();
        $b->setRawAttributes(['id' => 1, 'name' => 123]);

        $this->assertFalse($a->hasSameAttributes($b));
    }
}

class EqualTestModel extends Model
{
    use SoftDeletes;

    protected $table = 'equal_test_models';

    protected $fillable = ['name', 'email', 'age', 'price', 'json_data', 'tags', 'is_active'];

    public function getCache(): CacheInterface
    {
        return Cache::store('array');
    }

    public function getCacheTtl(?int $duration = null): int
    {
        return 60;
    }

    public function getCachePlaceholder(): ?string
    {
        return md5(__METHOD__);
    }

    public function onChangeRefreshCacheKeys(): array
    {
        return [
            [$this->getKeyName() => $this->getKey()],
        ];
    }
}
