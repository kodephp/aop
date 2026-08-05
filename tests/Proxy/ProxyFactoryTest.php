<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Proxy;

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Contract\ProxyInterface;
use Kode\Aop\Runtime\AspectKernel;
use Kode\Aop\Runtime\JoinPoint;
use Kode\Aop\Tests\Fixture\OrderService;
use Kode\Aop\Tests\Fixture\SampleService;
use PHPUnit\Framework\TestCase;

/**
 * 代理工厂单元测试
 *
 * @package Kode\Aop\Tests\Proxy
 * @author Kode Team <382601296@qq.com>
 */
#[Aspect]
class RecordingProxyAspect
{
    /** @var array<int, string> */
    public array $log = [];

    #[Before('execution(* *Service->create(..))')]
    public function before(JoinPoint $joinPoint): void
    {
        $this->log[] = 'before';
    }
}

class ProxyFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        AspectKernel::resetInstance();
    }

    private function kernelWithAspect(): AspectKernel
    {
        $kernel = AspectKernel::getInstance();
        $kernel->registerAspect(new RecordingProxyAspect());
        $kernel->init();

        return $kernel;
    }

    public function testProxyImplementsMarkerAndExtendsTarget(): void
    {
        $kernel = $this->kernelWithAspect();
        $proxy = $kernel->getProxy(SampleService::class);

        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertInstanceOf(SampleService::class, $proxy);
    }

    public function testConstructorArgumentsPreserved(): void
    {
        $kernel = $this->kernelWithAspect();
        $proxy = $kernel->getProxy(SampleService::class, ['tag' => 'custom']);
        $this->assertInstanceOf(SampleService::class, $proxy);

        $this->assertSame('custom', $proxy->tag);
    }

    public function testProxyClassForReturnsNullWhenNoAdvice(): void
    {
        AspectKernel::resetInstance();
        $kernel = AspectKernel::getInstance();

        $this->assertNull($kernel->getProxyClass(OrderService::class));
    }

    public function testWrapCopiesProperties(): void
    {
        $kernel = $this->kernelWithAspect();
        $real = new SampleService('wrapped');

        $proxy = $kernel->wrap($real);

        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertInstanceOf(SampleService::class, $proxy);
        $this->assertSame('wrapped', $proxy->tag);
    }

    public function testProxyInvocationTriggersAdvice(): void
    {
        $aspect = new RecordingProxyAspect();
        $kernel = AspectKernel::getInstance();
        $kernel->registerAspect($aspect);
        $kernel->init();

        $proxy = $kernel->getProxy(SampleService::class);
        $this->assertInstanceOf(SampleService::class, $proxy);
        $proxy->create('hello');

        $this->assertSame(['before'], $aspect->log);
    }
}
