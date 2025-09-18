<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use ReflectionClass;
use ReflectionMethod;
use Kode\Aop\Contract\JoinPointInterface;

/**
 * 连接点实现类
 * 
 * 封装了方法调用的上下文信息
 */
class JoinPoint implements JoinPointInterface
{
    /**
     * @var ReflectionClass 目标类的反射对象
     */
    protected ReflectionClass $class;

    /**
     * @var ReflectionMethod 目标方法的反射对象
     */
    protected ReflectionMethod $method;

    /**
     * @var object 目标对象实例
     */
    protected object $object;

    /**
     * @var array 方法参数
     */
    protected array $arguments;

    /**
     * @var string 切入点表达式
     */
    protected string $pointcut;

    /**
     * 构造函数
     *
     * @param ReflectionClass $class 目标类的反射对象
     * @param ReflectionMethod $method 目标方法的反射对象
     * @param object $object 目标对象实例
     * @param array $arguments 方法参数
     * @param string $pointcut 切入点表达式
     */
    public function __construct(
        ReflectionClass $class,
        ReflectionMethod $method,
        object $object,
        array $arguments,
        string $pointcut
    ) {
        $this->class = $class;
        $this->method = $method;
        $this->object = $object;
        $this->arguments = $arguments;
        $this->pointcut = $pointcut;
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
}