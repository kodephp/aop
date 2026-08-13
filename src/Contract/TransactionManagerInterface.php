<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

/**
 * 事务管理器契约
 *
 * 框架无关的事务抽象，声明式 {@see \Kode\Aop\Attribute\Transactional} 切面
 * 仅依赖此接口，从而与具体 ORM / 数据库层解耦。
 *
 * 适配示例：
 * - Laravel：用 `Illuminate\Database\ConnectionInterface` 包装 `beginTransaction/
 *   commit/rollBack` 实现一个适配器；
 * - 原生 PDO：直接使用库内置的 {@see \Kode\Aop\Runtime\PdoTransactionManager}。
 *
 * @package Kode\Aop\Contract
 * @author Kode Team <382601296@qq.com>
 */
interface TransactionManagerInterface
{
    /**
     * 开启一个事务。
     */
    public function begin(): void;

    /**
     * 提交当前事务。
     */
    public function commit(): void;

    /**
     * 回滚当前事务。
     */
    public function rollback(): void;

    /**
     * 在事务上下文中执行回调，成功提交、异常回滚并重新抛出。
     *
     * @template T
     * @param callable(): T $callback 业务回调
     * @return T 回调的返回值
     */
    public function transactional(callable $callback): mixed;
}
