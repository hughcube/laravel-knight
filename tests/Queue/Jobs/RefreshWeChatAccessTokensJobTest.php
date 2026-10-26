<?php

namespace HughCube\Laravel\Knight\Tests\Queue\Jobs;

use EasyWeChat\Kernel\Contracts\AccessToken as AccessTokenInterface;
use EasyWeChat\MiniApp\Application as MiniApp;
use EasyWeChat\MiniApp\Contracts\Account as MiniAppAccount;
use EasyWeChat\OfficialAccount\Application as OfficialAccount;
use EasyWeChat\OfficialAccount\Contracts\Account as OfficialAccountAccount;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use HughCube\Laravel\Knight\Queue\Jobs\RefreshWeChatMiniAppAccessTokensJob;
use HughCube\Laravel\Knight\Queue\Jobs\RefreshWeChatOfficialAccountAccessTokensJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClientInterface;

class RefreshWeChatAccessTokensJobTest extends TestCase
{
    public function testMiniAppResetHttpClientSkipsWhenNoProxy()
    {
        if (! class_exists(MiniApp::class)) {
            $this->markTestSkipped('MiniApp Application class is not available.');
        }

        $job = new RefreshWeChatMiniAppAccessTokensJob(['proxy' => null]);
        $this->callMethod($job, 'loadParameters');

        $app = $this->createWeChatAppMock(MiniApp::class, ['getHttpClient', 'setHttpClient']);

        $app->expects($this->never())->method('getHttpClient');
        $app->expects($this->never())->method('setHttpClient');

        $result = $this->callMethod($job, 'resetHttpClient', [$app]);

        $this->assertSame($app, $result);
    }

    public function testMiniAppResetHttpClientUsesProxy()
    {
        if (! class_exists(MiniApp::class)) {
            $this->markTestSkipped('MiniApp Application class is not available.');
        }
        $this->skipIfHttpClientMissing();

        $job = new RefreshWeChatMiniAppAccessTokensJob(['proxy' => 'http://proxy']);
        $this->callMethod($job, 'loadParameters');

        $httpClient = $this->createHttpClientMockForTest();

        $app = $this->createWeChatAppMock(MiniApp::class, ['getHttpClient', 'setHttpClient']);

        $app->expects($this->once())->method('getHttpClient')->willReturn($httpClient);
        $app->expects($this->once())
            ->method('setHttpClient')
            ->with($this->callback($this->proxyClientMatcher('http://proxy')))
            ->willReturn($app);

        $result = $this->callMethod($job, 'resetHttpClient', [$app]);

        $this->assertSame($app, $result);
    }

    public function testOfficialAccountResetHttpClientUsesProxy()
    {
        if (! class_exists(OfficialAccount::class)) {
            $this->markTestSkipped('OfficialAccount Application class is not available.');
        }
        $this->skipIfHttpClientMissing();

        $job = new RefreshWeChatOfficialAccountAccessTokensJob(['proxy' => 'http://proxy']);
        $this->callMethod($job, 'loadParameters');

        $httpClient = $this->createHttpClientMockForTest();

        $app = $this->createWeChatAppMock(OfficialAccount::class, ['getHttpClient', 'setHttpClient']);

        $app->expects($this->once())->method('getHttpClient')->willReturn($httpClient);
        $app->expects($this->once())
            ->method('setHttpClient')
            ->with($this->callback($this->proxyClientMatcher('http://proxy')))
            ->willReturn($app);

        $result = $this->callMethod($job, 'resetHttpClient', [$app]);

        $this->assertSame($app, $result);
    }

    public function testMiniAppActionRefreshesTokensAndSkipsEmptyAppId()
    {
        if (! class_exists(MiniApp::class)) {
            $this->markTestSkipped('MiniApp Application class is not available.');
        }
        $this->skipIfHttpClientMissing();
        if (! interface_exists(AccessTokenInterface::class)) {
            $this->markTestSkipped('EasyWeChat AccessToken interface is not available.');
        }

        config(['easywechat.mini_app' => ['empty' => [], 'valid' => []]]);

        $emptyApp = $this->makeMiniAppMock('');
        $validToken = new FakeAccessToken();
        $validApp = $this->makeMiniAppMock('mini-app-1', $validToken, 'http://proxy');

        $this->app->instance('easywechat.mini_app.empty', $emptyApp);
        $this->app->instance('easywechat.mini_app.valid', $validApp);

        $job = new RefreshWeChatMiniAppAccessTokensJob(['proxy' => 'http://proxy']);

        $this->assertJob($job);

        $this->assertSame(1, $validToken->refreshCalls);
    }

    public function testOfficialAccountActionRefreshesTokens()
    {
        if (! class_exists(OfficialAccount::class)) {
            $this->markTestSkipped('OfficialAccount Application class is not available.');
        }
        $this->skipIfHttpClientMissing();
        if (! interface_exists(AccessTokenInterface::class)) {
            $this->markTestSkipped('EasyWeChat AccessToken interface is not available.');
        }

        config(['easywechat.official_account' => ['main' => []]]);

        $token = new FakeAccessToken();
        $app = $this->makeOfficialAccountMock('official-1', $token, 'http://proxy');

        $this->app->instance('easywechat.official_account.main', $app);

        $job = new RefreshWeChatOfficialAccountAccessTokensJob(['proxy' => 'http://proxy']);

        $this->assertJob($job);

        $this->assertSame(1, $token->refreshCalls);
    }

    private function makeMiniAppMock(string $appId, ?FakeAccessToken $token = null, ?string $proxy = null): MiniApp
    {
        $account = $this->makeMiniAppAccount($appId);

        $app = $this->createWeChatAppMock(
            MiniApp::class,
            ['getAccount', 'getAccessToken', 'getHttpClient', 'setHttpClient']
        );

        $app->method('getAccount')->willReturn($account);

        if ($appId === '') {
            $app->expects($this->never())->method('getAccessToken');
            $app->expects($this->never())->method('getHttpClient');
            $app->expects($this->never())->method('setHttpClient');

            return $app;
        }

        $token = $token ?? new FakeAccessToken();

        $app->expects($this->once())->method('getAccessToken')->willReturn($token);

        if (empty($proxy)) {
            $app->expects($this->never())->method('getHttpClient');
            $app->expects($this->never())->method('setHttpClient');

            return $app;
        }

        $httpClient = $this->createHttpClientMockForTest();

        $app->expects($this->once())->method('getHttpClient')->willReturn($httpClient);
        $app->expects($this->once())
            ->method('setHttpClient')
            ->with($this->callback($this->proxyClientMatcher($proxy)))
            ->willReturn($app);

        return $app;
    }

    private function makeOfficialAccountMock(string $appId, FakeAccessToken $token, ?string $proxy = null): OfficialAccount
    {
        $account = $this->makeOfficialAccountAccount($appId);

        $app = $this->createWeChatAppMock(
            OfficialAccount::class,
            ['getAccount', 'getAccessToken', 'getHttpClient', 'setHttpClient']
        );

        $app->method('getAccount')->willReturn($account);
        $app->expects($this->once())->method('getAccessToken')->willReturn($token);

        if (empty($proxy)) {
            $app->expects($this->never())->method('getHttpClient');
            $app->expects($this->never())->method('setHttpClient');

            return $app;
        }

        $httpClient = $this->createHttpClientMockForTest();

        $app->expects($this->once())->method('getHttpClient')->willReturn($httpClient);
        $app->expects($this->once())
            ->method('setHttpClient')
            ->with($this->callback($this->proxyClientMatcher($proxy)))
            ->willReturn($app);

        return $app;
    }

    private function makeMiniAppAccount(string $appId): MiniAppAccount
    {
        return new class($appId) implements MiniAppAccount
        {
            private string $appId;

            public function __construct(string $appId)
            {
                $this->appId = $appId;
            }

            public function getAppId(): string
            {
                return $this->appId;
            }

            public function getSecret(): string
            {
                return '';
            }

            public function getToken(): ?string
            {
                return null;
            }

            public function getAesKey(): ?string
            {
                return null;
            }
        };
    }

    private function makeOfficialAccountAccount(string $appId): OfficialAccountAccount
    {
        return new class($appId) implements OfficialAccountAccount
        {
            private string $appId;

            public function __construct(string $appId)
            {
                $this->appId = $appId;
            }

            public function getAppId(): string
            {
                return $this->appId;
            }

            public function getSecret(): string
            {
                return '';
            }

            public function getToken(): ?string
            {
                return null;
            }

            public function getAesKey(): ?string
            {
                return null;
            }
        };
    }

    private function skipIfHttpClientMissing(): void
    {
        if (! $this->isGuzzleHttpClient() && ! $this->isSymfonyHttpClient()) {
            $this->markTestSkipped('Neither Guzzle nor Symfony HTTP client interface is available.');
        }
    }

    private function isGuzzleHttpClient(): bool
    {
        return interface_exists(GuzzleClientInterface::class);
    }

    private function isSymfonyHttpClient(): bool
    {
        return interface_exists(SymfonyHttpClientInterface::class);
    }

    private function usesSymfonyHttpClient(): bool
    {
        if (! class_exists(MiniApp::class)) {
            return false;
        }

        if (! method_exists(MiniApp::class, 'getHttpClient')) {
            return false;
        }

        try {
            $method = new \ReflectionMethod(MiniApp::class, 'getHttpClient');
            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionNamedType) {
                return $returnType->getName() === SymfonyHttpClientInterface::class;
            }
        } catch (\ReflectionException $e) {
            // ignore
        }

        return false;
    }

    private function createHttpClientMockForTest()
    {
        if ($this->usesSymfonyHttpClient()) {
            $httpClient = $this->createMock(SymfonyHttpClientInterface::class);
            $httpClient->expects($this->once())
                ->method('withOptions')
                ->willReturnSelf();

            return $httpClient;
        }

        $httpClient = $this->createMock(GuzzleClientInterface::class);
        $httpClient->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        return $httpClient;
    }

    private function createWeChatAppMock(string $className, array $methods)
    {
        $builder = $this->getMockBuilder($className)
            ->disableOriginalConstructor();

        $existing = [];
        $missing = [];

        foreach ($methods as $method) {
            if (method_exists($className, $method)) {
                $existing[] = $method;
            } else {
                $missing[] = $method;
            }
        }

        if (! empty($existing)) {
            $builder->onlyMethods($existing);
        }

        if (! empty($missing)) {
            $builder->addMethods($missing);
        }

        return $builder->getMock();
    }

    private function proxyClientMatcher(string $proxy): callable
    {
        return function ($client) use ($proxy) {
            // For Symfony HTTP Client (EasyWeChat 6.x)
            if ($client instanceof SymfonyHttpClientInterface) {
                return true;
            }

            // For Guzzle HTTP Client (EasyWeChat 5.x)
            if ($client instanceof GuzzleClientInterface) {
                if (! method_exists($client, 'getConfig')) {
                    return false;
                }

                return $client->getConfig('proxy') === $proxy;
            }

            return false;
        };
    }
}

if (interface_exists(AccessTokenInterface::class)) {
    class FakeAccessToken implements AccessTokenInterface
    {
        public int $refreshCalls = 0;

        public function refresh(): string
        {
            $this->refreshCalls++;

            return 'token-'.$this->refreshCalls;
        }

        public function getToken(): string
        {
            return 'token';
        }

        public function toQuery(): array
        {
            return [];
        }
    }
} else {
    class FakeAccessToken
    {
        public int $refreshCalls = 0;

        public function refresh(): string
        {
            $this->refreshCalls++;

            return 'token-'.$this->refreshCalls;
        }

        public function getToken(): string
        {
            return 'token';
        }

        public function toQuery(): array
        {
            return [];
        }
    }
}
