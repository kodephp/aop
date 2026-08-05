<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

/**
 * 另一个不相关的服务，用作否定用例
 */
class OrderService
{
    public function save(): void
    {
    }

    public function process(int $n): int
    {
        return $n;
    }
}
