<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Kode\Aop\Contract\ProceedingJoinPointInterface;

/**
 * 继续执行连接点实现类
 *
 * 继承自 JoinPoint，专门用于 Around（环绕）通知。
 * 提供了 proceed() 方法，允许控制是否继续执行原方法。
 *
 * 这是 AOP 框架中最强大的连接点类型，可以完全控制目标方法的执行流程。
 *
 * @package Kode\Aop\Runtime
 * @author Kode Team <382601296@qq.com>
 * @see ProceedingJoinPointInterface
 */
class ProceedingJoinPoint extends JoinPoint implements ProceedingJoinPointInterface
{
    /**
     * 构造函数
     *
     * @param ReflectionClass $class 目标类的反射对象
     * @param ReflectionMethod $method 目标方法的反射对象
     * @param object $object 目标对象实例
     * @param array $arguments 方法参数数组
     * @param string $pointcut 切入点表达式
     * @param Closure $proceedClosure 原方法调用闭包
     */
    public function __construct(
        ReflectionClass $class,
        ReflectionMethod $method,
        object $object,
        array $arguments,
        string $pointcut,
        protected readonly Closure $proceedClosure
    ) {
        parent::__construct($class, $method, $object, $arguments, $pointcut);
    }

    /**
     * {@inheritDoc}
     *
     * 执行原方法，可以传入新的参数或使用原始参数。
     *
     * @param array $arguments 可选的方法参数，如果为空则使用原始参数
     * @return mixed 原方法的返回值
     */
    public function proceed(array $arguments = []): mixed
    {
        $args = $arguments !== [] ? $arguments : $this->arguments;
        return ($this->proceedClosure)(...$args);
    }

    /**
     * 执行原方法并传递命名参数
     *
     * 便捷方法，支持使用关联数组传递命名参数。
     *
     * @param array<string, mixed> $namedParams 命名参数数组
     * @return mixed 原方法的返回值
     */
    public function proceedWithNamedParams(array $namedParams): mixed
    {
        return ($this->proceedClosure)(...$namedParams);
    }

    /**
     * 获取原方法调用闭包
     *
     * 返回原方法的调用闭包，可用于延迟执行或传递给其他函数。
     *
     * @return Closure 原方法调用闭包
     */
    public function getProceedClosure(): Closure
    {
        return $this->proceedClosure;
    }
}
