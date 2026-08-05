<?php

declare(strict_types=1);

namespace Kode\Aop\Advice;

/**
 * 通知集合
 *
 * 表示某个「目标类::目标方法」上所有已命中并排好序的通知。
 * 该对象由 AdviceRegistry 计算一次后缓存，运行期直接复用。
 *
 * @package Kode\Aop\Advice
 * @author Kode Team <382601296@qq.com>
 */
final readonly class AdviceSet
{
    /**
     * @param array<int, Advice> $before 前置通知（升序）
     * @param array<int, Advice> $around 环绕通知（升序，第一个在最外层）
     * @param array<int, Advice> $afterReturning 返回后通知（升序）
     * @param array<int, Advice> $afterThrowing 异常通知（升序）
     * @param array<int, Advice> $after 后置通知（降序）
     */
    public function __construct(
        public array $before = [],
        public array $around = [],
        public array $afterReturning = [],
        public array $afterThrowing = [],
        public array $after = []
    ) {
    }

    /**
     * 空集合单例式构造
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * 是否不含任何通知
     */
    public function isEmpty(): bool
    {
        return $this->before === []
            && $this->around === []
            && $this->afterReturning === []
            && $this->afterThrowing === []
            && $this->after === [];
    }

    /**
     * 是否存在环绕通知
     */
    public function hasAround(): bool
    {
        return $this->around !== [];
    }

    /**
     * 通知总数
     */
    public function count(): int
    {
        return count($this->before)
            + count($this->around)
            + count($this->afterReturning)
            + count($this->afterThrowing)
            + count($this->after);
    }

    /**
     * 获取全部通知（按类型分组展开）
     *
     * @return array<int, Advice>
     */
    public function all(): array
    {
        return [
            ...$this->before,
            ...$this->around,
            ...$this->afterReturning,
            ...$this->afterThrowing,
            ...$this->after,
        ];
    }
}
