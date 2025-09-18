<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

use ReflectionClass;
use ReflectionMethod;

/**
 * 连接点接口
 * 
 * 封装了方法调用的上下文信息
 */
interface JoinPointInterface
{
    /**
     * 获取目标类的反射对象
     */
    public function getClass(): ReflectionClass;

    /**
     * 获取目标方法的反射对象
     */
    public function getMethod(): ReflectionMethod;

    /**
     * 获取目标对象实例
     */
    public function getThis(): object;

    /**
     * 获取方法参数
     */
    public function getArguments(): array;

    /**
     * 设置方法参数
     */
    public function setArguments(array $args): void;

    /**
     * 获取切入点表达式
     */
    public function getPointcut(): string;
}