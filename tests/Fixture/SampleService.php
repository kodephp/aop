<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

/**
 * 通用服务对象，用于切入点表达式与代理生成测试
 */
class SampleService
{
    public function __construct(
        public string $tag = 'default'
    ) {
    }

    public function create(string $name): int
    {
        return strlen($name);
    }

    /**
     * @return array<string, int>
     */
    public function find(int $id): array
    {
        return ['id' => $id];
    }

    public function delete(int $id): bool
    {
        return true;
    }

    public function mixedTypes(int|string $x): string
    {
        return (string) $x;
    }

    protected function secret(): void
    {
    }

    public static function utility(): void
    {
    }

    final public function locked(): void
    {
    }
}
