<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use Closure;
use Kode\Aop\Contract\ProceedingJoinPointInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * 可继续执行的连接点
 *
 * 继承自 {@see JoinPoint}，为 Around 通知提供 proceed() 能力。
 *
 * v3 引入了「继续执行栈」：当同一个方法上存在多个 Around 通知时，
 * 内核会把它们编排成洋葱式的调用链，外层通知调用 proceed() 实际上
 * 是在调用下一层通知，最内层才真正执行目标方法。这修复了 v2 中
 * 「多个 Around 只有优先级最高的那个会生效」的缺陷。
 *
 * @package Kode\Aop\Runtime
 * @author Kode Team <382601296@qq.com>
 * @see ProceedingJoinPointInterface
 */
class ProceedingJoinPoint extends JoinPoint implements ProceedingJoinPointInterface
{
    /**
     * 继续执行栈，栈顶为当前 Around 通知应调用的下一环
     *
     * @var array<int, Closure(array<int|string, mixed>): mixed>
     */
    private array $proceedStack = [];

    /**
     * @param ReflectionClass $class 目标类的反射对象
     * @param ReflectionMethod $method 目标方法的反射对象
     * @param object $object 目标对象实例
     * @param array<int|string, mixed> $arguments 方法参数数组
     * @param string $pointcut 切入点表达式
     * @param Closure $proceedClosure 最内层的目标方法调用闭包
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
     * @param array<int|string, mixed> $arguments 可选的新参数，为空则沿用当前参数
     */
    #[\Override]
    public function proceed(array $arguments = []): mixed
    {
        if ($arguments !== []) {
            $this->arguments = $arguments;
        }

        $next = end($this->proceedStack);

        if ($next === false) {
            return ($this->proceedClosure)(...$this->arguments);
        }

        return $next($this->arguments);
    }

    /**
     * 压入下一环调用闭包
     *
     * @param Closure(array<int|string, mixed>): mixed $next
     * @internal 由内核编排调用链时使用
     */
    public function pushProceed(Closure $next): void
    {
        $this->proceedStack[] = $next;
    }

    /**
     * 弹出栈顶调用闭包
     *
     * @internal 由内核编排调用链时使用
     */
    public function popProceed(): void
    {
        array_pop($this->proceedStack);
    }

    /**
     * 以命名参数继续执行原方法
     *
     * @param array<string, mixed> $namedParams 命名参数数组
     */
    public function proceedWithNamedParams(array $namedParams): mixed
    {
        return ($this->proceedClosure)(...$namedParams);
    }

    /**
     * 获取最内层的目标方法调用闭包
     */
    public function getProceedClosure(): Closure
    {
        return $this->proceedClosure;
    }
}
