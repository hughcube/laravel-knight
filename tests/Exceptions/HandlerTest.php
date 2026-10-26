<?php

namespace HughCube\Laravel\Knight\Tests\Exceptions;

use HughCube\Laravel\Knight\Exceptions\Contracts\DataExceptionInterface;
use HughCube\Laravel\Knight\Exceptions\Contracts\ResponseExceptionInterface;
use HughCube\Laravel\Knight\Exceptions\Handler;
use HughCube\Laravel\Knight\Exceptions\UserException;
use HughCube\Laravel\Knight\Exceptions\ValidatePinCodeException;
use HughCube\Laravel\Knight\Exceptions\ValidateSignatureException;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HandlerTest extends TestCase
{
    public function testMake()
    {
        $handler = $this->app->make(Handler::class);

        $this->assertInstanceOf(ExceptionHandler::class, $handler);
    }

    public function testRenderReturnsResponseFromResponseExceptionInterface()
    {
        $handler = $this->app->make(Handler::class);
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
        $handler = $this->app->make(Handler::class);
        $request = Request::create('/exception', 'GET');

        $dataException = new class('data-error', 499) extends \Exception implements DataExceptionInterface
        {
            public function getData(): array
            {
                return ['key' => 'value'];
            }
        };

        $cases = [
            [new ValidateSignatureException('signature'), 400],
            [new ValidatePinCodeException('pin', 429), 429],
            [new AuthenticationException(), 401],
            [ValidationException::withMessages(['name' => ['invalid']]), 422],
            [$dataException, 499],
            [new NotFoundHttpException(), 404],
            [new UserException('user', 403), 403],
            [new \Exception('oops'), 500],
            [new \Error('boom'), 500],
        ];

        foreach ($cases as [$exception, $expectedCode]) {
            $rendered = $handler->render($request, $exception);

            $this->assertInstanceOf(JsonResponse::class, $rendered);
            $data = $rendered->getData(true);

            $this->assertSame($expectedCode, $data['code']);
            $this->assertArrayHasKey('data', $data);
        }
    }

    public function testRenderIncludesDebugDataWhenEnabled()
    {
        config(['app.debug' => true]);

        try {
            $handler = $this->app->make(Handler::class);
            $request = Request::create('/exception', 'GET');
            $exception = ValidationException::withMessages(['field' => ['invalid']]);

            $rendered = $handler->render($request, $exception);

            $this->assertInstanceOf(JsonResponse::class, $rendered);

            $data = $rendered->getData(true);
            $this->assertArrayHasKey('debug', $data);
            $this->assertSame(ValidationException::class, $data['debug']['exception']);
            $this->assertArrayHasKey('errors', $data['debug']);
        } finally {
            config(['app.debug' => false]);
        }
    }

    public function testConvertResultsToResponseHandlesStringAndResponse()
    {
        $handler = $this->app->make(Handler::class);

        $stringResponse = self::callMethod($handler, 'convertResultsToResponse', ['ok']);
        $this->assertInstanceOf(Response::class, $stringResponse);
        $this->assertSame('ok', $stringResponse->getContent());

        $response = new Response('raw');
        $same = self::callMethod($handler, 'convertResultsToResponse', [$response]);
        $this->assertSame($response, $same);
    }

    public function testConvertExceptionToArrayIncludesPrevious()
    {
        $handler = $this->app->make(Handler::class);
        $previous = new \RuntimeException('previous');
        $exception = new \RuntimeException('current', 0, $previous);

        $data = self::callMethod($handler, 'convertExceptionToArray', [$exception]);

        $this->assertSame('current', $data['message']);
        $this->assertArrayHasKey('previous', $data);
        $this->assertSame('previous', $data['previous']['message']);
    }
}
