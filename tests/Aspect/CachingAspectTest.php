<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Aspect;

use Kode\Aop\Aop;
use Kode\Aop\Provider\AopProvider;
use Kode\Aop\Tests\Fixture\ConcernService;
use Kode\Aop\Tests\Fixture\FinalConcernInterface;
use Kode\Aop\Tests\Fixture\FinalConcernService;
use Kode\Aop\Tests\Fixture\InMemoryCache;
use PHPUnit\Framework\TestCase;

/**
 * 声明式 #[Cache] 切面测试（含 final 组合式代理场景）。
 *
 * @package Kode\Aop\Tests\Aspect
 * @author Kode Team <382601296@qq.com>
 */
final class CachingAspectTest extends TestCase
{
    protected function setUp(): void
    {
        Aop::reset();
    }

    public function testCachesResult(): void
    {
        $cache = new InMemoryCache();
        AopProvider::create()->withCache($cache)->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);
        $this->assertSame(40, $svc->heavy(4));
        $this->assertSame(40, $svc->heavy(4)); // 命中缓存，不再执行原方法

        $this->assertSame(1, $svc->getComputeCalls());
    }

    public function testDifferentArgsDifferentKeys(): void
    {
        $cache = new InMemoryCache();
        AopProvider::create()->withCache($cache)->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);
        $this->assertSame(40, $svc->heavy(4));
        $this->assertSame(50, $svc->heavy(5));

        $this->assertSame(2, $svc->getComputeCalls());
    }

    public function testWorksOnFinalClass(): void
    {
        $cache = new InMemoryCache();
        AopProvider::create()->withCache($cache)->boot();

        /** @var FinalConcernInterface $proxy */
        $proxy = Aop::proxy(FinalConcernService::class);
        $this->assertSame(30, $proxy->heavy(3));
        $this->assertSame(30, $proxy->heavy(3));

        $real = $proxy->heavy(3); // 二次命中缓存，computeCalls 不增长
        $this->assertSame(30, $real);
        $this->assertSame(1, $proxy->getComputeCalls());
    }
}
