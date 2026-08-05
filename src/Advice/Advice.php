<?php

declare(strict_types=1);

namespace Kode\Aop\Advice;

use Closure;
use Kode\Aop\Contract\JoinPointInterface;
use Kode\Aop\Pointcut\MatchContext;
use Throwable;

/**
 * 单条通知
 *
 * 把「切面对象 + 通知方法 + 通知类型 + 已编译的切入点匹配器 + 优先级」
 * 打包为一个不可变的值对象，供 AdviceRegistry 与 AdviceExecutor 使用。
 *
 * @package Kode\Aop\Advice
 * @author Kode Team <382601296@qq.com>
 */
final readonly class Advice
{
    /**
     * @param object $aspect 切面实例
     * @param string $aspectMethod 切面上的通知方法名
     * @param AdviceType $type 通知类型
     * @param string $pointcut 原始切入点表达式
     * @param int $order 优先级，数值越小越先执行
     * @param Closure(MatchContext): bool $matcher 已编译的切入点匹配闭包
     * @param class-string<Throwable> $throwable 仅 AfterThrowing 有效，限定异常类型
     */
    public function __construct(
        public object $aspect,
        public string $aspectMethod,
        public AdviceType $type,
        public string $pointcut,
        public int $order,
        public Closure $matcher,
        public string $throwable = Throwable::class
    ) {
    }

    /**
     * 判断该通知是否命中给定的目标方法
     */
    public function matches(MatchContext $context): bool
    {
        return ($this->matcher)($context);
    }

    /**
     * 执行通知方法
     *
     * @param JoinPointInterface $joinPoint 连接点
     * @return mixed 通知方法的返回值
     */
    public function invoke(JoinPointInterface $joinPoint): mixed
    {
        /** @var callable $callable */
        $callable = [$this->aspect, $this->aspectMethod];

        return $callable($joinPoint);
    }

    /**
     * 判断该异常通知是否应处理给定异常
     */
    public function handles(Throwable $throwable): bool
    {
        return $throwable instanceof $this->throwable;
    }

    /**
     * 返回可读的通知标识，便于调试
     */
    public function describe(): string
    {
        return sprintf('%s::%s [%s]', $this->aspect::class, $this->aspectMethod, $this->type->value);
    }
}
