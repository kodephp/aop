<?php

declare(strict_types=1);

namespace Kode\Aop\Advice;

use Closure;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * 通知执行器
 *
 * 按标准 AOP 时序编排一次方法调用上的全部通知：
 *
 * ```
 * Before(升序)
 *   └─ Around(升序，洋葱式嵌套)
 *        └─ 目标方法
 *   ├─ 正常返回 → AfterReturning(升序，可替换返回值)
 *   └─ 抛出异常 → AfterThrowing(升序，按异常类型过滤) → 继续抛出
 * After(降序，等价 finally)
 * ```
 *
 * 整个过程只创建一个连接点实例，因此各通知之间可以通过连接点传递状态。
 *
 * @package Kode\Aop\Advice
 * @author Kode Team <382601296@qq.com>
 */
final class AdviceExecutor
{
    /**
     * 执行织入逻辑
     *
     * @param AdviceSet $set 已匹配的通知集合
     * @param object $target 目标对象（代理实例）
     * @param ReflectionClass $class 原始类反射对象
     * @param ReflectionMethod $method 目标方法反射对象
     * @param array<int|string, mixed> $arguments 调用参数
     * @param Closure $invoker 真正执行目标方法的闭包，签名为 fn(mixed ...$args): mixed
     * @return mixed 最终返回值
     * @throws Throwable 目标方法或通知抛出的异常
     */
    public function execute(
        AdviceSet $set,
        object $target,
        ReflectionClass $class,
        ReflectionMethod $method,
        array $arguments,
        Closure $invoker
    ): mixed {
        $joinPoint = new ProceedingJoinPoint(
            $class,
            $method,
            $target,
            $arguments,
            $set->before[0]->pointcut ?? ($set->around[0]->pointcut ?? ''),
            $invoker
        );

        foreach ($set->before as $advice) {
            $advice->invoke($joinPoint);
        }

        $chain = $this->buildChain($set->around, $joinPoint, $invoker);

        try {
            $joinPoint->setResult($chain($joinPoint->getArguments()));

            foreach ($set->afterReturning as $advice) {
                $replacement = $advice->invoke($joinPoint);

                if ($replacement !== null) {
                    $joinPoint->setResult($replacement);
                }
            }

            return $joinPoint->getResult();
        } catch (Throwable $throwable) {
            $joinPoint->setException($throwable);

            foreach ($set->afterThrowing as $advice) {
                if ($advice->handles($throwable)) {
                    $advice->invoke($joinPoint);
                }
            }

            throw $throwable;
        } finally {
            foreach ($set->after as $advice) {
                $advice->invoke($joinPoint);
            }
        }
    }

    /**
     * 把 Around 通知编排成洋葱式调用链
     *
     * @param array<int, Advice> $arounds 已按优先级升序排列的环绕通知
     * @param ProceedingJoinPoint $joinPoint 共享连接点
     * @param Closure $invoker 目标方法调用闭包
     * @return Closure(array<int|string, mixed>): mixed 最外层入口
     */
    private function buildChain(array $arounds, ProceedingJoinPoint $joinPoint, Closure $invoker): Closure
    {
        $next = static fn(array $args): mixed => $invoker(...$args);

        for ($index = count($arounds) - 1; $index >= 0; $index--) {
            $advice = $arounds[$index];
            $inner = $next;

            $next = static function (array $args) use ($advice, $inner, $joinPoint): mixed {
                $joinPoint->setArguments($args);
                $joinPoint->pushProceed($inner);

                try {
                    return $advice->invoke($joinPoint);
                } finally {
                    $joinPoint->popProceed();
                }
            };
        }

        return $next;
    }
}
