<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Advice;

use Kode\Aop\Advice\AdviceRegistry;
use Kode\Aop\Advice\AdviceSet;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\Priority;
use Kode\Aop\Runtime\JoinPoint;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use Kode\Aop\Tests\Fixture\OrderService;
use Kode\Aop\Tests\Fixture\SampleService;
use PHPUnit\Framework\TestCase;

/**
 * 通知注册表单元测试
 *
 * @package Kode\Aop\Tests\Advice
 * @author Kode Team <382601296@qq.com>
 */
#[Aspect]
class RegistryFixtureAspect
{
    #[Before('execution(* *Service->create(..))')]
    #[Priority(5)]
    public function beforeCreate(JoinPoint $joinPoint): void
    {
    }

    #[After('execution(* *Service->create(..))')]
    public function afterCreate(JoinPoint $joinPoint): void
    {
    }

    #[Around('execution(* *Service->delete(..))')]
    public function aroundDelete(ProceedingJoinPoint $joinPoint): mixed
    {
        return $joinPoint->proceed();
    }
}

class AdviceRegistryTest extends TestCase
{
    public function testRegisterAndResolve(): void
    {
        $registry = new AdviceRegistry();
        $registry->register(new RegistryFixtureAspect());

        $set = $registry->resolve(SampleService::class, 'create');

        $this->assertCount(1, $set->before);
        $this->assertCount(1, $set->after);
        $this->assertCount(0, $set->around);
        $this->assertFalse($set->isEmpty());
    }

    public function testResolveResultIsCached(): void
    {
        $registry = new AdviceRegistry();
        $registry->register(new RegistryFixtureAspect());

        $first = $registry->resolve(SampleService::class, 'create');
        $second = $registry->resolve(SampleService::class, 'create');

        $this->assertSame($first, $second);
    }

    public function testNoAdviceForUnmatchedMethod(): void
    {
        $registry = new AdviceRegistry();
        $registry->register(new RegistryFixtureAspect());

        $set = $registry->resolve(SampleService::class, 'find');

        $this->assertTrue($set->isEmpty());
    }

    public function testFingerprintChangesWithAspects(): void
    {
        $registered = new AdviceRegistry();
        $registered->register(new RegistryFixtureAspect());
        $this->assertNotSame('empty', $registered->fingerprint());

        $empty = new AdviceRegistry();
        $this->assertSame('empty', $empty->fingerprint());
    }

    public function testShouldProxy(): void
    {
        $registry = new AdviceRegistry();
        $registry->register(new RegistryFixtureAspect());

        $this->assertTrue($registry->shouldProxy(SampleService::class));
        $this->assertFalse($registry->shouldProxy(OrderService::class));
    }

    public function testEmptyAdviceSetHelper(): void
    {
        $set = AdviceSet::empty();

        $this->assertTrue($set->isEmpty());
        $this->assertSame(0, $set->count());
    }
}
