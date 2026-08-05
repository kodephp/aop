<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 返回后通知注解
 *
 * 仅在目标方法**正常返回**（未抛出异常）后执行，可以读取甚至替换返回值。
 * 与 #[After] 的区别在于：#[After] 无论成功失败都会执行，而本通知只在成功时执行。
 *
 * 使用示例：
 * ```php
 * #[Aspect]
 * class CacheAspect
 * {
 *     #[AfterReturning("execution(* App\Service\UserService->getUser(..))")]
 *     public function cacheResult(JoinPoint $joinPoint): void
 *     {
 *         $value = $joinPoint->getResult();
 *         // 写入缓存……
 *     }
 * }
 * ```
 *
 * 替换返回值：通知方法返回非 null 值时，将覆盖原方法的返回值。
 * 若需保持原值，请让通知方法返回 null 或声明为 void。
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AfterReturning
{
    /**
     * @param string $pointcut 切入点表达式，用于匹配目标方法
     */
    public function __construct(
        public string $pointcut
    ) {
    }
}
