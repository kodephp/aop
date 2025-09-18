<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use ReflectionClass;
use ReflectionMethod;
use Kode\Aop\Contract\ProceedingJoinPointInterface;

/**
 * 继续执行连接点实现类
 * 
 * 用于 Around 通知，可以控制是否继续执行原方法
 */
class ProceedingJoinPoint extends JoinPoint implements ProceedingJoinPointInterface
{
    /**
     * @var callable 原方法调用器
     */
    protected $originalMethodCaller;

    /**
     * 构造函数
     *
     * @param ReflectionClass $class 目标类的反射对象
     * @param ReflectionMethod $method 目标方法的反射对象
     * @param object $object 目标对象实例
     * @param array $arguments 方法参数
     * @param string $pointcut 切入点表达式
     * @param callable $originalMethodCaller 原方法调用器
     */
    public function __construct(
        ReflectionClass $class,
        ReflectionMethod $method,
        object $object,
        array $arguments,
        string $pointcut,
        callable $originalMethodCaller
    ) {
        parent::__construct($class, $method, $object, $arguments, $pointcut);
        $this->originalMethodCaller = $originalMethodCaller;
    }

    /**
     * {@inheritDoc}
     */
    public function proceed(array $arguments = []): mixed
    {
        $args = $arguments ?: $this->arguments;
        return ($this->originalMethodCaller)(...$args);
    }
}