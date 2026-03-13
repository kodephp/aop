<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 环绕通知注解
 *
 * 环绕目标方法执行，可以完全控制方法的执行流程。
 * 这是最强大的通知类型，可以：
 * - 在方法执行前后添加自定义逻辑
 * - 决定是否执行原方法
 * - 修改方法参数
 * - 修改返回值
 * - 捕获或转换异常
 *
 * 使用示例：
 * ```php
 * #[Aspect]
 * class TransactionAspect
 * {
 *     #[Around("execution(* App\Service\UserService->*(..))")]
 *     public function transactional(ProceedingJoinPoint $joinPoint): mixed
 *     {
 *         // 开始事务
 *         echo "开始事务\n";
 *
 *         try {
 *             // 执行原方法
 *             $result = $joinPoint->proceed();
 *
 *             // 提交事务
 *             echo "提交事务\n";
 *
 *             return $result;
 *         } catch (\Exception $e) {
 *             // 回滚事务
 *             echo "回滚事务\n";
 *             throw $e;
 *         }
 *     }
 * }
 * ```
 *
 * 注意：
 * - Around 通知必须接受 ProceedingJoinPoint 参数
 * - 必须调用 $joinPoint->proceed() 来执行原方法
 * - 应该返回原方法的返回值（或修改后的值）
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Around
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
