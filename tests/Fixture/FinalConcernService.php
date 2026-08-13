<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

use Kode\Aop\Attribute\Cache;
use Kode\Aop\Attribute\Log;
use Kode\Aop\Attribute\LogLevel;
use Kode\Aop\Attribute\Transactional;

/**
 * 声明式关注点测试目标（final，走组合式代理）。
 *
 * @package Kode\Aop\Tests\Fixture
 */
final class FinalConcernService implements FinalConcernInterface
{
    public int $computeCalls = 0;

    public function getComputeCalls(): int
    {
        return $this->computeCalls;
    }

    #[Log(level: LogLevel::Info, logArgs: true, logResult: true)]
    public function logged(int $x): int
    {
        return $x + 1;
    }

    #[Cache(ttl: 120, prefix: 'fcs')]
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
}
