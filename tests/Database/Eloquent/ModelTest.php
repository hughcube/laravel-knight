<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/5
 * Time: 2:49 下午.
 */

namespace HughCube\Laravel\Knight\Tests\Database\Eloquent;

use Exception;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModelTest extends TestCase
{
    protected $cacheForgetCount = 0;

    protected $cacheMissedCount = 0;

    protected $cacheHitCount = 0;

    protected $cachePutCount = 0;

    protected $databaseQueryCount = 0;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned()->comment('id');
            $table->string('nickname')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->listenCacheEvent(KeyForgotten::class, function () {
            $this->cacheForgetCount++;
        });

        $this->listenCacheEvent(CacheMissed::class, function () {
            $this->cacheMissedCount++;
        });

        $this->listenCacheEvent(CacheHit::class, function () {
            $this->cacheHitCount++;
        });

        $this->listenCacheEvent(KeyWritten::class, function () {
            $this->cachePutCount++;
        });

        DB::listen(function () {
            $this->databaseQueryCount++;
        });
    }

    protected function assertCacheForgetCount($value, $callable)
    {
        $count = $this->cacheForgetCount;
        $results = $callable();
        $this->assertSame($count + $value, $this->cacheForgetCount);

        return $results;
    }

    protected function assertCacheMissedCount($value, $callable)
    {
        $count = $this->cacheMissedCount;
        $results = $callable();
        $this->assertSame($count + $value, $this->cacheMissedCount);

        return $results;
    }

    protected function assertCacheHitCount($value, $callable)
    {
        $count = $this->cacheHitCount;
        $results = $callable();
        $this->assertSame($count + $value, $this->cacheHitCount);

        return $results;
    }

    protected function assertCachePutCount($value, $callable)
    {
        $count = $this->cachePutCount;
        $results = $callable();
        $this->assertSame($count + $value, $this->cachePutCount);

        return $results;
    }

    protected function assertCacheMissedAndPutCount($value, $callable)
    {
        return $this->assertCachePutCount($value, function () use ($value, $callable) {
            return $this->assertCacheMissedCount($value, $callable);
        });
    }

    protected function assertDatabaseQueryCount($value, $callable)
    {
        $count = $this->databaseQueryCount;
        $results = $callable();
        $this->assertSame($count + $value, $this->databaseQueryCount);

        return $results;
    }

    protected function listenCacheEvent($events, $listener = null)
    {
        $dispatcher = app(Dispatcher::class);

        if ($dispatcher instanceof Dispatcher) {
            $dispatcher->listen($events, $listener);
        }
    }

    protected function resetTable()
    {
        User::query()->truncate();
        User::query()->getCache()->clear();
    }

    /**
     * @param  int  $count
     * @return void
     *
     * @throws Exception
     */
    protected function createUsers(int $count = 1)
    {
        for ($i = 1; $i <= $count; $i++) {
            $user = new User();
            $user->nickname = md5(random_bytes(1000));
            $user->save();
        }
    }

    public function testIsMatchPk()
    {
        $this->resetTable();

        $this->assertTrue(! User::isMatchPk(0));
        $this->assertTrue(! User::isMatchPk(null));
        $this->assertTrue(! User::isMatchPk(''));

        $this->assertTrue(User::isMatchPk(1));
        $this->assertTrue(User::isMatchPk('1'));

        $this->assertDatabaseQueryCount(0, function () {
            $this->assertCacheHitCount(0, function () {
                $this->assertCacheForgetCount(0, function () {
                    $this->assertCacheMissedAndPutCount(0, function () {
                        User::findByIds([0]);
                        User::findByIds([null, 0, '']);
                    });
                });
            });
        });

        $this->assertDatabaseQueryCount(1, function () {
            $this->assertCacheMissedAndPutCount(1, function () {
                User::findByIds([null, 0, 1]);
            });
        });
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testCacheOnCreate()
    {
        $this->resetTable();

        /** Miss */
        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertNull($user);
        });

        /** hit */
        $this->assertCacheHitCount(1, function () {
            $user = User::findById(1);
            $this->assertNull($user);
        });

        $this->createUsers();

        /** miss */
        $this->assertCacheMissedAndPutCount(2, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
            $this->assertFalse($user->isFromCache());

            $user = User::findById(1);
            $this->assertTrue($user->isFromCache());

            sleep(11);
            $user = User::findById(1);
            $this->assertFalse($user->isFromCache());
        });

        /** hit */
        $this->assertCacheHitCount(1, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
            $this->assertTrue($user->isFromCache());
        });

        /** hit */
        $this->assertCacheHitCount(3, function () {
            $user = User::findById(1);
            $this->assertTrue($user->isFromCache());

            $user->deleteRowCache();
            $user = User::findById(1);
            $this->assertFalse($user->isFromCache());

            $user = User::query()->findUniqueRow(['nickname' => $user->nickname]);
            $this->assertFalse($user->isFromCache());

            $user->resetRowCache();

            $user = User::findById(1);
            $this->assertTrue($user->isFromCache());

            $user = User::query()->findUniqueRow(['nickname' => $user->nickname]);
            $this->assertTrue($user->isFromCache());

            sleep(11);
            $user = User::findById(1);
            $this->assertFalse($user->isFromCache());
        });
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testCacheOnUpdate()
    {
        $this->resetTable();
        $this->createUsers();

        $nickname = Str::random();

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
        });

        $this->assertCacheForgetCount(1, function () use ($nickname) {
            $user = User::findById(1);
            $user->nickname = $nickname;
            $user->save();
        });

        $this->assertCacheMissedAndPutCount(1, function () use ($nickname) {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
            $this->assertFalse($user->isFromCache());
            $this->assertSame($user->nickname, $nickname);
        });

        $this->assertCacheHitCount(1, function () use ($nickname) {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
            $this->assertTrue($user->isFromCache());
            $this->assertSame($user->nickname, $nickname);
        });
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testCacheOnForceDelete()
    {
        $this->resetTable();
        $this->createUsers();

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
        });

        $this->assertCacheForgetCount(1, function () {
            $user = User::findById(1);
            $user->forceDelete();
        });

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertNull($user);
        });

        $this->assertSame(0, User::query()->count());
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testCacheOnDelete()
    {
        $this->resetTable();
        $this->createUsers();

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
        });

        $this->assertCacheForgetCount(1, function () {
            $user = User::findById(1);
            $user->delete();
        });

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertNull($user);
        });

        $this->assertCacheForgetCount(1, function () {
            $user = User::withTrashed()->whereKey(1)->first();
            $this->assertInstanceOf(User::class, $user);
            $user->nickname = Str::random();
            $user->save();
        });

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertNull($user);
        });

        $this->assertCacheForgetCount(1, function () {
            $user = User::withTrashed()->whereKey(1)->first();
            $this->assertInstanceOf(User::class, $user);
            $user->restore();
        });

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
            $this->assertFalse($user->isFromCache());
        });

        $this->assertCacheHitCount(1, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
            $this->assertTrue($user->isFromCache());
        });

        $this->assertSame(1, User::withTrashed()->count());
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testFindById()
    {
        $this->resetTable();

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertNull($user);
        });

        $this->assertCacheHitCount(1, function () {
            $user = User::findById(1);
            $this->assertNull($user);
        });

        $this->createUsers();

        $this->assertCacheMissedAndPutCount(1, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
        });

        $this->assertCacheHitCount(1, function () {
            $user = User::findById(1);
            $this->assertInstanceOf(User::class, $user);
        });
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testFindByIds()
    {
        $this->resetTable();
        $cacheIds = Collection::make();

        $this->assertDatabaseQueryCount(1, function () {
            $this->assertCacheMissedAndPutCount(100, function () {
                $users = User::findByIds(range(1, 100));
                $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $users);
                $this->assertTrue($users->isEmpty());
            });
        });

        $this->assertDatabaseQueryCount(0, function () {
            $this->assertCacheHitCount(100, function () {
                $users = User::findByIds(range(1, 100));
                $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $users);
                $this->assertTrue($users->isEmpty());
            });
        });

        $this->createUsers(100);

        $this->assertCacheMissedAndPutCount(20, function () use (&$cacheIds) {
            $ids = Collection::make([
                1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
                1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010,
            ])->shuffle()->shuffle()->values();

            $users = User::findByIds($ids);
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $users);

            /** 查询结果正确 */
            $this->assertSame(10, $users->count());

            /** isFromCache正确 */
            $users->each(function (User $user) {
                $this->assertFalse($user->isFromCache());
            });

            /** id排序正确 */
            $this->assertSame(
                $ids->filter(function ($item) {
                    return 1000 > $item;
                })->values()->toArray(),
                $users->keys()->values()->toArray()
            );

            $cacheIds = $cacheIds->merge($ids);
        });

        $this->assertCacheHitCount(10, function () use (&$cacheIds) {
            $this->assertCacheMissedAndPutCount(10, function () use (&$cacheIds) {
                $ids = Collection::make([
                    6, 7, 8, 9, 10, 1001, 1002, 1003, 1004, 1005,
                    11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
                ])->shuffle()->shuffle()->values();

                $users = User::findByIds($ids);
                $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $users);

                /** 查询结果正确 */
                $this->assertSame(15, $users->count());

                /** isFromCache正确 */
                $users->each(function (User $user) use ($cacheIds) {
                    $this->assertSame($cacheIds->contains($user->id), $user->isFromCache());
                });

                /** id排序正确 */
                $this->assertSame(
                    $ids->filter(function ($item) {
                        return 1000 > $item;
                    })->values()->toArray(),
                    $users->keys()->values()->toArray()
                );

                $cacheIds = $cacheIds->merge($ids);
            });
        });

        $this->assertDatabaseQueryCount(0, function () use ($cacheIds) {
            User::findByIds($cacheIds);
        });
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testConversionDateTime()
    {
        $this->resetTable();

        $user = new User();
        $user->nickname = md5(random_bytes(1000));
        $user->save();

        /** @var User $user */
        $user = User::withTrashed()->first();
        $this->assertInstanceOf(Carbon::class, $user->created_at);
        $this->assertInstanceOf(Carbon::class, $user->updated_at);
        $this->assertNull($user->deleted_at);

        $now = Carbon::now();
        $user->timestamps = false;
        $user->created_at = $now;
        $user->updated_at = $now;
        $user->deleted_at = $now;
        $user->save();

        /** @var User $user */
        $user = User::withTrashed()->first();
        $this->assertFalse($user->isAvailable());
        $this->assertTrue($user->isDeleted());

        /** @var User $user */
        $user = User::withTrashed()->first();
        $this->assertSame($user->created_at->getTimestamp(), $now->getTimestamp());
        $this->assertSame($user->updated_at->getTimestamp(), $now->getTimestamp());
        $this->assertSame($user->deleted_at->getTimestamp(), $now->getTimestamp());

        /** @var User $user */
        $user = User::withTrashed()->first();
        $format = 'Y-m-d H:i:s';
        $this->assertSame($user->formatCreatedAt($format), $now->format($format));
        $this->assertSame($user->formatUpdatedAt($format), $now->format($format));
        $this->assertSame($user->formatDeleteAt($format), $now->format($format));

        $user->created_at = null;
        $user->updated_at = null;
        $user->deleted_at = null;
        $user->save();
        /** @var User $user */
        $user = User::withTrashed()->first();
        $this->assertTrue($user->isAvailable());
        $this->assertFalse($user->isDeleted());

        $this->assertNull($user->created_at);
        $this->assertNull($user->updated_at);
        $this->assertNull($user->deleted_at);

        $this->assertNull($user->formatCreatedAt());
        $this->assertNull($user->formatCreatedAt());
        $this->assertNull($user->formatCreatedAt());
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testUniqueRows()
    {
        $this->resetTable();

        $condition = Collection::make();
        for ($i = 1; $i <= 100; $i++) {
            $user = new User();
            $user->nickname = sprintf('nickname-%s', $i);
            $user->save();

            $condition->add(['id' => $user->id, 'nickname' => $user->nickname]);
        }

        /** @var Collection<int, User> $rows */
        $rows = User::query()->findUniqueRows($condition);
        $this->assertSame($rows->count(), $condition->count());
        foreach ($rows as $row) {
            $this->assertFalse($row->isFromCache());
        }

        /** @var Collection<int, User> $rows */
        $rows = User::query()->findUniqueRows($condition);
        $this->assertSame($rows->count(), $condition->count());
        foreach ($rows as $row) {
            $this->assertTrue($row->isFromCache());
        }
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testQueryWhereLike()
    {
        $this->resetTable();

        $user = new User();
        $user->nickname = md5(random_bytes(1000));
        $user->save();

        $keyword = substr(substr($user->nickname, 10), -10);
        $queryUser = User::query()->whereEscapeLike('nickname', $keyword)->first();
        $this->assertSame($user->id, $queryUser->id);

        $keyword = md5(random_bytes(1000)).$user->nickname;
        $queryUser = User::query()->whereEscapeLike('nickname', $keyword)->first();
        $this->assertNull($queryUser);

        $keyword = substr($user->nickname, 0, 10);
        $this->assertTrue(Str::startsWith($user->nickname, $keyword));
        $queryUser = User::query()->whereEscapeLeftLike('nickname', $keyword)->first();
        $this->assertSame($user->id, $queryUser->id);

        $keyword = substr($user->nickname, -10);
        $this->assertTrue(Str::endsWith($user->nickname, $keyword));
        $queryUser = User::query()->whereEscapeRightLike('nickname', $keyword)->first();
        $this->assertSame($user->id, $queryUser->id);
    }

    public function testQueryCollection()
    {
        $this->resetTable();

        $users = User::query()->limit(10)->get();

        $this->assertInstanceOf(EloquentCollection::class, $users);
        $this->assertInstanceOf(EloquentCollection::class, User::findByIds($users->pluck('id')));
    }

    /**
     * @return void
     */
    public function testScopeAvailable()
    {
        $this->resetTable();

        // 创建3个用户
        for ($i = 1; $i <= 3; $i++) {
            $user = new User();
            $user->nickname = 'user'.$i;
            $user->sort = $i;
            $user->save();
        }

        // 删除第2个用户
        $user2 = User::query()->where('nickname', 'user2')->first();
        $user2->delete();

        // 测试 scopeAvailable - 只返回未删除的记录
        $availableUsers = User::available()->get();
        $this->assertSame(2, $availableUsers->count());
        $this->assertSame(['user1', 'user3'], $availableUsers->pluck('nickname')->toArray());

        // 测试链式调用
        $availableUsers = User::query()->available()->get();
        $this->assertSame(2, $availableUsers->count());

        // 测试 availableQuery (deprecated) 应该返回相同结果
        $availableUsersDeprecated = User::availableQuery()->get();
        $this->assertSame(2, $availableUsersDeprecated->count());
        $this->assertSame(['user1', 'user3'], $availableUsersDeprecated->pluck('nickname')->toArray());
    }

    /**
     * @return void
     */
    public function testScopeSort()
    {
        $this->resetTable();

        // 创建用户并设置不同的 sort 值
        $sortValues = [3, 1, 5, 2, 4];
        foreach ($sortValues as $index => $sortValue) {
            $user = new User();
            $user->nickname = 'user'.($index + 1);
            $user->sort = $sortValue;
            $user->save();
        }

        // 测试 scopeSort - 按 sort 降序, 然后按 id 降序
        $sortedUsers = User::sort()->get();
        $this->assertSame(5, $sortedUsers->count());
        // sort: 5, 4, 3, 2, 1
        $this->assertEquals([5, 4, 3, 2, 1], $sortedUsers->pluck('sort')->toArray());

        // 测试链式调用
        $sortedUsers = User::query()->sort()->get();
        $this->assertEquals([5, 4, 3, 2, 1], $sortedUsers->pluck('sort')->toArray());

        // 测试 sortQuery (deprecated) 应该返回相同结果
        $sortedUsersDeprecated = User::sortQuery()->get();
        $this->assertEquals([5, 4, 3, 2, 1], $sortedUsersDeprecated->pluck('sort')->toArray());
    }

    /**
     * @return void
     */
    public function testScopeSortWithSameSort()
    {
        $this->resetTable();

        // 创建用户，相同的 sort 值
        for ($i = 1; $i <= 3; $i++) {
            $user = new User();
            $user->nickname = 'user'.$i;
            $user->sort = 10; // 相同的 sort 值
            $user->save();
        }

        // 测试相同 sort 值时按 id 降序
        $sortedUsers = User::sort()->get();
        $this->assertSame(3, $sortedUsers->count());
        // id: 3, 2, 1 (按 id 降序)
        $this->assertEquals([3, 2, 1], $sortedUsers->pluck('id')->toArray());
    }

    /**
     * @return void
     */
    public function testScopeSortAvailable()
    {
        $this->resetTable();

        // 创建5个用户
        $sortValues = [3, 1, 5, 2, 4];
        foreach ($sortValues as $index => $sortValue) {
            $user = new User();
            $user->nickname = 'user'.($index + 1);
            $user->sort = $sortValue;
            $user->save();
        }

        // 删除 sort=5 和 sort=2 的用户
        /** @var User $user */
        foreach (User::query()->whereIn('sort', [5, 2])->get() as $user) {
            $user->delete();
        }

        // 测试 scopeSortAvailable - 只返回未删除的记录，并按 sort 降序
        $users = User::sortAvailable()->get();
        $this->assertSame(3, $users->count());
        // 未删除的 sort 值: 4, 3, 1 (降序)
        $this->assertEquals([4, 3, 1], $users->pluck('sort')->toArray());

        // 测试链式调用
        $users = User::query()->sortAvailable()->get();
        $this->assertEquals([4, 3, 1], $users->pluck('sort')->toArray());

        // 测试 sortAvailableQuery (deprecated) 应该返回相同结果
        $usersDeprecated = User::sortAvailableQuery()->get();
        $this->assertEquals([4, 3, 1], $usersDeprecated->pluck('sort')->toArray());
    }

    /**
     * @return void
     */
    public function testScopeCombination()
    {
        $this->resetTable();

        // 创建用户
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->nickname = 'user'.$i;
            $user->sort = $i;
            $user->save();
        }

        // 删除第3个用户
        User::query()->where('sort', 3)->first()->delete();

        // 测试 available()->sort() 组合调用
        $users = User::available()->sort()->get();
        $this->assertSame(4, $users->count());
        $this->assertEquals([5, 4, 2, 1], $users->pluck('sort')->toArray());

        // 测试 sort()->available() 组合调用 (顺序不影响结果)
        $users = User::sort()->available()->get();
        $this->assertSame(4, $users->count());
    }
}
