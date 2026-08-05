<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 异常通知注解
 *
 * 仅在目标方法抛出异常时执行，适用于异常上报、告警、审计等场景。
 * 通知执行完毕后异常会继续向上抛出（本通知不吞异常）。
 *
 * 使用示例：
 * ```php
 * #[Aspect]
 * class AlarmAspect
 * {
 *     #[AfterThrowing("execution(* App\Service\*->*(..))")]
 *     public function report(JoinPoint $joinPoint): void
 *     {
 *         $e = $joinPoint->getException();
 *         error_log($e?->getMessage() ?? '');
 *     }
 * }
 * ```
 *
 * 可通过 $throwable 参数限定只捕获特定类型的异常：
 * ```php
 * #[AfterThrowing("execution(* App\Service\*->*(..))", throwable: \RuntimeException::class)]
 * ```
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AfterThrowing
{
    /**
     * @param string $pointcut 切入点表达式，用于匹配目标方法
     * @param class-string<\Throwable> $throwable 只处理该类型（含子类）的异常
     */
    public function __construct(
        public string $pointcut,
        public string $throwable = \Throwable::class
    ) {
    }
}
