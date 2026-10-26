<?php

namespace HughCube\Laravel\EasySms\Exceptions {
    if (! class_exists(MobileInvalidException::class, false)) {
        class MobileInvalidException extends \Exception
        {
        }
    }

    if (! class_exists(ThrottleRequestsException::class, false)) {
        class ThrottleRequestsException extends \Exception
        {
        }
    }
}

namespace HughCube\Laravel\Knight\Tests\Exceptions {
    use HughCube\Laravel\EasySms\Exceptions\MobileInvalidException as EasySmsMobileInvalidException;
    use HughCube\Laravel\EasySms\Exceptions\ThrottleRequestsException as EasySmsThrottleRequestsException;
    use HughCube\Laravel\Knight\Exceptions\Contracts\DataExceptionInterface;
    use HughCube\Laravel\Knight\Exceptions\Contracts\ResponseExceptionInterface;
    use HughCube\Laravel\Knight\Exceptions\DebugHandler;
    use HughCube\Laravel\Knight\Exceptions\Exception as KnightException;
    use HughCube\Laravel\Knight\Exceptions\UserException;
    use HughCube\Laravel\Knight\Exceptions\ValidatePinCodeException;
    use HughCube\Laravel\Knight\Exceptions\ValidateSignatureException;
    use HughCube\Laravel\Knight\Http\JsonResponse as KnightJsonResponse;
    use HughCube\Laravel\Knight\Tests\TestCase;
    use Illuminate\Auth\AuthenticationException;
    use Illuminate\Http\Request;
    use Illuminate\Validation\ValidationException;
    use stdClass;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
    use Throwable;

    class DebugHandlerTest extends TestCase
    {
        public function testRenderReturnsResponseFromResponseExceptionInterface()
        {
            $handler = $this->app->make(DebugHandler::class);
            $request = Request::create('/exception', 'GET');
            $response = new Response('ok', 418);

            $exception = new class($response) extends \Exception implements ResponseExceptionInterface
            {
                private Response $response;

                public function __construct(Response $response)
                {
                    parent::__construct('custom');
                    $this->response = $response;
                }

                public function getResponse()
                {
                    return $this->response;
                }
            };

            $rendered = $handler->render($request, $exception);

            $this->assertSame($response, $rendered);
        }

        public function testRenderMapsKnownExceptionsToJsonPayloads()
        {
            $handler = $this->app->make(DebugHandler::class);
            $request = Request::create('/exception', 'GET');

            $dataException = new class('data-error', 499) extends \Exception implements DataExceptionInterface
            {
                public function getData(): array
                {
                    return ['key' => 'value'];
                }
            };

            $emptyDataException = new class('empty', 0) extends \Exception implements DataExceptionInterface
            {
                public function getData(): array
                {
                    return [];
                }
            };

            $cases = [
                [new ValidateSignatureException('signature'), 'SignatureInvalid'],
                [new ValidatePinCodeException('pin', 429), 'PinCodeInvalid'],
                [new AuthenticationException(), 'Unauthorized'],
                [ValidationException::withMessages(['name' => ['invalid']]), 'ValidationFailed'],
                [$dataException, 499],
                [$emptyDataException, 'Failure'],
                [new NotFoundHttpException(), 'HttpError'],
                [new UserException('user', 403), 'UserException'],
                [new KnightException('knight'), 'Failure'],
                [new EasySmsMobileInvalidException('mobile-invalid'), 'MobileInvalid'],
                [new EasySmsThrottleRequestsException('sms-throttled'), 'SmsThrottled'],
                [new \Error('boom'), 'ServerError'],
            ];

            foreach ($cases as $case) {
                $exception = $case[0];
                $expectedCode = $case[1];

                $rendered = $handler->render($request, $exception);

                $this->assertInstanceOf(KnightJsonResponse::class, $rendered);
                $data = $rendered->getData(true);

                $this->assertSame($expectedCode, $data['Code']);
                $this->assertArrayHasKey('Data', $data);
            }
        }

        public function testRenderIncludesDebugDataWhenEnabled()
        {
            config(['app.debug' => true]);

            try {
                $handler = $this->app->make(DebugHandler::class);
                $request = Request::create('/exception', 'GET');
                $exception = new \RuntimeException('current', 0, new \RuntimeException('previous'));

                $rendered = $handler->render($request, $exception);

                $this->assertInstanceOf(KnightJsonResponse::class, $rendered);

                $data = $rendered->getData(true);
                $this->assertArrayHasKey('Debug', $data);
                $this->assertSame('current', $data['Debug']['message']);
                $this->assertSame('previous', $data['Debug']['previous']['message']);
            } finally {
                config(['app.debug' => false]);
            }
        }

        public function testBuildResultsUsesCustomConversionAndDefaultsData()
        {
            $handler = new class($this->app) extends DebugHandler
            {
                protected function convertExceptionToResults(Throwable $e): ?array
                {
                    return [
                        'Code' => 'Custom',
                        'Message' => 'Converted',
                    ];
                }
            };

            $results = self::callMethod($handler, 'buildResults', [new \RuntimeException('x')]);

            $this->assertSame('Custom', $results['Code']);
            $this->assertSame('Converted', $results['Message']);
            $this->assertArrayHasKey('Data', $results);
            $this->assertInstanceOf(stdClass::class, $results['Data']);
        }

        public function testConvertExceptionToDebugArrayIncludesValidationErrors()
        {
            $handler = $this->app->make(DebugHandler::class);
            $exception = ValidationException::withMessages(['field' => ['invalid']]);

            $debug = self::callMethod($handler, 'convertExceptionToDebugArray', [$exception]);

            $this->assertArrayHasKey('errors', $debug);
            $this->assertSame(['field' => ['invalid']], $debug['errors']);
        }
    }
}
