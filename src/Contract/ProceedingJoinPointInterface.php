<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

/**
 * 继续执行连接点接口
 * 
 * 用于 Around 通知，可以控制是否继续执行原方法
 */
interface ProceedingJoinPointInterface extends JoinPointInterface
{
    /**
     * 继续执行原方法
     *
     * @param array $arguments 方法参数
     * @return mixed 原方法的返回值
     */
    public function proceed(array $arguments = []): mixed;
}