<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 切面注解
 *
 * 用于标记一个类为切面类，切面类中包含各种通知方法（Before、After、Around）。
 *
 * 使用示例：
 * ```php
 * #[Aspect]
 * class LoggingAspect
 * {
 *     #[Before("execution(* App\Service\*->*(..))")]
 *     public function logBefore(JoinPoint $joinPoint): void
 *     {
 *         // 日志记录逻辑
 *     }
 * }
 * ```
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Aspect
{
    /**
     * 构造函数
     *
     * @param int $priority 切面优先级，数字越小优先级越高（默认为0）
     * @param bool $enabled 是否启用此切面（默认为true）
     */
    public function __construct(
        public int $priority = 0,
        public bool $enabled = true
    ) {
    }
}
