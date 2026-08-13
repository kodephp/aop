<?php

declare(strict_types=1);

namespace Kode\Aop\Aspect;

use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Transactional;
use Kode\Aop\Contract\AspectInterface;
use Kode\Aop\Contract\TransactionManagerInterface;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use Kode\Attributes\Attr;

/**
 * 内置声明式事务切面
 *
 * 元切面（meta-aspect）：以 {@see Around} 拦截全部方法调用，命中目标方法上的
 * {@see Transactional} 注解时用注入的 {@see TransactionManagerInterface} 包裹事务，
 * 方法正常返回则提交，抛出异常则回滚。
 *
 * 由 {@see \Kode\Aop\Provider\AopProvider} 在装配阶段注入事务管理器后自动注册。
 *
 * @package Kode\Aop\Aspect
 * @author Kode Team <382601296@qq.com>
 */
#[Aspect]
final class TransactionalAspect implements AspectInterface
{
    public function __construct(
        private readonly TransactionManagerInterface $transactionManager
    ) {
    }

    /**
     * 环绕全部方法：命中 #[Transactional] 时包裹事务上下文。
     */
    #[Around("execution(* *->*(..))")]
    public function around(ProceedingJoinPoint $jp): mixed
    {
        if ($this->resolve($jp) === null) {
            return $jp->proceed();
        }

        return $this->transactionManager->transactional(
            static fn(): mixed => $jp->proceed()
        );
    }

    /**
     * 解析目标方法 / 类上的 #[Transactional] 注解（方法级优先于类级）。
     */
    private function resolve(ProceedingJoinPoint $jp): ?Transactional
    {
        $methodMeta = Attr::ofMethod($jp->getClassName(), $jp->getMethodName(), inherited: false)
            ->get(Transactional::class);

        if ($methodMeta !== null) {
            $instance = $methodMeta->getInstance();

            return $instance instanceof Transactional ? $instance : null;
        }

        $classMeta = Attr::of($jp->getClassName())->get(Transactional::class);

        if ($classMeta !== null) {
            $instance = $classMeta->getInstance();

            return $instance instanceof Transactional ? $instance : null;
        }

        return null;
    }
}
