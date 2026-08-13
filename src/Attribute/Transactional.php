<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 声明式事务切面注解
 *
 * 直接标注在业务方法（或业务类）上，由库内置的
 * {@see \Kode\Aop\Aspect\TransactionalAspect} 自动在该方法外包裹一个事务：
 * 方法正常返回则提交，抛出异常则回滚。
 *
 * 事务能力来自 {@see \Kode\Aop\Contract\TransactionManagerInterface} 契约，
 * 框架无关——Laravel 可传 `DB::connection()` 适配器，原生场景可传
 * {@see \Kode\Aop\Runtime\PdoTransactionManager}。
 *
 * 使用示例：
 * ```php
 * use Kode\Aop\Attribute\Transactional;
 *
 * class OrderService
 * {
 *     #[Transactional]
 *     public function place(int $userId, int $productId): void
 *     {
 *         $this->account->deduct($userId);
 *         $this->inventory->reduce($productId);
 *     }
 * }
 * ```
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Transactional
{
    /**
     * @param string|null $name 事务标识，仅用于日志 / 诊断，不影响行为
     */
    public function __construct(
        public ?string $name = null
    ) {
    }
}
