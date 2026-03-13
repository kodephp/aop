<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use ReflectionClass;
use ReflectionMethod;
use Kode\Aop\Contract\JoinPointInterface;

/**
 * 连接点实现类
 *
 * 封装了方法调用的完整上下文信息，包括目标类、目标方法、
 * 目标对象实例、方法参数和切入点表达式。
 *
 * 连接点是 AOP 框架中的核心概念，代表程序执行过程中的某个特定点。
 * 在本框架中，连接点主要指方法的调用点。
 *
 * @package Kode\Aop\Runtime
 * @author Kode Team <382601296@qq.com>
 * @see JoinPointInterface
 */
class JoinPoint implements JoinPointInterface
{
    /**
     * 构造函数
     *
     * @param ReflectionClass $class 目标类的反射对象
     * @param ReflectionMethod $method 目标方法的反射对象
     * @param object $object 目标对象实例
     * @param array $arguments 方法参数数组
     * @param string $pointcut 切入点表达式
     * @param mixed $result 方法返回值（用于 After 通知）
     */
    public function __construct(
        protected readonly ReflectionClass $class,
        protected readonly ReflectionMethod $method,
        protected readonly object $object,
        protected array $arguments,
        protected readonly string $pointcut = '',
        protected readonly mixed $result = null
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getClass(): ReflectionClass
    {
        return $this->class;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): ReflectionMethod
    {
        return $this->method;
    }

    /**
     * {@inheritDoc}
     */
    public function getThis(): object
    {
        return $this->object;
    }

    /**
     * {@inheritDoc}
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * {@inheritDoc}
     */
    public function setArguments(array $args): void
    {
        $this->arguments = $args;
    }

    /**
     * {@inheritDoc}
     */
    public function getPointcut(): string
    {
        return $this->pointcut;
    }

    /**
     * 获取方法返回值
     *
     * 仅在 After 通知中有效，用于获取方法的返回值。
     *
     * @return mixed 方法返回值
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * 获取方法名
     *
     * 便捷方法，直接返回目标方法的名称。
     *
     * @return string 方法名
     */
    public function getMethodName(): string
    {
        return $this->method->getName();
    }

    /**
     * 获取类名
     *
     * 便捷方法，直接返回目标类的完整名称。
     *
     * @return string 类名
     */
    public function getClassName(): string
    {
        return $this->class->getName();
    }

    /**
     * 获取指定位置的参数
     *
     * 便捷方法，用于获取指定位置的参数值。
     *
     * @param int $index 参数位置索引（从0开始）
     * @param mixed $default 默认值
     * @return mixed 参数值或默认值
     */
    public function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }
}
