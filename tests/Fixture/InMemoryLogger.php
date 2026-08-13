<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * 内存版 PSR-3 日志器（测试替身）
 *
 * @package Kode\Aop\Tests\Fixture
 */
final class InMemoryLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
