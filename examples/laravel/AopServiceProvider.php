<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kode\Aop\Contract\TransactionManagerInterface;
use Kode\Aop\Provider\AopProvider;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Laravel 集成示例
 *
 * 仅作示范，不纳入库自动加载（避免强制依赖 illuminate）。把本文件放到
 * `app/Providers/` 并在 `config/app.php` 的 providers 中注册即可。
 *
 * 关键思路：用一个匿名适配器把 Laravel 的 DB 连接桥接成库要求的
 * {@see TransactionManagerInterface}，其余日志 / 缓存直接复用容器里的
 * PSR-3 / PSR-16 实现，最后交给框架无关的 {@see AopProvider} 统一装配。
 */
final class AopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 把 Laravel 数据库连接适配为库的事务管理器契约
        $this->app->singleton(TransactionManagerInterface::class, function () {
            $connection = $this->app->make('db')->connection();

            return new class($connection) implements TransactionManagerInterface {
                public function __construct(
                    private $connection
                ) {
                }

                public function begin(): void
                {
                    $this->connection->beginTransaction();
                }

                public function commit(): void
                {
                    $this->connection->commit();
                }

                public function rollback(): void
                {
                    $this->connection->rollBack();
                }

                public function transactional(callable $callback): mixed
                {
                    return $this->connection->transaction($callback);
                }
            };
        });

        // 统一装配：声明式关注点 + 自定义切面
        $this->app->singleton(AopProvider::class, function () {
            return AopProvider::create()
                ->withLogger($this->app->make(LoggerInterface::class))
                ->withCache($this->app->make(CacheInterface::class))
                ->withTransactionManager($this->app->make(TransactionManagerInterface::class))
                ->withCacheDir($this->app->storagePath('framework/aop'))
                ->register(\App\Aspect\CustomAuditAspect::class);
        });
    }

    public function boot(): void
    {
        $this->app->make(AopProvider::class)->boot();
    }
}
