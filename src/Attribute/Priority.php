<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 优先级注解
 *
 * 控制切面方法的执行顺序，数字越小优先级越高，越先执行。
 * 当多个切面作用于同一个方法时，可以通过优先级控制执行顺序。
 *
 * 执行顺序规则：
 * - Before 通知：优先级高的先执行
 * - After 通知：优先级低的后执行（即先注册的后执行，形成栈结构）
 * - Around 通知：按优先级嵌套执行
 *
 * 使用示例：
 * ```php
 * #[Aspect]
 * class PriorityAspect
 * {
 *     #[Before("execution(* App\Service\*->*(..))")]
 *     #[Priority(10)]  // 高优先级，先执行
 *     public function first(JoinPoint $joinPoint): void
 *     {
 *         echo "第一个执行\n";
 *     }
 *
 *     #[Before("execution(* App\Service\*->*(..))")]
 *     #[Priority(20)]  // 低优先级，后执行
 *     public function second(JoinPoint $joinPoint): void
 *     {
 *         echo "第二个执行\n";
 *     }
 *
 *     #[Before("execution(* App\Service\*->*(..))")]
 *     #[Priority(-100)]  // 负数优先级，最优先执行
 *     public function highest(JoinPoint $joinPoint): void
 *     {
 *         echo "最高优先级执行\n";
 *     }
 * }
 * ```
 *
 * 默认优先级：
 * - 如果未指定 Priority 注解，默认优先级为 0
 * - 建议使用 10 的倍数作为优先级，便于后续插入新的切面
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Priority
{
    /**
     * 默认优先级常量
     */
    public const HIGHEST = -1000;
    public const HIGH = -100;
    public const NORMAL = 0;
    public const LOW = 100;
    public const LOWEST = 1000;

    /**
     * 构造函数
     *
     * @param int $value 优先级值，数字越小优先级越高
     */
    public function __construct(
        public int $value
    ) {
    }
}
