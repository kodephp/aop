<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use Kode\Aop\Contract\TransactionManagerInterface;

/**
 * 基于原生 PDO 的默认事务管理器
 *
 * 开箱即用的 {@see TransactionManagerInterface} 实现，无需任何 ORM 即可让
 * {@see \Kode\Aop\Attribute\Transactional} 切面工作。框架场景（Laravel / Doctrine）
 * 应改用对应连接层适配器以复用既有的连接与事务栈。
 *
 * @package Kode\Aop\Runtime
 * @author Kode Team <382601296@qq.com>
 */
final class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(
        private readonly \PDO $pdo
    ) {
    }

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function transactional(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }
}
