<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

use Kode\Aop\Attribute\Cache;
use Kode\Aop\Attribute\Log;
use Kode\Aop\Attribute\LogLevel;
use Kode\Aop\Attribute\Transactional;

/**
 * 声明式关注点测试目标（非 final，走继承式代理）。
 *
 * @package Kode\Aop\Tests\Fixture
 */
class ConcernService
{
    public int $computeCalls = 0;

    public function getComputeCalls(): int
    {
        return $this->computeCalls;
    }

    public function plain(int $x): int
    {
        return $x * 2;
    }

    #[Log(level: LogLevel::Info, logArgs: true, logResult: true)]
    public function logged(int $x): int
    {
        return $x + 1;
    }

    #[Log]
    public function boom(): void
    {
        throw new \RuntimeException('fail');
    }

    #[Cache(ttl: 120, prefix: 'cs')]
    public function heavy(int $x): int
    {
        $this->computeCalls++;

        return $x * 10;
    }

    #[Transactional(name: 'order')]
    public function transact(int $x): int
    {
        return $x;
    }

    #[Transactional]
    public function failTx(): void
    {
        throw new \RuntimeException('tx-fail');
    }
}
