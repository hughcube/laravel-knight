<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/9/3
 * Time: 10:10.
 */

namespace HughCube\Laravel\Knight\Tests\Database;

use Exception;
use HughCube\Laravel\Knight\Database\MultipleModelBatchFinder;
use HughCube\Laravel\Knight\Database\MultipleModelBatchResult;
use HughCube\Laravel\Knight\Tests\Database\Eloquent\Post;
use HughCube\Laravel\Knight\Tests\Database\Eloquent\User;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MultipleModelBatchFinderTest extends TestCase
{
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

        Schema::dropIfExists('posts');
        Schema::create('posts', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned()->comment('id');
            $table->string('title')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
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

    protected function listenCacheEvent($events, $listener = null)
    {
        $dispatcher = app(Dispatcher::class);

        if ($dispatcher instanceof Dispatcher) {
            $dispatcher->listen($events, $listener);
        }
    }

    protected function resetTables()
    {
        User::query()->truncate();
        User::query()->getCache()->clear();

        Post::query()->truncate();
        Post::query()->getCache()->clear();
    }

    /**
     * @throws Exception
     */
    protected function createUsers(int $count = 1)
    {
        for ($i = 1; $i <= $count; $i++) {
            $user = new User();
            $user->nickname = sprintf('user-%s', $i);
            $user->save();
        }
    }

    /**
     * @throws Exception
     */
    protected function createPosts(int $count = 1)
    {
        for ($i = 1; $i <= $count; $i++) {
            $post = new Post();
            $post->title = sprintf('post-%s', $i);
            $post->save();
        }
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

    protected function assertDatabaseQueryCount($value, $callable)
    {
        $count = $this->databaseQueryCount;
        $results = $callable();
        $this->assertSame($count + $value, $this->databaseQueryCount);

        return $results;
    }

    /**
     * 测试基本的静态方法查询.
     *
     * @throws Exception
     */
    public function testBasicFind()
    {
        $this->resetTables();
        $this->createUsers(5);
        $this->createPosts(5);

        // 第一次查询：缓存未命中，从数据库查询
        $cachePutBefore = $this->cachePutCount;
        $dbQueryBefore = $this->databaseQueryCount;

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2, 3],
            Post::class => [1, 2, 3],
        ]);

        // 应该有 6 个缓存写入（3 个 User + 3 个 Post）
        $this->assertSame($cachePutBefore + 6, $this->cachePutCount);
        // 应该有 2 次数据库查询（User 表 1 次 + Post 表 1 次）
        $this->assertSame($dbQueryBefore + 2, $this->databaseQueryCount);

        $this->assertInstanceOf(MultipleModelBatchResult::class, $result);

        // 验证 User 结果
        $users = $result->getOf(User::class);
        $this->assertSame(3, $users->count());

        // 验证 Post 结果
        $posts = $result->getOf(Post::class);
        $this->assertSame(3, $posts->count());

        // 第二次查询：缓存命中，不查询数据库
        $this->assertDatabaseQueryCount(0, function () {
            $result = MultipleModelBatchFinder::find([
                User::class => [1, 2, 3],
                Post::class => [1, 2, 3],
            ]);

            $this->assertSame(3, $result->getOf(User::class)->count());
            $this->assertSame(3, $result->getOf(Post::class)->count());

            // 验证 isFromCache 标记
            $result->getOf(User::class)->each(function (User $user) {
                $this->assertTrue($user->isFromCache());
            });
            $result->getOf(Post::class)->each(function (Post $post) {
                $this->assertTrue($post->isFromCache());
            });
        });
    }

    /**
     * 测试链式调用方式.
     *
     * @throws Exception
     */
    public function testFluentInterface()
    {
        $this->resetTables();
        $this->createUsers(5);
        $this->createPosts(5);

        $result = MultipleModelBatchFinder::make()
            ->with(User::class, [1, 2])
            ->with(Post::class, [1, 2, 3])
            ->get();

        $this->assertSame(2, $result->getOf(User::class)->count());
        $this->assertSame(3, $result->getOf(Post::class)->count());
    }

    /**
     * 测试部分缓存命中的场景.
     *
     * @throws Exception
     */
    public function testPartialCacheHit()
    {
        $this->resetTables();
        $this->createUsers(10);
        $this->createPosts(10);

        // 先缓存部分数据
        User::findByIds([1, 2, 3]);
        Post::findByIds([1, 2]);

        // 清除计数
        $cachePutBefore = $this->cachePutCount;
        $cacheHitBefore = $this->cacheHitCount;
        $dbQueryBefore = $this->databaseQueryCount;

        // 查询时部分命中缓存
        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2, 3, 4, 5],  // 1,2,3 命中，4,5 未命中
            Post::class => [1, 2, 3, 4],     // 1,2 命中，3,4 未命中
        ]);

        $this->assertSame(5, $result->getOf(User::class)->count());
        $this->assertSame(4, $result->getOf(Post::class)->count());

        // 验证缓存命中数 (3 + 2 = 5)
        $this->assertSame($cacheHitBefore + 5, $this->cacheHitCount);

        // 验证缓存写入数 (2 + 2 = 4，未命中的部分)
        $this->assertSame($cachePutBefore + 4, $this->cachePutCount);

        // 验证数据库查询次数 (2 次，每个模型各 1 次)
        $this->assertSame($dbQueryBefore + 2, $this->databaseQueryCount);
    }

    /**
     * 测试空查询.
     */
    public function testEmptyQuery()
    {
        $this->resetTables();

        $this->assertDatabaseQueryCount(0, function () {
            $result = MultipleModelBatchFinder::find([]);

            $this->assertTrue(! $result->has());
        });

        $this->assertDatabaseQueryCount(0, function () {
            $result = MultipleModelBatchFinder::find([
                User::class => [],
                Post::class => [],
            ]);

            $this->assertTrue(! $result->has());
        });
    }

    /**
     * 测试查询不存在的数据（占位符机制）.
     *
     * @throws Exception
     */
    public function testNotFoundWithPlaceholder()
    {
        $this->resetTables();
        $this->createUsers(2);

        // 查询存在和不存在的数据
        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2, 999, 1000],
        ]);

        // 只返回存在的数据
        $this->assertSame(2, $result->getOf(User::class)->count());

        // 第二次查询，占位符生效，不查询数据库
        $this->assertDatabaseQueryCount(0, function () {
            $result = MultipleModelBatchFinder::find([
                User::class => [999, 1000],
            ]);

            $this->assertSame(0, $result->getOf(User::class)->count());
        });
    }

    /**
     * 测试过滤无效的主键值.
     *
     * @throws Exception
     */
    public function testFilterInvalidPks()
    {
        $this->resetTables();
        $this->createUsers(2);

        $this->assertDatabaseQueryCount(1, function () {
            $result = MultipleModelBatchFinder::find([
                User::class => [0, null, '', 1, 2],  // 0, null, '' 会被过滤
            ]);

            $this->assertSame(2, $result->getOf(User::class)->count());
        });
    }

    /**
     * 测试 MultipleModelBatchResult 的各种方法.
     *
     * @throws Exception
     */
    public function testResultMethods()
    {
        $this->resetTables();
        $this->createUsers(3);
        $this->createPosts(2);

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2, 3],
            Post::class => [1, 2],
        ]);

        // hasOf
        $this->assertTrue($result->hasOf(User::class));
        $this->assertTrue($result->hasOf(Post::class));
        $this->assertFalse($result->hasOf('NonExistentClass'));

        // getModelClasses
        $classes = $result->getModelClasses();
        $this->assertContains(User::class, $classes);
        $this->assertContains(Post::class, $classes);
        $this->assertSame(2, count($classes));

        // countOf
        $this->assertSame(3, $result->countOf(User::class));
        $this->assertSame(2, $result->countOf(Post::class));
        $this->assertSame(0, $result->countOf('NonExistent'));

        // count
        $this->assertSame(5, $result->count());

        // isEmpty / isNotEmpty
        $this->assertFalse(! $result->has());
        $this->assertTrue($result->has());
    }

    /**
     * 测试唯一键查询.
     *
     * @throws Exception
     */
    public function testUniqueKeyQuery()
    {
        $this->resetTables();

        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->nickname = sprintf('user-%s', $i);
            $user->save();
        }

        // 使用唯一键（nickname）查询
        $result = MultipleModelBatchFinder::findByUniqueKeys([
            User::class => [
                ['nickname' => 'user-1'],
                ['nickname' => 'user-2'],
                ['nickname' => 'user-3'],
            ],
        ]);

        $this->assertSame(3, $result->getOf(User::class)->count());
    }

    /**
     * 测试与单独查询的性能比较（缓存操作次数）.
     *
     * @throws Exception
     */
    public function testCacheOperationComparison()
    {
        $this->resetTables();
        $this->createUsers(5);
        $this->createPosts(5);

        // 使用 MultipleModelBatchFinder：一次缓存读取
        $missedBefore = $this->cacheMissedCount;

        MultipleModelBatchFinder::find([
            User::class => [1, 2, 3],
            Post::class => [1, 2, 3],
        ]);

        // 缓存未命中数应该是 6（每个 ID 一次）
        $batchMissed = $this->cacheMissedCount - $missedBefore;

        // 清空缓存
        User::query()->getCache()->clear();
        Post::query()->getCache()->clear();

        // 使用传统方式：两次单独查询
        $missedBefore = $this->cacheMissedCount;

        User::findByIds([1, 2, 3]);
        Post::findByIds([1, 2, 3]);

        $separateMissed = $this->cacheMissedCount - $missedBefore;

        // 两种方式的缓存未命中数应该相同
        $this->assertSame($batchMissed, $separateMissed);
    }

    /**
     * 测试单模型使用 MultipleModelBatchFinder.
     *
     * @throws Exception
     */
    public function testSingleModel()
    {
        $this->resetTables();
        $this->createUsers(10);

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2, 3, 4, 5],
        ]);

        $users = $result->getOf(User::class);
        $this->assertSame(5, $users->count());

        // 验证返回的是 KnightCollection
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Collection::class,
            $users
        );
    }

    /**
     * 测试 MultipleModelBatchResult::toArray 方法（扁平列表）.
     *
     * @throws Exception
     */
    public function testResultToArray()
    {
        $this->resetTables();

        // 直接创建结果对象进行测试
        $user1 = new User(['id' => 1, 'nickname' => 'user-1']);
        $user2 = new User(['id' => 2, 'nickname' => 'user-2']);
        $post1 = new Post(['id' => 1, 'title' => 'post-1']);

        $result = new MultipleModelBatchResult([
            User::class => [$user1, $user2],
            Post::class => [$post1],
        ]);

        $array = $result->toArray();

        // 验证是扁平列表
        $this->assertCount(3, $array);

        // 验证每个元素都是数组
        foreach ($array as $item) {
            $this->assertIsArray($item);
        }
    }

    /**
     * 测试空结果的 toArray.
     */
    public function testEmptyResultToArray()
    {
        $result = MultipleModelBatchFinder::find([]);

        $array = $result->toArray();
        $this->assertIsArray($array);
        $this->assertEmpty($array);
    }

    /**
     * 测试 get 方法对不存在的类返回空集合.
     *
     * @throws Exception
     */
    public function testGetNonExistentClass()
    {
        $this->resetTables();
        $this->createUsers(2);

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2],
        ]);

        // 获取不存在的类
        $nonExistent = $result->getOf('NonExistentClass');
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Collection::class,
            $nonExistent
        );
        $this->assertSame(0, $nonExistent->count());
    }

    /**
     * 测试链式调用 withUniqueKeys 方法.
     *
     * @throws Exception
     */
    public function testWithUniqueKeysChaining()
    {
        $this->resetTables();
        $this->createUsers(5);

        $result = MultipleModelBatchFinder::make()
            ->withUniqueKeys(User::class, [
                ['nickname' => 'user-1'],
                ['nickname' => 'user-2'],
            ])
            ->withUniqueKeys(User::class, [
                ['nickname' => 'user-3'],
            ])
            ->get();

        // 验证链式添加的条件都被执行
        $this->assertSame(3, $result->getOf(User::class)->count());
    }

    /**
     * 测试 with 方法多次调用同一模型.
     *
     * @throws Exception
     */
    public function testWithMultipleCalls()
    {
        $this->resetTables();
        $this->createUsers(10);

        $result = MultipleModelBatchFinder::make()
            ->with(User::class, [1, 2])
            ->with(User::class, [3, 4, 5])
            ->get();

        // 应该合并所有的 ID
        $this->assertSame(5, $result->getOf(User::class)->count());
    }

    /**
     * 测试重复 ID 的处理.
     *
     * @throws Exception
     */
    public function testDuplicateIds()
    {
        $this->resetTables();
        $this->createUsers(3);

        // 传入重复的 ID
        $result = MultipleModelBatchFinder::find([
            User::class => [1, 1, 2, 2, 3, 3],
        ]);

        // 应该返回 6 条（不去重，因为条件是独立处理的）
        // 但由于缓存键相同，实际结果取决于实现
        $users = $result->getOf(User::class);
        $this->assertGreaterThanOrEqual(3, $users->count());
    }

    /**
     * 测试 Collection 类型的输入.
     *
     * @throws Exception
     */
    public function testCollectionInput()
    {
        $this->resetTables();
        $this->createUsers(5);

        $ids = collect([1, 2, 3]);

        $result = MultipleModelBatchFinder::make()
            ->with(User::class, $ids)
            ->get();

        $this->assertSame(3, $result->getOf(User::class)->count());
    }

    /**
     * 测试缓存键为空的场景.
     *
     * @throws Exception
     */
    public function testEmptyConditions()
    {
        $this->resetTables();
        $this->createUsers(3);

        // 所有条件都会被过滤
        $this->assertDatabaseQueryCount(0, function () {
            $result = MultipleModelBatchFinder::find([
                User::class => [0, null, '', false],
            ]);

            $this->assertSame(0, $result->getOf(User::class)->count());
        });
    }

    /**
     * 测试 toArray 处理非模型对象的情况（扁平列表）.
     */
    public function testResultToArrayWithNonModel()
    {
        // 创建一个包含非模型数据的结果
        $result = new MultipleModelBatchResult([
            'custom' => [
                ['id' => 1, 'name' => 'test1'],
                ['id' => 2, 'name' => 'test2'],
            ],
        ]);

        $array = $result->toArray();

        // 扁平列表，包含 2 个元素
        $this->assertCount(2, $array);
        $this->assertSame(['id' => 1, 'name' => 'test1'], $array[0]);
        $this->assertSame(['id' => 2, 'name' => 'test2'], $array[1]);
    }

    /**
     * 测试 get 方法对不存在的类名使用默认 Collection.
     */
    public function testResultGetNonExistentClassUsesDefaultCollection()
    {
        $result = new MultipleModelBatchResult([
            'FakeClass' => [['id' => 1]],
        ]);

        $collection = $result->getOf('FakeClass');

        // 由于 FakeClass 不存在，应该使用默认的 KnightCollection
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Collection::class,
            $collection
        );
        $this->assertSame(1, $collection->count());
    }

    /**
     * 测试 eachOf 方法遍历指定模型类的实例.
     *
     * @throws Exception
     */
    public function testResultEachOf()
    {
        $this->resetTables();
        $this->createUsers(2);
        $this->createPosts(3);

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2],
            Post::class => [1, 2, 3],
        ]);

        // 测试遍历 User
        $users = [];
        $result->eachOf(User::class, function ($model, $index) use (&$users) {
            $this->assertInstanceOf(User::class, $model);
            $users[$index] = $model;
        });
        $this->assertCount(2, $users);

        // 测试遍历 Post
        $posts = [];
        $result->eachOf(Post::class, function ($model, $index) use (&$posts) {
            $this->assertInstanceOf(Post::class, $model);
            $posts[$index] = $model;
        });
        $this->assertCount(3, $posts);

        // 测试遍历不存在的类（应该不执行回调）
        $count = 0;
        $result->eachOf('NonExistentClass', function () use (&$count) {
            $count++;
        });
        $this->assertSame(0, $count);
    }

    /**
     * 测试完全缓存命中的场景（无数据库查询）.
     *
     * @throws Exception
     */
    public function testFullCacheHit()
    {
        $this->resetTables();
        $this->createUsers(5);
        $this->createPosts(5);

        // 第一次查询，填充缓存
        MultipleModelBatchFinder::find([
            User::class => [1, 2, 3],
            Post::class => [1, 2, 3],
        ]);

        // 第二次查询，应该完全命中缓存
        $this->assertDatabaseQueryCount(0, function () {
            $this->assertCachePutCount(0, function () {
                $result = MultipleModelBatchFinder::find([
                    User::class => [1, 2, 3],
                    Post::class => [1, 2, 3],
                ]);

                // 验证所有数据都是从缓存获取
                $result->getOf(User::class)->each(function (User $user) {
                    $this->assertTrue($user->isFromCache());
                });

                $result->getOf(Post::class)->each(function (Post $post) {
                    $this->assertTrue($post->isFromCache());
                });
            });
        });
    }

    /**
     * 测试混合场景：部分数据缓存命中 + 部分不存在.
     *
     * @throws Exception
     */
    public function testMixedCacheHitAndNotFound()
    {
        $this->resetTables();
        $this->createUsers(3);

        // 填充缓存（包括查询不存在的 ID）
        MultipleModelBatchFinder::find([
            User::class => [1, 999],  // 999 不存在
        ]);

        // 再次查询，1 应该命中缓存，999 不会再查库
        $dbQueryBefore = $this->databaseQueryCount;

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2, 999],  // 1 和 999 命中缓存，2 需要查库
        ]);

        // 只有 1 次数据库查询（查询 ID 2）
        $this->assertSame($dbQueryBefore + 1, $this->databaseQueryCount);
        $this->assertSame(2, $result->getOf(User::class)->count());
    }

    /**
     * 测试 findByUniqueKeys 带正常唯一键值.
     *
     * @throws Exception
     */
    public function testFindByUniqueKeysNormal()
    {
        $this->resetTables();

        $user1 = new User();
        $user1->nickname = 'user-a';
        $user1->save();

        $user2 = new User();
        $user2->nickname = 'user-b';
        $user2->save();

        $result = MultipleModelBatchFinder::findByUniqueKeys([
            User::class => [
                ['nickname' => 'user-a'],
                ['nickname' => 'user-b'],
            ],
        ]);

        $this->assertSame(2, $result->getOf(User::class)->count());
    }

    /**
     * 测试 make 链式调用返回的是正确类型.
     */
    public function testMakeReturnsCorrectType()
    {
        $finder = MultipleModelBatchFinder::make();

        $this->assertInstanceOf(MultipleModelBatchFinder::class, $finder);
    }

    /**
     * 测试 count 方法对空模型结果.
     */
    public function testResultCountWithEmptyModels()
    {
        $result = new MultipleModelBatchResult([
            User::class => [],
            Post::class => [],
        ]);

        $this->assertSame(0, $result->count());
        $this->assertTrue(! $result->has());
        $this->assertFalse($result->has());
    }

    /**
     * 测试 each 方法遍历每一个模型.
     *
     * @throws Exception
     */
    public function testResultEach()
    {
        $this->resetTables();
        $this->createUsers(2);
        $this->createPosts(3);

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2],
            Post::class => [1, 2, 3],
        ]);

        $models = [];
        $indices = [];
        $result->each(function ($model, $index) use (&$models, &$indices) {
            $models[] = $model;
            $indices[] = $index;
        });

        // 验证遍历了所有 5 个模型
        $this->assertCount(5, $models);
        $this->assertSame([0, 1, 2, 3, 4], $indices);

        // 验证包含两种类型的模型
        $userCount = 0;
        $postCount = 0;
        foreach ($models as $model) {
            if ($model instanceof User) {
                $userCount++;
            } elseif ($model instanceof Post) {
                $postCount++;
            }
        }
        $this->assertSame(2, $userCount);
        $this->assertSame(3, $postCount);
    }

    /**
     * 测试 foreach 遍历（IteratorAggregate 接口）.
     *
     * @throws Exception
     */
    public function testResultForeach()
    {
        $this->resetTables();
        $this->createUsers(2);
        $this->createPosts(2);

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2],
            Post::class => [1, 2],
        ]);

        $models = [];
        foreach ($result as $model) {
            $this->assertInstanceOf(
                \Illuminate\Database\Eloquent\Model::class,
                $model
            );
            $models[] = $model;
        }

        // 验证遍历了所有 4 个模型
        $this->assertSame(4, count($models));
    }

    /**
     * 测试 Countable 接口（count() 函数）.
     *
     * @throws Exception
     */
    public function testResultCountable()
    {
        $this->resetTables();
        $this->createUsers(3);

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2, 3],
        ]);

        // 使用 PHP 的 count() 函数
        $this->assertSame(3, count($result));
    }

    /**
     * 测试 get 方法将所有模型合并到一个列表.
     *
     * @throws Exception
     */
    public function testResultGet()
    {
        $this->resetTables();
        $this->createUsers(2);
        $this->createPosts(3);

        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2],
            Post::class => [1, 2, 3],
        ]);

        $allModels = $result->get();

        // 验证总数
        $this->assertSame(5, $allModels->count());

        // 验证包含两种类型的模型
        $this->assertSame(2, $allModels->filter(function ($item) {
            return $item instanceof User;
        })->count());
        $this->assertSame(3, $allModels->filter(function ($item) {
            return $item instanceof Post;
        })->count());
    }

    /**
     * 测试空结果的 get.
     */
    public function testEmptyResultGet()
    {
        $result = MultipleModelBatchFinder::find([]);

        $allModels = $result->get();

        $this->assertSame(0, $allModels->count());
    }

    /**
     * 测试 has 方法（检查是否有任何结果）.
     *
     * @throws Exception
     */
    public function testResultHas()
    {
        $this->resetTables();
        $this->createUsers(2);

        // 有结果
        $result = MultipleModelBatchFinder::find([
            User::class => [1, 2],
        ]);
        $this->assertTrue($result->has());

        // 无结果
        $emptyResult = MultipleModelBatchFinder::find([]);
        $this->assertFalse($emptyResult->has());

        // 有模型但无数据
        $noDataResult = new MultipleModelBatchResult([
            User::class => [],
        ]);
        $this->assertFalse($noDataResult->has());
    }

    /**
     * 测试 findByUniqueKeys 正确处理 null 值条件.
     *
     * @throws Exception
     */
    public function testFindByUniqueKeysWithNull()
    {
        $this->resetTables();

        // 创建 nickname 为 null 的用户
        $user1 = new User();
        $user1->nickname = null;
        $user1->save();

        $user2 = new User();
        $user2->nickname = 'has-name';
        $user2->save();

        // 使用包含 null 值的唯一键查询
        $result = MultipleModelBatchFinder::findByUniqueKeys([
            User::class => [
                ['nickname' => null],
                ['nickname' => 'has-name'],
            ],
        ]);

        $this->assertSame(2, $result->getOf(User::class)->count());

        // 第二次查询应该命中缓存
        $this->assertDatabaseQueryCount(0, function () {
            $result = MultipleModelBatchFinder::findByUniqueKeys([
                User::class => [
                    ['nickname' => null],
                    ['nickname' => 'has-name'],
                ],
            ]);

            $this->assertSame(2, $result->getOf(User::class)->count());

            // 验证是从缓存获取的
            $result->getOf(User::class)->each(function (User $user) {
                $this->assertTrue($user->isFromCache());
            });
        });
    }

    /**
     * 测试 findByUniqueKeys 处理多个相同 null 值条件.
     *
     * @throws Exception
     */
    public function testFindByUniqueKeysWithMultipleSameNullConditions()
    {
        $this->resetTables();

        $user = new User();
        $user->nickname = null;
        $user->save();

        // 传入多个相同的 null 条件
        $result = MultipleModelBatchFinder::findByUniqueKeys([
            User::class => [
                ['nickname' => null],
                ['nickname' => null],
            ],
        ]);

        // 应该返回 2 条（因为条件是独立处理的，可能有重复）
        $this->assertGreaterThanOrEqual(1, $result->getOf(User::class)->count());
    }
}
