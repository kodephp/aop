<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 前置通知注解
 *
 * 在目标方法执行前执行，可以修改方法参数或执行其他预处理逻辑。
 * 前置通知无法阻止目标方法的执行，如需控制执行流程请使用 Around 通知。
 *
 * 使用示例：
 * ```php
 * #[Aspect]
 * class LoggingAspect
 * {
 *     #[Before("execution(* App\Service\UserService->createUser(..))")]
 *     public function logBefore(JoinPoint $joinPoint): void
 *     {
 *         $args = $joinPoint->getArguments();
 *         echo "准备创建用户: " . json_encode($args[0]);
 *     }
 * }
 * ```
 *
 * 切入点表达式语法：
 * - `execution(* App\Service\*->send*(..))` - 匹配 send 开头的方法
 * - `execution(public App\Payment\*->process())` - 匹配特定方法
 * - `within(App\Controller\*)` - 匹配某个命名空间下所有类
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Before
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
