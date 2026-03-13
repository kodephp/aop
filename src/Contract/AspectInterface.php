<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

/**
 * 切面接口标记
 *
 * 所有切面类都需要实现此接口或使用 #[Aspect] 注解标记。
 * 此接口为标记接口，不包含任何方法定义。
 *
 * 使用示例：
 * ```php
 * // 方式一：实现接口
 * class LoggingAspect implements AspectInterface
 * {
 *     #[Before("execution(* App\Service\*->*(..))")]
 *     public function logBefore(JoinPoint $joinPoint): void
 *     {
 *         // 日志记录逻辑
 *     }
 * }
 *
 * // 方式二：使用注解（推荐）
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
 * @package Kode\Aop\Contract
 * @author Kode Team <382601296@qq.com>
 */
interface AspectInterface
{
}
