<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

use Kode\Aop\Contract\TransactionManagerInterface;

/**
 * 假事务管理器（测试替身），记录 begin / commit / rollback 调用次数。
 *
 * @package Kode\Aop\Tests\Fixture
 */
final class FakeTransactionManager implements TransactionManagerInterface
{
    public int $begins = 0;

    public int $commits = 0;

    public int $rollbacks = 0;

    public function begin(): void
    {
        $this->begins++;
    }

    public function commit(): void
    {
        $this->commits++;
    }

    public function rollback(): void
    {
        $this->rollbacks++;
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
