<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Provider;

use Kode\Aop\Aop;
use Kode\Aop\Provider\AopProvider;
use Kode\Aop\Runtime\AspectKernel;
use Kode\Aop\Tests\Fixture\ConcernService;
use Kode\Aop\Tests\Fixture\FinalConcernInterface;
use Kode\Aop\Tests\Fixture\FinalConcernService;
use Kode\Aop\Tests\Fixture\FakeTransactionManager;
use Kode\Aop\Tests\Fixture\InMemoryCache;
use Kode\Aop\Tests\Fixture\InMemoryLogger;
use PHPUnit\Framework\TestCase;

/**
 * AopProvider 装配器测试。
 *
 * @package Kode\Aop\Tests\Provider
 * @author Kode Team <382601296@qq.com>
 */
final class AopProviderTest extends TestCase
{
    protected function setUp(): void
    {
        Aop::reset();
    }

    public function testRegistersOnlyProvidedConcerns(): void
    {
        // 未注入任何依赖 → 不注册内置声明式切面
        $kernel = AopProvider::create()->boot();

        $this->assertInstanceOf(AspectKernel::class, $kernel);
        $this->assertCount(0, $kernel->getRegisteredAspects());
    }

    public function testRegistersConcernsForProvidedDeps(): void
    {
        $kernel = AopProvider::create()
            ->withLogger(new InMemoryLogger())
            ->withCache(new InMemoryCache())
            ->withTransactionManager(new FakeTransactionManager())
            ->boot();

        // 三个内置声明式切面 + 0 个用户切面
        $this->assertCount(3, $kernel->getRegisteredAspects());
    }

    public function testAppendsUserAspects(): void
    {
        $userAspect = new class implements \Kode\Aop\Contract\AspectInterface {
            public function marker(): string
            {
                return 'user';
            }
        };

        $kernel = AopProvider::create()
            ->withLogger(new InMemoryLogger())
            ->register($userAspect)
            ->boot();

        $this->assertCount(2, $kernel->getRegisteredAspects());
    }

    public function testProviderDrivesFinalClass(): void
    {
        // Provider 装配后，声明式注解在 final 组合式代理上同样生效
        $logger = new InMemoryLogger();
        AopProvider::create()->withLogger($logger)->boot();

        /** @var FinalConcernInterface $proxy */
        $proxy = Aop::proxy(FinalConcernService::class);
        $proxy->logged(2);

        $this->assertCount(1, $logger->records);
        $this->assertSame([2], $logger->records[0]['context']['arguments']);
        $this->assertSame(3, $logger->records[0]['context']['result']);
    }

    public function testDisableSwitchBypassesWeaving(): void
    {
        $logger = new InMemoryLogger();
        AopProvider::create()->withLogger($logger)->enable(false)->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);
        $svc->logged(1);

        // 内核禁用时不再织入日志
        $this->assertEmpty($logger->records);
    }
}
