<?php

namespace HughCube\Laravel\Knight\Exceptions;

use HughCube\Laravel\EasySms\Exceptions\MobileInvalidException as EasySmsMobileInvalidException;
use HughCube\Laravel\EasySms\Exceptions\ThrottleRequestsException as EasySmsThrottleRequestsException;
use HughCube\Laravel\Knight\Exceptions\Contracts\DataExceptionInterface;
use HughCube\Laravel\Knight\Exceptions\Contracts\ResponseExceptionInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        HttpException::class,
        UserException::class,

        DataExceptionInterface::class,
        ResponseExceptionInterface::class,
    ];

    /**
     * @inheritdoc
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected function context(): array
    {
        $context = parent::context();

        try {
            $context['uri'] = request()->getUri();
            $context['headers'] = request()->headers->all();
            $context['body'] = request()->getContent();
        } catch (Throwable $exception) {
        }

        return $context;
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        /** @phpstan-ignore-next-line */
        if (method_exists($this, 'reportable')) {
            $this->reportable(function (Throwable $e) {
            });
        }
    }

    /**
     * @param  Throwable  $e
     * @return null|array
     *
     * @deprecated
     */
    protected function convertExceptionToResponseData($e): ?array
    {
        return null;
    }

    protected function convertExceptionToResults($e): ?array
    {
        /** @phpstan-ignore-next-line */
        if (method_exists($this, 'convertExceptionToResponseData')) {
            return $this->convertExceptionToResponseData($e);
        }

        return null;
    }

    /**
     * @param  Request  $request
     * @param  \Exception|Throwable  $e
     * @return mixed
     *
     * @throws Throwable
     */
    public function render($request, $e)
    {
        /** @phpstan-ignore-next-line */
        $e = method_exists($this, 'mapException') ? $this->mapException($e) : $e;
        $e = $this->prepareException($e);

        if ($e instanceof ResponseExceptionInterface) {
            return $e->getResponse();
        }

        /** @phpstan-ignore-next-line */
        if (! empty($data = $this->convertExceptionToResults($e)) && is_array($data)) {
            $results = $data;
        } elseif ($e instanceof ValidateSignatureException) {
            $results = ['code' => 400, 'message' => '签名验证失败!'];
        } elseif ($e instanceof ValidatePinCodeException) {
            $results = ['code' => $e->getCode() ?: 400, 'message' => '请输入正确的验证码!'];
        } elseif ($e instanceof AuthenticationException) {
            $results = ['code' => 401, 'message' => '请先登录!'];
        } elseif ($e instanceof ValidationException) {
            $results = ['code' => $e->status, 'message' => '非法请求!', 'errors' => $e->errors()];
        } elseif ($e instanceof DataExceptionInterface) {
            $results = ['code' => $e->getCode(), 'message' => $e->getMessage(), 'data' => $e->getData()];
        } elseif ($e instanceof HttpException) {
            $results = ['code' => $e->getStatusCode(), 'message' => Response::$statusTexts[$e->getStatusCode()] ?? ''];
        } elseif ($e instanceof UserException) {
            $results = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } elseif ($e instanceof Exception) {
            $results = ['code' => 500, 'message' => $e->getMessage()];
        } elseif ($e instanceof EasySmsMobileInvalidException) {
            $results = ['code' => $e->getCode(), 'message' => '手机号码不正确!'];
        } elseif ($e instanceof EasySmsThrottleRequestsException) {
            $results = ['code' => $e->getCode(), 'message' => '短信发送太频繁了, 请稍后再试!'];
        } else {
            $results = ['code' => 500, 'message' => '服务器繁忙, 请稍后再试!'];
        }

        $results['data'] = empty($results['data']) ? new stdClass() : $results['data'];

        if (config('app.debug')) {
            $results['debug'] = $this->convertExceptionToArray($e);
        }

        return $this->convertResultsToResponse($results);
    }

    /**
     * Converts an exception into an array.
     *
     * @param  Throwable|\Exception  $e
     * @return array the array representation of the exception.
     */
    protected function convertExceptionToArray($e): array
    {
        $array = [
            'code' => $e->getCode(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stack-trace' => $e->getTrace(),
        ];

        if ($e instanceof ValidationException) {
            $array['errors'] = $e->errors();
        }

        if (($prev = $e->getPrevious()) !== null) {
            $array['previous'] = $this->convertExceptionToArray($prev);
        }

        return $array;
    }

    /**
     * @param  array|Response|string  $results
     * @return Response
     */
    protected function convertResultsToResponse($results): Response
    {
        if ($results instanceof Response) {
            return $results;
        }

        if (is_string($results)) {
            return new Response($results);
        }

        return new JsonResponse($results);
    }
}
