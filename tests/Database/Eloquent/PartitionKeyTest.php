<?php

namespace HughCube\Laravel\Knight\Tests\Database\Eloquent;

use HughCube\Laravel\Knight\Exceptions\OptimisticLockException;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PartitionKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_partition_key_models', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('name');
        });

        Schema::create('test_partition_key_lock_models', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('name');
            $table->unsignedBigInteger('data_version')->default(1);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_partition_key_models');
        Schema::dropIfExists('test_partition_key_lock_models');

        parent::tearDown();
    }

    // ===== 基础功能 =====

    public function test_create_model_with_partition_key()
    {
        $model = TestPartitionKeyModel::create(['tenant_id' => 100, 'name' => 'Test']);

        $this->assertEquals(100, $model->tenant_id);
        $this->assertEquals('Test', $model->name);

        $dbModel = TestPartitionKeyModel::find($model->id);
        $this->assertEquals(100, $dbModel->tenant_id);
    }

    public function test_update_non_partition_key_columns_succeeds()
    {
        $model = TestPartitionKeyModel::create(['tenant_id' => 100, 'name' => 'Original']);

        $model->name = 'Updated';
        $model->save();

        $dbModel = TestPartitionKeyModel::find($model->id);
        $this->assertEquals('Updated', $dbModel->name);
        $this->assertEquals(100, $dbModel->tenant_id);
    }

    // ===== setKeysForSaveQuery 分区键条件 =====

    public function test_save_query_includes_partition_key_condition()
    {
        $model = TestPartitionKeyModel::create(['tenant_id' => 100, 'name' => 'Test']);

        // 直接修改数据库中的 tenant_id，模拟分区键不匹配的场景
        // 由于 setKeysForSaveQuery 会加上 tenant_id 条件，WHERE 不匹配，更新影响 0 行
        TestPartitionKeyModel::where('id', $model->id)->update(['tenant_id' => 999]);

        $model->name = 'Attempt update';
        $model->save();

        // 由于 WHERE 包含 tenant_id=100 但数据库中已变为 999，更新不会生效
        $dbModel = TestPartitionKeyModel::find($model->id);
        $this->assertEquals(999, $dbModel->tenant_id);
        $this->assertEquals('Test', $dbModel->name); // 原值未变
    }

    public function test_delete_includes_partition_key_condition()
    {
        $model = TestPartitionKeyModel::create(['tenant_id' => 100, 'name' => 'Test']);

        // 直接修改数据库中的 tenant_id
        TestPartitionKeyModel::where('id', $model->id)->update(['tenant_id' => 999]);

        // delete 也走 setKeysForSaveQuery，WHERE 包含 tenant_id=100，不匹配，删除不生效
        $model->delete();

        $dbModel = TestPartitionKeyModel::find($model->id);
        $this->assertNotNull($dbModel);
    }

    // ===== partitionKeyColumns =====

    public function test_partition_key_columns()
    {
        $model = new TestPartitionKeyModel();
        $this->assertEquals(['tenant_id'], $model->partitionKeyColumns());
    }

    // ===== 与 OptimisticLock 共存 =====

    public function test_partition_key_and_optimistic_lock_coexist()
    {
        $model = TestPartitionKeyWithOptimisticLockModel::create([
            'tenant_id' => 100,
            'name' => 'Test',
        ]);

        $this->assertEquals(1, $model->data_version);

        // 正常更新：分区键 + 乐观锁都工作
        $model->name = 'Updated';
        $model->save();
        $this->assertEquals(2, $model->data_version);

        $dbModel = TestPartitionKeyWithOptimisticLockModel::find($model->id);
        $this->assertEquals('Updated', $dbModel->name);
        $this->assertEquals(2, $dbModel->data_version);
    }

    public function test_optimistic_lock_works_with_partition_key()
    {
        $model = TestPartitionKeyWithOptimisticLockModel::create([
            'tenant_id' => 100,
            'name' => 'Test',
        ]);

        // 模拟版本冲突
        TestPartitionKeyWithOptimisticLockModel::where('id', $model->id)
            ->update(['data_version' => 99]);

        $model->name = 'Attempt';

        $this->expectException(OptimisticLockException::class);
        $model->useOptimisticLockSave();
    }
}
