<?php

declare(strict_types=1);

namespace Kode\Aop\Aspect;

use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Cache;
use Kode\Aop\Contract\AspectInterface;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use Kode\Attributes\Attr;
use Kode\Attributes\MetaList;
use Psr\SimpleCache\CacheInterface;

/**
 * 内置声明式缓存切面
 *
 * 元切面（meta-aspect）：以 {@see Around} 拦截全部方法调用，命中目标方法上的
 * {@see Cache} 注解时按「方法 + 参数」缓存返回值；未命中则直接 proceed。
 *
 * 由 {@see \Kode\Aop\Provider\AopProvider} 在装配阶段注入 PSR-16 缓存后自动注册。
 * 缓存 key 默认由 `prefix + 类名::方法名 + 参数摘要` 组成，可经 {@see Cache::$key}
 * 指定固定 key（此时不再区分参数，慎用）。
 *
 * @package Kode\Aop\Aspect
 * @author Kode Team <382601296@qq.com>
 */
#[Aspect]
final class CachingAspect implements AspectInterface
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * 环绕全部方法：命中 #[Cache] 时走缓存，否则执行并回写缓存。
     */
    #[Around("execution(* *->*(..))")]
    public function around(ProceedingJoinPoint $jp): mixed
    {
        $cfg = $this->resolve($jp);

        if ($cfg === null) {
            return $jp->proceed();
        }

        $key = $cfg->key ?? $this->autoKey($cfg, $jp);

        if ($this->cache->has($key)) {
            /** @var mixed $cached */
            $cached = $this->cache->get($key);

            return $cached;
        }

        $result = $jp->proceed();
        $this->cache->set($key, $result, $cfg->ttl);

        return $result;
    }

    /**
     * 解析目标方法 / 类上的 #[Cache] 注解（方法级优先于类级）。
     */
    private function resolve(ProceedingJoinPoint $jp): ?Cache
    {
        $methodMeta = Attr::ofMethod($jp->getClassName(), $jp->getMethodName(), inherited: false)
            ->get(Cache::class);

        if ($methodMeta !== null) {
            $instance = $methodMeta->getInstance();

            return $instance instanceof Cache ? $instance : null;
        }

        $classMeta = Attr::of($jp->getClassName())->get(Cache::class);

        if ($classMeta !== null) {
            $instance = $classMeta->getInstance();

            return $instance instanceof Cache ? $instance : null;
        }

        return null;
    }

    /**
     * 由「前缀 + 方法签名 + 参数摘要」生成稳定且唯一的缓存 key。
     */
    private function autoKey(Cache $cfg, ProceedingJoinPoint $jp): string
    {
        $summary = hash('md5', var_export($jp->getArguments(), true));

        return "{$cfg->prefix}:{$jp->getSignature()}:{$summary}";
    }
}
