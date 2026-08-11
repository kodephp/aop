<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

/**
 * final 类，且不实现任何接口
 *
 * 用于验证「无接口 final 类」也能通过组合式代理 + __call 委派被织入。
 */
final class FinalPlain
{
    public function __construct(
        public string $label = 'plain'
    ) {
    }

    public function shout(string $message): string
    {
        return strtoupper($message);
    }
}
