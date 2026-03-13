<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 后置通知注解
 *
 * 在目标方法执行后执行（无论是否抛出异常），类似于 finally 块。
 * 适用于资源清理、日志记录等场景。
 *
 * 使用示例：
 * ```php
 * #[Aspect]
 * class LoggingAspect
 * {
 *     #[After("execution(* App\Service\UserService->createUser(..))")]
 *     public function logAfter(JoinPoint $joinPoint): void
 *     {
 *         echo "用户创建操作已完成";
 *     }
 * }
 * ```
 *
 * 注意：
 * - After 通知会在方法执行完成后执行，即使方法抛出异常也会执行
 * - 如果需要只在成功时执行，请考虑使用 AfterReturning 通知（未来版本支持）
 * - 如果需要只在异常时执行，请考虑使用 AfterThrowing 通知（未来版本支持）
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class After
{
    /**
     * 构造函数
     *
     * @param string $pointcut 切入点表达式，用于匹配目标方法
     */
    public function __construct(
        public string $pointcut
    ) {
    }
}
