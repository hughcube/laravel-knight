<?php

namespace HughCube\Laravel\Knight\Tests\Database\Wal;

use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Wal\WalChangeRecord;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WalChangeRecordTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        Schema::dropIfExists('wal_record_test_items');
        Schema::create('wal_record_test_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->default('');
            $table->string('email')->default('');
            $table->timestamps();
        });
    }

    // ==================== Constructor & Getters ====================

    public function testConstructorAndGetters()
    {
        $record = new WalChangeRecord(
            'insert',
            'users',
            'users_2024',
            42,
            'id',
            ['id' => 42, 'name' => 'John'],
            [],
            WalRecordTestModel::class
        );

        $this->assertSame('insert', $record->getKind());
        $this->assertSame('users', $record->getTable());
        $this->assertSame('users_2024', $record->getPartitionTable());
        $this->assertSame(42, $record->getId());
        $this->assertSame('id', $record->getKeyName());
        $this->assertSame(['id' => 42, 'name' => 'John'], $record->getColumns());
        $this->assertSame([], $record->getOldColumns());
        $this->assertSame(WalRecordTestModel::class, $record->getModelClass());
    }

    public function testWithStringId()
    {
        $record = new WalChangeRecord(
            'insert',
            'items',
            null,
            'abc-def-123',
            'uuid',
            ['uuid' => 'abc-def-123', 'name' => 'Widget'],
            [],
            'stdClass'
        );

        $this->assertSame('abc-def-123', $record->getId());
        $this->assertSame('uuid', $record->getKeyName());
    }

    public function testWithNullPartitionTable()
    {
        $record = new WalChangeRecord('insert', 'users', null, 1, 'id', [], [], 'stdClass');

        $this->assertNull($record->getPartitionTable());
    }

    // ==================== Kind Helpers ====================

    public function testIsInsert()
    {
        $record = new WalChangeRecord('insert', 'users', null, 1, 'id', [], [], 'stdClass');

        $this->assertTrue($record->isInsert());
        $this->assertFalse($record->isUpdate());
        $this->assertFalse($record->isDelete());
    }

    public function testIsUpdate()
    {
        $record = new WalChangeRecord('update', 'users', null, 1, 'id', [], [], 'stdClass');

        $this->assertFalse($record->isInsert());
        $this->assertTrue($record->isUpdate());
        $this->assertFalse($record->isDelete());
    }

    public function testIsDelete()
    {
        $record = new WalChangeRecord('delete', 'users', null, 1, 'id', [], [], 'stdClass');

        $this->assertFalse($record->isInsert());
        $this->assertFalse($record->isUpdate());
        $this->assertTrue($record->isDelete());
    }

    // ==================== isFromPartition ====================

    public function testIsFromPartitionTrue()
    {
        $record = new WalChangeRecord('insert', 'orders', 'orders_2024_01', 1, 'id', [], [], 'stdClass');

        $this->assertTrue($record->isFromPartition());
    }

    public function testIsFromPartitionFalse()
    {
        $record = new WalChangeRecord('insert', 'orders', null, 1, 'id', [], [], 'stdClass');

        $this->assertFalse($record->isFromPartition());
    }

    // ==================== Constants ====================

    public function testKindConstants()
    {
        $this->assertSame('insert', WalChangeRecord::KIND_INSERT);
        $this->assertSame('update', WalChangeRecord::KIND_UPDATE);
        $this->assertSame('delete', WalChangeRecord::KIND_DELETE);
    }

    public function testInvalidKindThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid WAL change kind: truncate');

        new WalChangeRecord('truncate', 'users', null, 1, 'id', [], [], 'stdClass');
    }

    // ==================== fromWal2json ====================

    public function testFromWal2jsonInsert()
    {
        $change = [
            'kind'         => 'insert',
            'table'        => 'users',
            'columnnames'  => ['id', 'name', 'email'],
            'columnvalues' => [42, 'John', 'john@example.com'],
        ];

        $record = WalChangeRecord::fromWal2json($change, 'users', 'users', 'id', WalRecordTestModel::class);

        $this->assertNotNull($record);
        $this->assertTrue($record->isInsert());
        $this->assertSame(42, $record->getId());
        $this->assertSame('users', $record->getTable());
        $this->assertNull($record->getPartitionTable());
        $this->assertSame(['id' => 42, 'name' => 'John', 'email' => 'john@example.com'], $record->getColumns());
        $this->assertSame([], $record->getOldColumns());
    }

    public function testFromWal2jsonDelete()
    {
        $change = [
            'kind'    => 'delete',
            'table'   => 'users',
            'oldkeys' => [
                'keynames'  => ['id'],
                'keyvalues' => [15],
            ],
        ];

        $record = WalChangeRecord::fromWal2json($change, 'users', 'users', 'id', 'stdClass');

        $this->assertNotNull($record);
        $this->assertTrue($record->isDelete());
        $this->assertSame(15, $record->getId());
        $this->assertSame(['id' => 15], $record->getOldColumns());
    }

    public function testFromWal2jsonWithPartitionTable()
    {
        $change = [
            'kind'         => 'insert',
            'table'        => 'orders_p0',
            'columnnames'  => ['id', 'name'],
            'columnvalues' => [1, 'test'],
        ];

        $record = WalChangeRecord::fromWal2json($change, 'orders', 'orders_p0', 'id', 'stdClass');

        $this->assertNotNull($record);
        $this->assertSame('orders', $record->getTable());
        $this->assertSame('orders_p0', $record->getPartitionTable());
        $this->assertTrue($record->isFromPartition());
    }

    public function testFromWal2jsonReturnsNullWhenNoKind()
    {
        $change = ['table' => 'users', 'columnnames' => ['id'], 'columnvalues' => [1]];

        $this->assertNull(WalChangeRecord::fromWal2json($change, 'users', 'users', 'id', 'stdClass'));
    }

    public function testFromWal2jsonReturnsNullWhenKeyMissing()
    {
        $change = [
            'kind'         => 'insert',
            'table'        => 'users',
            'columnnames'  => ['name'],
            'columnvalues' => ['John'],
        ];

        $this->assertNull(WalChangeRecord::fromWal2json($change, 'users', 'users', 'id', 'stdClass'));
    }

    public function testFromWal2jsonWithStringUuid()
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $change = [
            'kind'         => 'insert',
            'table'        => 'items',
            'columnnames'  => ['uuid', 'name'],
            'columnvalues' => [$uuid, 'Widget'],
        ];

        $record = WalChangeRecord::fromWal2json($change, 'items', 'items', 'uuid', 'stdClass');

        $this->assertNotNull($record);
        $this->assertSame($uuid, $record->getId());
        $this->assertSame('uuid', $record->getKeyName());
    }

    public function testFromWal2jsonDeleteWithMissingOldkeys()
    {
        $change = ['kind' => 'delete', 'table' => 'users'];

        $this->assertNull(WalChangeRecord::fromWal2json($change, 'users', 'users', 'id', 'stdClass'));
    }

    public function testFromWal2jsonWithMismatchedColumnLengths()
    {
        $change = [
            'kind'         => 'insert',
            'table'        => 'items',
            'columnnames'  => ['id', 'name', 'extra'],
            'columnvalues' => [1, 'test'],
        ];

        // columnnames/columnvalues 长度不匹配 → columns 为空 → 无法提取主键 → null
        $this->assertNull(WalChangeRecord::fromWal2json($change, 'items', 'items', 'id', 'stdClass'));
    }

    // ==================== context ====================

    public function testContextSetAndGet()
    {
        $record = new WalChangeRecord('insert', 'users', null, 1, 'id', [], [], 'stdClass');

        $record->setContext('parent_ids', [10, 20, 30]);

        $this->assertSame([10, 20, 30], $record->getContext('parent_ids'));
    }

    public function testContextGetDefaultValue()
    {
        $record = new WalChangeRecord('insert', 'users', null, 1, 'id', [], [], 'stdClass');

        $this->assertNull($record->getContext('nonexistent'));
        $this->assertSame('fallback', $record->getContext('nonexistent', 'fallback'));
    }

    public function testContextIsNotSerialized()
    {
        $record = new WalChangeRecord('insert', 'users', null, 1, 'id', ['id' => 1], [], 'stdClass');
        $record->setContext('temp_data', 'should_not_survive');

        /** @var WalChangeRecord $unserialized */
        $unserialized = unserialize(serialize($record));

        $this->assertNull($unserialized->getContext('temp_data'));
        $this->assertSame(1, $unserialized->getId());
    }

    public function testContextSetReturnsSelf()
    {
        $record = new WalChangeRecord('insert', 'users', null, 1, 'id', [], [], 'stdClass');

        $result = $record->setContext('key', 'value');

        $this->assertSame($record, $result);
    }

    // ==================== toModel ====================

    public function testMakeModelForInsert()
    {
        $record = new WalChangeRecord(
            'insert',
            'wal_record_test_items',
            null,
            42,
            'id',
            ['id' => 42, 'name' => 'John', 'email' => 'john@example.com'],
            [],
            WalRecordTestModel::class
        );

        $model = $record->toModel();

        $this->assertInstanceOf(WalRecordTestModel::class, $model);
        $this->assertSame(42, $model->id);
        $this->assertSame('John', $model->name);
        $this->assertSame('john@example.com', $model->email);
        $this->assertTrue($model->exists);
    }

    public function testMakeModelForUpdate()
    {
        $record = new WalChangeRecord(
            'update',
            'wal_record_test_items',
            null,
            7,
            'id',
            ['id' => 7, 'name' => 'Jane', 'email' => 'jane@example.com'],
            [],
            WalRecordTestModel::class
        );

        $model = $record->toModel();

        $this->assertInstanceOf(WalRecordTestModel::class, $model);
        $this->assertSame(7, $model->id);
        $this->assertSame('Jane', $model->name);
        $this->assertTrue($model->exists);
    }

    public function testMakeModelForDelete()
    {
        $record = new WalChangeRecord(
            'delete',
            'wal_record_test_items',
            null,
            15,
            'id',
            [],
            ['id' => 15],
            WalRecordTestModel::class
        );

        $model = $record->toModel();

        $this->assertInstanceOf(WalRecordTestModel::class, $model);
        $this->assertSame(15, $model->id);
        $this->assertFalse($model->exists);
    }

    public function testMakeModelForDeleteWithFullReplicaIdentity()
    {
        $record = new WalChangeRecord(
            'delete',
            'wal_record_test_items',
            null,
            15,
            'id',
            [],
            ['id' => 15, 'name' => 'OldName', 'email' => 'old@example.com'],
            WalRecordTestModel::class
        );

        $model = $record->toModel();

        $this->assertSame(15, $model->id);
        $this->assertSame('OldName', $model->name);
        $this->assertSame('old@example.com', $model->email);
        $this->assertFalse($model->exists);
    }

    public function testMakeModelSyncsOriginal()
    {
        $record = new WalChangeRecord(
            'update',
            'wal_record_test_items',
            null,
            1,
            'id',
            ['id' => 1, 'name' => 'Test'],
            [],
            WalRecordTestModel::class
        );

        $model = $record->toModel();

        // After syncOriginal, getDirty should be empty
        $this->assertEmpty($model->getDirty());
    }

    // ==================== jsonSerialize ====================

    public function testJsonSerialize()
    {
        $record = new WalChangeRecord(
            'insert',
            'users',
            'users_2024',
            42,
            'id',
            ['id' => 42, 'name' => 'John'],
            [],
            WalRecordTestModel::class
        );

        $json = $record->jsonSerialize();

        $this->assertSame('insert', $json['kind']);
        $this->assertSame('users', $json['table']);
        $this->assertSame('users_2024', $json['partitionTable']);
        $this->assertSame(42, $json['id']);
        $this->assertSame('id', $json['keyName']);
        $this->assertSame(['id' => 42, 'name' => 'John'], $json['columns']);
        $this->assertSame([], $json['oldColumns']);
        $this->assertSame(WalRecordTestModel::class, $json['modelClass']);
    }

    public function testJsonEncodeProducesValidJson()
    {
        $record = new WalChangeRecord('update', 'orders', null, 7, 'id', ['id' => 7, 'status' => 'paid'], ['id' => 7, 'status' => 'pending'], 'stdClass');

        $encoded = json_encode($record);
        $this->assertNotFalse($encoded);

        $decoded = json_decode($encoded, true);
        $this->assertSame('update', $decoded['kind']);
        $this->assertSame(7, $decoded['id']);
        $this->assertNull($decoded['partitionTable']);
    }

    public function testJsonSerializeExcludesContext()
    {
        $record = new WalChangeRecord('insert', 'users', null, 1, 'id', ['id' => 1], [], 'stdClass');
        $record->setContext('temp', 'value');

        $json = $record->jsonSerialize();

        $this->assertArrayNotHasKey('context', $json);
    }

    public function testMakeModelSetsCorrectPrimaryKey()
    {
        $record = new WalChangeRecord(
            'insert',
            'wal_record_test_items',
            null,
            99,
            'id',
            ['id' => 99, 'name' => 'Test'],
            [],
            WalRecordTestModel::class
        );

        $model = $record->toModel();

        $this->assertSame(99, $model->getKey());
    }

    // ==================== fetchModel ====================

    public function testFindModelQueriesDbAndCachesResult()
    {
        /** 先插入一条记录 */
        $inserted = new WalRecordTestModel();
        $inserted->name = 'FindMe';
        $inserted->email = 'find@example.com';
        $inserted->save();

        $record = new WalChangeRecord(
            'update',
            'wal_record_test_items',
            null,
            $inserted->id,
            'id',
            ['id' => $inserted->id, 'name' => 'FindMe'],
            [],
            WalRecordTestModel::class
        );

        /** 第一次查询 DB */
        $model = $record->fetchModel();
        $this->assertInstanceOf(WalRecordTestModel::class, $model);
        $this->assertSame($inserted->id, $model->id);
        $this->assertSame('find@example.com', $model->email);

        /** 第二次走 context 缓存，返回同一个实例 */
        $model2 = $record->fetchModel();
        $this->assertSame($model, $model2);
    }

    public function testFindModelReturnsNullForDeletedRecord()
    {
        $record = new WalChangeRecord(
            'delete',
            'wal_record_test_items',
            null,
            999999,
            'id',
            [],
            ['id' => 999999],
            WalRecordTestModel::class
        );

        /** 记录不存在，返回 null */
        $this->assertNull($record->fetchModel());

        /** 再次调用不会重复查询（context 缓存了 false） */
        $this->assertNull($record->fetchModel());
    }

    public function testFindModelCacheNotSurviveSerialization()
    {
        $inserted = new WalRecordTestModel();
        $inserted->name = 'Serializable';
        $inserted->email = 'ser@example.com';
        $inserted->save();

        $record = new WalChangeRecord(
            'insert',
            'wal_record_test_items',
            null,
            $inserted->id,
            'id',
            ['id' => $inserted->id, 'name' => 'Serializable'],
            [],
            WalRecordTestModel::class
        );

        $record->fetchModel();

        /** 序列化后 context 被清除，fetchModel 会重新查询 */
        /** @var WalChangeRecord $unserialized */
        $unserialized = unserialize(serialize($record));
        $this->assertNull($unserialized->getContext('__kr_fm_9d7e4a1b3f6c82e5'));

        /** 但依然能正常查询到 */
        $model = $unserialized->fetchModel();
        $this->assertInstanceOf(WalRecordTestModel::class, $model);
        $this->assertSame($inserted->id, $model->id);
    }

    // ==================== setModel ====================

    public function testSetModelAllowsFindModelToReturnCached()
    {
        $record = new WalChangeRecord(
            'insert',
            'wal_record_test_items',
            null,
            1,
            'id',
            ['id' => 1, 'name' => 'Test'],
            [],
            WalRecordTestModel::class
        );

        $fakeModel = new WalRecordTestModel();
        $fakeModel->id = 1;
        $fakeModel->name = 'PreSet';

        $record->setModel($fakeModel);

        /** fetchModel 直接返回 setModel 设置的实例 */
        $result = $record->fetchModel();
        $this->assertSame($fakeModel, $result);
        $this->assertSame('PreSet', $result->name);
    }

    public function testSetModelNullCachesAsNotFound()
    {
        $record = new WalChangeRecord(
            'delete',
            'wal_record_test_items',
            null,
            1,
            'id',
            [],
            ['id' => 1],
            WalRecordTestModel::class
        );

        $record->setModel(null);

        /** fetchModel 返回 null 且不会查询 DB */
        $this->assertNull($record->fetchModel());
    }

    public function testSetModelReturnsSelf()
    {
        $record = new WalChangeRecord('insert', 'wal_record_test_items', null, 1, 'id', [], [], WalRecordTestModel::class);

        $result = $record->setModel(new WalRecordTestModel());

        $this->assertSame($record, $result);
    }

    // ==================== setFetcher ====================

    public function testSetFetcherCustomQuery()
    {
        $inserted = new WalRecordTestModel();
        $inserted->name = 'CustomFetch';
        $inserted->email = 'custom@example.com';
        $inserted->save();

        $record = new WalChangeRecord(
            'update',
            'wal_record_test_items',
            null,
            $inserted->id,
            'id',
            ['id' => $inserted->id, 'name' => 'CustomFetch'],
            [],
            WalRecordTestModel::class
        );

        $fetcherCalled = false;
        $record->setFetcher(function (WalChangeRecord $r) use (&$fetcherCalled) {
            $fetcherCalled = true;

            return WalRecordTestModel::query()->where('email', 'custom@example.com')->first();
        });

        $model = $record->fetchModel();

        $this->assertTrue($fetcherCalled);
        $this->assertInstanceOf(WalRecordTestModel::class, $model);
        $this->assertSame($inserted->id, $model->id);
        $this->assertSame('custom@example.com', $model->email);
    }

    public function testSetFetcherReturnsSelf()
    {
        $record = new WalChangeRecord('insert', 'wal_record_test_items', null, 1, 'id', [], [], WalRecordTestModel::class);

        $result = $record->setFetcher(function () {
            return null;
        });

        $this->assertSame($record, $result);
    }

    public function testSetFetcherResultIsCached()
    {
        $record = new WalChangeRecord(
            'insert',
            'wal_record_test_items',
            null,
            1,
            'id',
            ['id' => 1],
            [],
            WalRecordTestModel::class
        );

        $callCount = 0;
        $fakeModel = new WalRecordTestModel();
        $fakeModel->id = 1;

        $record->setFetcher(function () use (&$callCount, $fakeModel) {
            $callCount++;

            return $fakeModel;
        });

        $record->fetchModel();
        $record->fetchModel();

        $this->assertSame(1, $callCount);
    }

    public function testSetFetcherNotSurviveSerialization()
    {
        $inserted = new WalRecordTestModel();
        $inserted->name = 'SerFetcher';
        $inserted->email = 'serfetcher@example.com';
        $inserted->save();

        $record = new WalChangeRecord(
            'insert',
            'wal_record_test_items',
            null,
            $inserted->id,
            'id',
            ['id' => $inserted->id, 'name' => 'SerFetcher'],
            [],
            WalRecordTestModel::class
        );

        $fetcherCalled = false;
        $record->setFetcher(function () use (&$fetcherCalled) {
            $fetcherCalled = true;

            return null;
        });

        /** @var WalChangeRecord $unserialized */
        $unserialized = unserialize(serialize($record));

        /** 反序列化后 fetcher 丢失，走默认查询逻辑 */
        $model = $unserialized->fetchModel();
        $this->assertFalse($fetcherCalled);
        $this->assertInstanceOf(WalRecordTestModel::class, $model);
        $this->assertSame($inserted->id, $model->id);
    }
    // ==================== TOAST sentinel handling (v2) ====================

    public function testFromWal2jsonV2ToastUnchangedReplacedWithNull()
    {
        $change = [
            'action'  => 'U',
            'table'   => 'wal_record_test_items',
            'columns' => [
                ['name' => 'id', 'type' => 'integer', 'value' => 1],
                ['name' => 'name', 'type' => 'text', 'value' => 'updated'],
                ['name' => 'email', 'type' => 'text', 'value' => 'unchanged-toast-datum'],
            ],
            'identity' => [
                ['name' => 'id', 'type' => 'integer', 'value' => 1],
            ],
        ];

        $record = WalChangeRecord::fromWal2json($change, 'wal_record_test_items', 'wal_record_test_items', 'id', WalRecordTestModel::class);

        $this->assertNotNull($record);
        $columns = $record->getColumns();

        /** TOAST 列 key 应被移除（不存在于 columns 中） */
        $this->assertArrayNotHasKey('email', $columns);
        /** 正常值不受影响 */
        $this->assertSame('updated', $columns['name']);
        $this->assertSame(1, $columns['id']);
    }

    public function testToModelDoesNotContainToastSentinel()
    {
        $change = [
            'action'  => 'U',
            'table'   => 'wal_record_test_items',
            'columns' => [
                ['name' => 'id', 'type' => 'integer', 'value' => 42],
                ['name' => 'name', 'type' => 'text', 'value' => 'hello'],
                ['name' => 'email', 'type' => 'text', 'value' => 'unchanged-toast-datum'],
            ],
            'identity' => [
                ['name' => 'id', 'type' => 'integer', 'value' => 42],
            ],
        ];

        $record = WalChangeRecord::fromWal2json($change, 'wal_record_test_items', 'wal_record_test_items', 'id', WalRecordTestModel::class);
        $model = $record->toModel();

        /** Model 属性不应包含 TOAST 哨兵字符串 */
        $this->assertNotSame('unchanged-toast-datum', $model->email);
        $this->assertSame('hello', $model->name);
    }

    public function testIsToastUnchangedForMissingColumn()
    {
        /** 手动构建一个 columns 中缺少 email 的 record，模拟 TOAST 列被过滤 */
        $record = new WalChangeRecord(
            'update',
            'wal_record_test_items',
            null,
            1,
            'id',
            ['id' => 1, 'name' => 'updated'],
            ['id' => 1],
            WalRecordTestModel::class
        );

        $this->assertTrue($record->isToastUnchanged('email'));
        $this->assertFalse($record->isToastUnchanged('name'));
        $this->assertFalse($record->isToastUnchanged('id'));
    }

    public function testIsToastUnchangedViaFromWal2json()
    {
        /** 通过 fromWal2json 实际路径构建 record，验证 isToastUnchanged 正确性 */
        $change = [
            'action'  => 'U',
            'table'   => 'wal_record_test_items',
            'columns' => [
                ['name' => 'id', 'type' => 'integer', 'value' => 1],
                ['name' => 'name', 'type' => 'text', 'value' => 'updated'],
                ['name' => 'email', 'type' => 'text', 'value' => 'unchanged-toast-datum'],
            ],
            'identity' => [
                ['name' => 'id', 'type' => 'integer', 'value' => 1],
            ],
        ];

        $record = WalChangeRecord::fromWal2json($change, 'wal_record_test_items', 'wal_record_test_items', 'id', WalRecordTestModel::class);

        $this->assertNotNull($record);
        /** email 是 TOAST 未变更列 */
        $this->assertTrue($record->isToastUnchanged('email'));
        /** name 和 id 有实际值，不是 TOAST */
        $this->assertFalse($record->isToastUnchanged('name'));
        $this->assertFalse($record->isToastUnchanged('id'));
    }

    public function testToastConstantValue()
    {
        $this->assertSame('unchanged-toast-datum', WalChangeRecord::TOAST_UNCHANGED);
    }
}

/**
 * @property int    $id
 * @property string $name
 * @property string $email
 */
class WalRecordTestModel extends Model
{
    protected $table = 'wal_record_test_items';

    protected $fillable = ['id', 'name', 'email'];
}
