<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\DevopsScheduleAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DevopsScheduleActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::any('/devops/schedule', DevopsScheduleAction::class)->name('knight.devops.schedule');
    }

    public function testUnauthorizedAccessDenied()
    {
        putenv('KNIGHT_DEVOPS_SCHEDULE_TOKEN=test-token-123456');

        $this->expectException(AccessDeniedHttpException::class);

        $request = Request::create('/devops/schedule', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.195', // 公网 IP
        ]);
        $this->app->instance('request', $request);

        /** @var DevopsScheduleAction $action */
        $action = $this->app->make(DevopsScheduleAction::class);
        $action();
    }

    public function testAuthorizedWithToken()
    {
        putenv('KNIGHT_DEVOPS_SCHEDULE_TOKEN=test-token-123456');
        Cache::flush();

        $request = Request::create('/devops/schedule', 'POST', [], [], [], [
            'REMOTE_ADDR'         => '203.0.113.195', // 公网 IP
            'HTTP_X_DEVOPS_TOKEN' => 'test-token-123456',
        ]);
        $this->app->instance('request', $request);

        /** @var DevopsScheduleAction $action */
        $action = $this->app->make(DevopsScheduleAction::class);
        $response = $action();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Success', $data['Code']);
        $this->assertSame('done', $data['Data']['status']);
    }

    public function testAuthorizedWithIntranetIp()
    {
        putenv('KNIGHT_DEVOPS_SCHEDULE_TOKEN='); // 无 Token 模式
        Cache::flush();

        // 127.0.0.1 本机白名单
        $request = Request::create('/devops/schedule', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $this->app->instance('request', $request);

        /** @var DevopsScheduleAction $action */
        $action = $this->app->make(DevopsScheduleAction::class);
        $response = $action();
        $this->assertSame(200, $response->getStatusCode());

        // 172.20.0.5 Docker 内网白名单
        $request2 = Request::create('/devops/schedule', 'POST', [], [], [], [
            'REMOTE_ADDR' => '172.20.0.5',
        ]);
        $this->app->instance('request', $request2);

        /** @var DevopsScheduleAction $action2 */
        $action2 = $this->app->make(DevopsScheduleAction::class);
        $response2 = $action2();
        $this->assertSame(200, $response2->getStatusCode());
    }

    public function testMinuteIdempotencyLock()
    {
        putenv('KNIGHT_DEVOPS_SCHEDULE_TOKEN=');
        Cache::flush();

        $request = Request::create('/devops/schedule', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $this->app->instance('request', $request);

        // 第 1 次触发
        /** @var DevopsScheduleAction $action1 */
        $action1 = $this->app->make(DevopsScheduleAction::class);
        $response1 = $action1();
        $data1 = json_decode($response1->getContent(), true);
        $this->assertSame('done', $data1['Data']['status']);

        // 同一分钟内第 2 次触发：幂等锁生效，跳过执行
        /** @var DevopsScheduleAction $action2 */
        $action2 = $this->app->make(DevopsScheduleAction::class);
        $response2 = $action2();
        $data2 = json_decode($response2->getContent(), true);
        $this->assertSame('skipped', $data2['Data']['status']);
        $this->assertStringContainsString('Already triggered', $data2['Data']['reason']);
    }
}
