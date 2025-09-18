<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

/**
 * AOP 内核接口
 * 
 * 定义 AOP 内核的核心功能
 */
interface AspectKernelInterface
{
    /**
     * 注册切面
     */
    public function registerAspect(object $aspect): void;

    /**
     * 获取代理对象
     */
    public function getProxy(string $className): object;

    /**
     * 初始化内核
     */
    public function init(): void;
}