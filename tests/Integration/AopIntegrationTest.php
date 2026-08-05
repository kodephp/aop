<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Integration;

use Kode\Aop\Aop;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\AfterReturning;
use Kode\Aop\Attribute\AfterThrowing;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\Priority;
use Kode\Aop\Contract\JoinPointInterface;
use Kode\Aop\Contract\ProxyInterface;
use Kode\Aop\Runtime\JoinPoint;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 端到端集成测试
 *
 * 通过 Aop 门面走完整链路：注册切面 → 生成代理 → 方法调用，
 * 验证命名空间代理、构造函数保留、洋葱式 Around 链、AfterReturning
 * 返回值替换、AfterThrowing 异常捕获、wrap 包装等 v3 关键能力。
 *
 * @package Kode\Aop\Tests\Integration
 * @author Kode Team <382601296@qq.com>
 */
class IntService
{
    public function __construct(
        public string $name = 'svc'
    ) {
    }

    public function run(int $x): int
    {
        return $x * 2;
    }

    public function compute(int $a, int $b): int
    {
        return $a + $b;
    }

    public function boom(): void
    {
        throw new RuntimeException('kaboom');
    }

    public function chain(): string
    {
        return 'core';
    }
}

#[Aspect]
class OrderAspect
{
    /** @var array<int, string> */
    public array $log = [];

    #[Before('execution(* *IntService->run(..))')]
    #[Priority(1)]
    public function before(JoinPoint $joinPoint): void
    {
        $this->log[] = 'before';
    }

    #[Around('execution(* *IntService->run(..))')]
    #[Priority(1)]
    public function around(ProceedingJoinPoint $joinPoint): mixed
    {
        $this->log[] = 'around-in';
        $result = $joinPoint->proceed();
        $this->log[] = 'around-out';

        return $result;
    }

    #[After('execution(* *IntService->run(..))')]
    #[Priority(1)]
    public function after(JoinPoint $joinPoint): void
    {
        $this->log[] = 'after';
    }
}

#[Aspect]
class ReturnAspect
{
    #[AfterReturning('execution(* *IntService->compute(..))')]
    public function replace(JoinPointInterface $joinPoint): mixed
    {
        return 999;
    }
}

#[Aspect]
class ThrowAspect
{
    /** @var array<int, string> */
    public array $caught = [];

    #[AfterThrowing('execution(* *IntService->boom(..))')]
    public function onThrow(JoinPointInterface $joinPoint): void
    {
        $this->caught[] = $joinPoint->getException()?->getMessage() ?? 'none';
    }
}

#[Aspect]
class ChainAspect
{
    /** @var array<int, string> */
    public array $log = [];

    #[Around('execution(* *IntService->chain(..))')]
    #[Priority(1)]
    public function outer(ProceedingJoinPoint $joinPoint): mixed
    {
        $this->log[] = 'outer-in';
        $result = $joinPoint->proceed();
        $this->log[] = 'outer-out';

        return $result;
    }

    #[Around('execution(* *IntService->chain(..))')]
    #[Priority(2)]
    public function inner(ProceedingJoinPoint $joinPoint): mixed
    {
        $this->log[] = 'inner-in';
        $result = $joinPoint->proceed();
        $this->log[] = 'inner-out';

        return $result;
    }
}

class AopIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Aop::reset();
    }

    public function testBootAndProxyBasicOrder(): void
    {
        $aspect = new OrderAspect();
        Aop::boot([$aspect]);

        $proxy = Aop::proxy(IntService::class);
        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertInstanceOf(IntService::class, $proxy);

        $result = $proxy->run(5);
        $this->assertSame(10, $result);
        $this->assertSame(['before', 'around-in', 'around-out', 'after'], $aspect->log);
    }

    public function testConstructorPreserved(): void
    {
        Aop::boot([new OrderAspect()]);

        $proxy = Aop::proxy(IntService::class, ['name' => 'arg']);
        $this->assertInstanceOf(IntService::class, $proxy);
        $this->assertSame('arg', $proxy->name);
    }

    public function testAfterReturningReplacesResult(): void
    {
        Aop::boot([new ReturnAspect()]);

        $proxy = Aop::proxy(IntService::class);
        $this->assertInstanceOf(IntService::class, $proxy);
        $this->assertSame(999, $proxy->compute(1, 2));
    }

    public function testAfterThrowingCatchesAndRethrows(): void
    {
        $aspect = new ThrowAspect();
        Aop::boot([$aspect]);

        $proxy = Aop::proxy(IntService::class);
        $this->assertInstanceOf(IntService::class, $proxy);

        try {
            $proxy->boom();
            $this->fail('Expected RuntimeException to be thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('kaboom', $exception->getMessage());
        }

        $this->assertSame(['kaboom'], $aspect->caught);
    }

    public function testOnionChainWithMultipleAround(): void
    {
        $aspect = new ChainAspect();
        Aop::boot([$aspect]);

        $proxy = Aop::proxy(IntService::class);
        $this->assertInstanceOf(IntService::class, $proxy);
        $this->assertSame('core', $proxy->chain());
        $this->assertSame(['outer-in', 'inner-in', 'inner-out', 'outer-out'], $aspect->log);
    }

    public function testWrapExistingInstance(): void
    {
        $aspect = new OrderAspect();
        Aop::boot([$aspect]);

        $real = new IntService('wrapped');
        $proxy = Aop::wrap($real);

        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertInstanceOf(IntService::class, $proxy);
        $this->assertSame('wrapped', $proxy->name);
        $this->assertSame(8, $proxy->run(4));
        $this->assertSame(['before', 'around-in', 'around-out', 'after'], $aspect->log);
    }

    public function testAdvicesForDebug(): void
    {
        $aspect = new OrderAspect();
        Aop::boot([$aspect]);

        $set = Aop::advicesFor(IntService::class, 'run');
        $this->assertCount(1, $set->before);
        $this->assertCount(1, $set->around);
        $this->assertCount(1, $set->after);
    }

    public function testDiagnostics(): void
    {
        Aop::boot([new OrderAspect()]);

        $diagnostics = Aop::diagnostics();
        $this->assertArrayHasKey('aspects', $diagnostics);
        $this->assertSame(1, $diagnostics['aspects']);
        $this->assertTrue($diagnostics['enabled']);
    }

    public function testBootFromConfig(): void
    {
        Aop::bootFromConfig([
            'aspects' => [new OrderAspect()],
            'cache' => ['path' => null],
        ]);

        $proxy = Aop::proxy(IntService::class);
        $this->assertInstanceOf(IntService::class, $proxy);
        $this->assertSame(10, $proxy->run(5));
    }
}
