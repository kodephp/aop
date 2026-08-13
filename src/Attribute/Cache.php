<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 声明式缓存切面注解
 *
 * 直接标注在业务方法（或业务类）上，由库内置的 {@see \Kode\Aop\Aspect\CachingAspect}
 * 自动按「方法 + 参数」缓存返回值，避免重复计算 / 重复查询。
 *
 * 缓存 key 默认由 `prefix + 类名::方法名 + 序列化参数` 生成；也可通过
 * {@see $key} 指定固定 key（此时忽略参数维度，慎用）。
 *
 * 使用示例：
 * ```php
 * use Kode\Aop\Attribute\Cache;
 *
 * class ProductService
 * {
 *     // 缓存 300 秒，key 自动包含方法参数
 *     #[Cache(ttl: 300, prefix: 'product')]
 *     public function detail(int $id): array
 *     {
 *         return $this->repository->find($id);
 *     }
 * }
 * ```
 *
 * 注意：返回值必须可被 `var_export` 序列化（PSR-16 要求），闭包、
 * 资源、循环引用等无法缓存。
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Cache
{
    /**
     * @param int $ttl 缓存有效期（秒），默认 60
     * @param string|null $key 自定义固定 key；null 时按「类名::方法名 + 参数」自动生成
     * @param string $prefix 自动 key 的前缀，便于按业务隔离与批量清理
     */
    public function __construct(
        public int $ttl = 60,
        public ?string $key = null,
        public string $prefix = 'aop'
    ) {
    }
}
