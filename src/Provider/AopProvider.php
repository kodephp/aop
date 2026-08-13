<?php

declare(strict_types=1);

namespace Kode\Aop\Provider;

use Kode\Aop\Aop;
use Kode\Aop\Aspect\CachingAspect;
use Kode\Aop\Aspect\LoggingAspect;
use Kode\Aop\Aspect\TransactionalAspect;
use Kode\Aop\Contract\TransactionManagerInterface;
use Kode\Aop\Runtime\AspectKernel;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * 框架无关的 AOP 装配器（Provider）
 *
 * 把「声明式关注点」（日志 / 缓存 / 事务）与用户自定义切面统一装配进内核，
 * 屏蔽底层 {@see AspectKernel} 的样板代码。特性：
 *
 * - 注入 PSR-3 日志器 → 自动注册内置 {@see LoggingAspect}
 * - 注入 PSR-16 缓存 → 自动注册内置 {@see CachingAspect}
 * - 注入事务管理器 → 自动注册内置 {@see TransactionalAspect}
 * - 未注入某依赖时，对应内置切面不注册（零开销、按需启用）
 * - 通过 {@see register()} 追加任意用户切面（实例或类名）
 *
 * 用法：
 * ```php
 * use Kode\Aop\Provider\AopProvider;
 *
 * AopProvider::create()
 *     ->withLogger($logger)
 *     ->withCache($cache)
 *     ->withTransactionManager(new \Kode\Aop\Runtime\PdoTransactionManager($pdo))
 *     ->withCacheDir(__DIR__ . '/runtime/aop')
 *     ->register(CustomAuditAspect::class)
 *     ->boot();
 *
 * $userService = Aop::proxy(UserService::class);
 * ```
 *
 * @package Kode\Aop\Provider
 * @author Kode Team <382601296@qq.com>
 */
final class AopProvider
{
    private ?LoggerInterface $logger = null;

    private ?CacheInterface $cache = null;

    private ?TransactionManagerInterface $transactionManager = null;

    /**
     * 用户自定义切面（实例或类名）
     *
     * @var array<int, object|class-string>
     */
    private array $aspects = [];

    private ?string $cacheDir = null;

    private bool $enabled = true;

    /**
     * 创建装配器实例（流式入口）。
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * 注入 PSR-3 日志器，启用声明式 #[Log] 切面。
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * 注入 PSR-16 缓存，启用声明式 #[Cache] 切面。
     */
    public function withCache(CacheInterface $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * 注入事务管理器，启用声明式 #[Transactional] 切面。
     */
    public function withTransactionManager(TransactionManagerInterface $transactionManager): self
    {
        $this->transactionManager = $transactionManager;

        return $this;
    }

    /**
     * 设置代理类文件缓存目录（落盘后可被 OPcache 加速）。
     */
    public function withCacheDir(?string $cacheDir): self
    {
        $this->cacheDir = $cacheDir;

        return $this;
    }

    /**
     * 总开关：false 时内核禁用，代理方法直接透传（便于压测 / 排障）。
     */
    public function enable(bool $on = true): self
    {
        $this->enabled = $on;

        return $this;
    }

    /**
     * 追加用户自定义切面（实例或类名）。
     *
     * @param object|class-string $aspect
     */
    public function register(object|string $aspect): self
    {
        $this->aspects[] = $aspect;

        return $this;
    }

    /**
     * 装配并启动内核。
     *
     * 按「用户切面 + 已注入依赖对应的内置声明式切面」顺序注册，返回内核单例。
     */
    public function boot(): AspectKernel
    {
        $aspects = $this->aspects;

        if ($this->logger !== null) {
            $aspects[] = new LoggingAspect($this->logger);
        }
        if ($this->cache !== null) {
            $aspects[] = new CachingAspect($this->cache);
        }
        if ($this->transactionManager !== null) {
            $aspects[] = new TransactionalAspect($this->transactionManager);
        }

        $kernel = Aop::boot($aspects, $this->cacheDir);

        if (!$this->enabled) {
            $kernel->disable();
        }

        return $kernel;
    }
}
