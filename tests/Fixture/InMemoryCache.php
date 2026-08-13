<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

use Psr\SimpleCache\CacheInterface;

/**
 * 内存版 PSR-16 缓存（测试替身）
 *
 * @package Kode\Aop\Tests\Fixture
 */
final class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->items[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = $this->get($key, $default);
        }

        return $out;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }
}
