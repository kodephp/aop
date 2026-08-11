<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

/**
 * final 类，实现 GreeterInterface
 *
 * 在 v3.1 及之前，final 类无法被代理（代理类需 extends 它）；
 * v3.2 起改用组合式代理后，final 类可被正常织入。
 */
final class FinalGreeter implements GreeterInterface
{
    public function __construct(
        public string $name = 'world'
    ) {
    }

    public function greet(string $name): string
    {
        return "Hello, {$name}";
    }

    public function farewell(string $name): void
    {
    }

    final public function fixed(): string
    {
        return 'fixed-original';
    }

    public function whoami(): string
    {
        return $this->name;
    }
}
