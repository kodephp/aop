<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 切入点注解
 *
 * 定义可复用的切入点表达式，可以在其他通知注解中引用。
 * 使用切入点注解可以将复杂的切入点表达式集中管理，提高代码可读性和可维护性。
 *
 * 使用示例：
 * ```php
 * #[Aspect]
 * class LoggingAspect
 * {
 *     // 定义切入点
 *     #[Pointcut("execution(* App\Service\UserService->*(..))")]
 *     public function userServicePointcut(): void
 *     {
 *         // 此方法体为空，仅用于定义切入点
 *     }
 *
 *     // 引用切入点
 *     #[Before("userServicePointcut()")]
 *     public function logBefore(JoinPoint $joinPoint): void
 *     {
 *         echo "UserService 方法被调用";
 *     }
 *
 *     // 组合切入点
 *     #[Pointcut("execution(* App\Service\OrderService->*(..))")]
 *     public function orderServicePointcut(): void
 *     {
 *     }
 *
 *     // 多切入点组合
 *     #[Before("userServicePointcut() || orderServicePointcut()")]
 *     public function logMultipleServices(JoinPoint $joinPoint): void
 *     {
 *         echo "UserService 或 OrderService 方法被调用";
 *     }
 * }
 * ```
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Pointcut
{
    /**
     * 构造函数
     *
     * @param string $expression 切入点表达式
     */
    public function __construct(
        public string $expression
    ) {
    }
}
