<?php

declare(strict_types=1);

namespace Kode\Aop;

use Kode\Aop\Advice\AdviceSet;
use Kode\Aop\Exception\AopException;
use Kode\Aop\Runtime\AspectKernel;

/**
 * AOP 门面
 *
 * 对 {@see AspectKernel} 单例的静态包装，让常见用法变成一行代码：
 *
 * ```php
 * use Kode\Aop\Aop;
 *
 * Aop::boot([LoggingAspect::class, TransactionAspect::class], __DIR__ . '/runtime/aop');
 *
 * $service = Aop::proxy(UserService::class);
 * $service->getUser(1);
 * ```
 *
 * 也可以直接吃配置数组（结构见 config/aop.php）：
 *
 * ```php
 * Aop::bootFromConfig(require __DIR__ . '/config/aop.php');
 * ```
 *
 * @package Kode\Aop
 * @author Kode Team <382601296@qq.com>
 */
final class Aop
{
    private function __construct()
    {
    }

    /**
     * 获取内核单例
     */
    public static function kernel(): AspectKernel
    {
        return AspectKernel::getInstance();
    }

    /**
     * 一步完成「注册切面 + 设置缓存目录 + 初始化」
     *
     * @param array<int, object|class-string> $aspects 切面实例或类名
     * @param string|null $cacheDir 代理类文件缓存目录
     * @throws AopException 切面非法时抛出
     */
    public static function boot(array $aspects = [], ?string $cacheDir = null): AspectKernel
    {
        $kernel = self::kernel();
        $kernel->setCacheDir($cacheDir);
        $kernel->registerAspects($aspects);
        $kernel->init();

        return $kernel;
    }

    /**
     * 按配置数组启动
     *
     * 可识别的键：`enable`、`aspects`、`cache.path`。
     *
     * @param array<string, mixed> $config 配置数组
     * @throws AopException 切面非法时抛出
     */
    public static function bootFromConfig(array $config): AspectKernel
    {
        /** @var array<int, object|class-string> $aspects */
        $aspects = $config['aspects'] ?? [];
        $cache = $config['cache'] ?? [];
        $cacheDir = is_array($cache) ? ($cache['path'] ?? null) : null;

        $kernel = self::boot($aspects, is_string($cacheDir) ? $cacheDir : null);

        if (array_key_exists('enable', $config) && $config['enable'] === false) {
            $kernel->disable();
        }

        return $kernel;
    }

    /**
     * 注册切面
     *
     * @param object|class-string $aspect 切面实例或类名
     * @throws AopException 切面非法时抛出
     */
    public static function register(object|string $aspect): void
    {
        self::kernel()->registerAspects([$aspect]);
    }

    /**
     * 创建代理实例
     *
     * @param class-string $className 目标类名
     * @param array<int|string, mixed> $constructorArgs 目标类构造参数
     * @throws AopException 目标类无法被代理时抛出
     */
    public static function proxy(string $className, array $constructorArgs = []): object
    {
        return self::kernel()->getProxy($className, $constructorArgs);
    }

    /**
     * 把已有实例包装为代理
     */
    public static function wrap(object $instance): object
    {
        return self::kernel()->wrap($instance);
    }

    /**
     * 查询某个方法上命中的通知集合，便于调试
     */
    public static function advicesFor(string $className, string $methodName): AdviceSet
    {
        return self::kernel()->resolveAdvices($className, $methodName);
    }

    /**
     * 获取诊断信息
     *
     * @return array<string, mixed>
     */
    public static function diagnostics(): array
    {
        return self::kernel()->diagnostics();
    }

    /**
     * 重置内核（主要用于测试）
     */
    public static function reset(): void
    {
        AspectKernel::resetInstance();
    }
}
