<?php

namespace HughCube\Laravel\Knight\Tests\Routing;

use HughCube\Laravel\Knight\Routing\Action;
use HughCube\Laravel\Knight\Routing\PaginateQuery;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Mockery;

class PaginateQueryActionStub
{
    use Action, PaginateQuery {
        PaginateQuery::rules insteadof Action;
    }

    protected $builderMock;

    public function __construct($builderMock = null)
    {
        $this->builderMock = $builderMock;
    }

    protected function makeQuery(): ?Builder
    {
        return $this->builderMock;
    }
}

/**
 * 模拟子类自定义 rules 绕过了 page_size 的 max 校验, 用于验证 getPageSize() 的硬上限兜底.
 */
class PaginateQueryNoMaxRuleStub extends PaginateQueryActionStub
{
    protected function rules(): array
    {
        return [
            'page'      => ['integer', 'remove_if_empty'],
            'page_size' => ['integer', 'remove_if_empty'],
        ];
    }
}

class PaginateQueryTest extends TestCase
{
    public function testPaginateQuery()
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('count')->andReturn(100);
        $builder->shouldReceive('limit')->with(10)->andReturnSelf();
        $builder->shouldReceive('offset')->with(0)->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(new Collection(range(1, 10)));

        $action = new PaginateQueryActionStub($builder);

        // 模拟请求参数
        $request = \Illuminate\Http\Request::create('/test', 'GET', ['page' => 1, 'page_size' => 10]);
        $this->app->instance('request', $request);

        $response = $action();

        $content = $response->getData(true);
        $this->assertSame('Success', $content['Code']);
        $this->assertSame(1, $content['Data']['page']);
        $this->assertSame(10, $content['Data']['page_size']);
        $this->assertSame(100, $content['Data']['count']);
        $this->assertCount(10, $content['Data']['list']);
    }

    public function testPageSizeClampedToMax()
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('count')->andReturn(100);
        $builder->shouldReceive('limit')->with(1000)->andReturnSelf();
        $builder->shouldReceive('offset')->with(0)->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(new Collection(range(1, 10)));

        $action = new PaginateQueryNoMaxRuleStub($builder);

        // page_size 超过上限, 应被钳制到 1000
        $request = \Illuminate\Http\Request::create('/test', 'GET', ['page' => 1, 'page_size' => 999999]);
        $this->app->instance('request', $request);

        $response = $action();

        $content = $response->getData(true);
        $this->assertSame('Success', $content['Code']);
        $this->assertSame(1000, $content['Data']['page_size']);
    }
}
