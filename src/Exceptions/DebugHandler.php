<?php

namespace HughCube\Laravel\Knight\Exceptions;

use HughCube\Laravel\EasySms\Exceptions\MobileInvalidException as EasySmsMobileInvalidException;
use HughCube\Laravel\EasySms\Exceptions\ThrottleRequestsException as EasySmsThrottleRequestsException;
use HughCube\Laravel\Knight\Exceptions\Contracts\DataExceptionInterface;
use HughCube\Laravel\Knight\Exceptions\Contracts\ResponseExceptionInterface;
use HughCube\Laravel\Knight\Exceptions\Exception as KnightException;
use HughCube\Laravel\Knight\Http\JsonResponse as KJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class DebugHandler extends ExceptionHandler
{
    /**
     * @inheritdoc
     */
    protected $dontReport = [
        HttpException::class,
        UserException::class,
        DataExceptionInterface::class,
        ResponseExceptionInterface::class,
        ValidateSignatureException::class,
        ValidatePinCodeException::class,
        AuthenticationException::class,
        ValidationException::class,
    ];

    /**
     * 子类可重写此方法来自定义异常转换逻辑.
     */
    protected function convertExceptionToResults(Throwable $e): ?array
    {
        return null;
    }

    /**
     * @param  $request
     * @param  Throwable  $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        $e = $this->mapException($e);
        $e = $this->prepareException($e);

        if ($e instanceof ResponseExceptionInterface) {
            return $e->getResponse();
        }

        $results = $this->buildResults($e);

        if (config('app.debug')) {
            $results['Debug'] = $this->convertExceptionToDebugArray($e);
        }

        return new KJsonResponse($results);
    }

    /**
     * 根据异常构建响应结果.
     */
    protected function buildResults(Throwable $e): array
    {
        // 优先使用子类自定义的转换逻辑
        $custom = $this->convertExceptionToResults($e);
        if (! empty($custom)) {
            $custom['Data'] ??= new stdClass();

            return $custom;
        }

        if ($e instanceof ValidateSignatureException) {
            $results = ['Code' => 'SignatureInvalid', 'Message' => '签名验证失败!'];
        } elseif ($e instanceof ValidatePinCodeException) {
            $results = ['Code' => 'PinCodeInvalid', 'Message' => '请输入正确的验证码!'];
        } elseif ($e instanceof AuthenticationException) {
            $results = ['Code' => 'Unauthorized', 'Message' => '请先登录!'];
        } elseif ($e instanceof ValidationException) {
            $results = ['Code' => 'ValidationFailed', 'Message' => '非法请求!', 'Errors' => $e->errors()];
        } elseif ($e instanceof DataExceptionInterface) {
            $results = ['Code' => $e->getCode() ?: 'Failure', 'Message' => $e->getMessage(), 'Data' => $e->getData() ?: new stdClass()];
        } elseif ($e instanceof HttpException) {
            $results = ['Code' => 'HttpError', 'Message' => Response::$statusTexts[$e->getStatusCode()] ?? ''];
        } elseif ($e instanceof UserException) {
            $results = ['Code' => $e->getStringCode(), 'Message' => $e->getMessage()];
        } elseif ($e instanceof KnightException) {
            $results = ['Code' => 'Failure', 'Message' => $e->getMessage()];
        } elseif ($e instanceof EasySmsMobileInvalidException) {
            $results = ['Code' => 'MobileInvalid', 'Message' => '手机号码不正确!'];
        } elseif ($e instanceof EasySmsThrottleRequestsException) {
            $results = ['Code' => 'SmsThrottled', 'Message' => '短信发送太频繁了, 请稍后再试!'];
        } else {
            $results = ['Code' => 'ServerError', 'Message' => '服务器繁忙, 请稍后再试!'];
        }

        $results['Data'] ??= new stdClass();

        return $results;
    }

    /**
     * 将异常转换为调试数组.
     */
    protected function convertExceptionToDebugArray(Throwable $e): array
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
            $array['previous'] = $this->convertExceptionToDebugArray($prev);
        }

        return $array;
    }
}
