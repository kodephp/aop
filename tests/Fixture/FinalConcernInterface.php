<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

use Kode\Aop\Attribute\Cache;
use Kode\Aop\Attribute\Log;
use Kode\Aop\Attribute\Transactional;

/**
 * 声明式关注点测试目标接口（供 final 组合式代理实现）。
 *
 * @package Kode\Aop\Tests\Fixture
 */
interface FinalConcernInterface
{
    public function logged(int $x): int;

    public function heavy(int $x): int;

    public function transact(int $x): int;

    public function getComputeCalls(): int;
}
