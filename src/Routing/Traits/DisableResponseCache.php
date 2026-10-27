<?php

namespace HughCube\Laravel\Knight\Routing\Traits;

trait DisableResponseCache
{
    /**
     * 声明当前 Action 强力禁用浏览器与代理缓存.
     */
    protected function isDisableResponseCache(): bool
    {
        return true;
    }
}
