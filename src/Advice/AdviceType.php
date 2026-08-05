<?php

declare(strict_types=1);

namespace Kode\Aop\Advice;

/**
 * 通知类型枚举
 *
 * 描述一个通知在目标方法生命周期中的织入位置。
 *
 * 执行时序：
 * ```
 * Before  →  Around(外) → … → Around(内) → 目标方法
 *                                        ↓ 正常返回
 *                                   AfterReturning
 *                                        ↓ 抛出异常
 *                                   AfterThrowing
 *                                        ↓ 无论如何
 *                                      After
 * ```
 *
 * @package Kode\Aop\Advice
 * @author Kode Team <382601296@qq.com>
 */
enum AdviceType: string
{
    /** 前置通知 */
    case Before = 'before';

    /** 环绕通知 */
    case Around = 'around';

    /** 返回后通知（仅正常返回时执行） */
    case AfterReturning = 'afterReturning';

    /** 异常通知（仅抛出异常时执行） */
    case AfterThrowing = 'afterThrowing';

    /** 后置通知（无论成功失败均执行） */
    case After = 'after';

    /**
     * 该类型是否按优先级倒序执行
     *
     * After 系列通知遵循"先进后出"的栈语义，优先级高的最后执行。
     */
    public function isReversed(): bool
    {
        return $this === self::After;
    }
}
