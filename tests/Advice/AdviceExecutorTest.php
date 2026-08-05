<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Advice;

use Closure;
use Kode\Aop\Advice\Advice;
use Kode\Aop\Advice\AdviceExecutor;
use Kode\Aop\Advice\AdviceSet;
use Kode\Aop\Advice\AdviceType;
use Kode\Aop\Contract\JoinPointInterface;
use Kode\Aop\Pointcut\MatchContext;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use Kode\Aop\Tests\Fixture\SampleService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * 通知执行器单元测试
 *
 * 直接构造 AdviceSet 与连接点，验证 Before / Around（洋葱链）/ AfterReturning /
 * AfterThrowing / After 的编排时序。
 *
 * @package Kode\Aop\Tests\Advice
 * @author Kode Team <382601296@qq.com>
 */
class AdviceExecutorTest extends TestCase
{
    /**
     * 构造一条通知
     */
    private function makeAdvice(object $aspect, string $method, AdviceType $type, int $order = 10, ?Closure $matcher = null): Advice
    {
        $matcher ??= static function (MatchContext $context): bool {
            return true;
        };

        return new Advice(
            aspect: $aspect,
            aspectMethod: $method,
            type: $type,
            pointcut: 'execution(* *->*(..))',
            order: $order,
            matcher: $matcher,
            throwable: \Throwable::class,
        );
    }

    /**
     * 为目标方法准备反射对象与实例
     *
     * @return array{0: SampleService, 1: ReflectionClass, 2: ReflectionMethod}
     */
    private function reflector(): array
    {
        $object = new SampleService();
        $class = new ReflectionClass($object);

        return [$object, $class, $class->getMethod('create')];
    }

    public function testBeforeAroundAfterOrder(): void
    {
        $aspect = new class {
            /** @var array<int, string> */
            public array $log = [];

            public function before(JoinPointInterface $jp): void
            {
                $this->log[] = 'before';
            }

            public function around(ProceedingJoinPoint $jp): mixed
            {
                $this->log[] = 'around-in';
                $result = $jp->proceed();
                $this->log[] = 'around-out';

                return $result;
            }

            public function after(JoinPointInterface $jp): void
            {
                $this->log[] = 'after';
            }
        };

        [$object, $class, $method] = $this->reflector();

        $set = new AdviceSet(
            before: [$this->makeAdvice($aspect, 'before', AdviceType::Before)],
            around: [$this->makeAdvice($aspect, 'around', AdviceType::Around)],
            after: [$this->makeAdvice($aspect, 'after', AdviceType::After)],
        );

        $executor = new AdviceExecutor();
        $result = $executor->execute(
            $set,
            $object,
            $class,
            $method,
            ['x'],
            static fn(mixed ...$args): mixed => $object->create(...$args)
        );

        $this->assertSame(['before', 'around-in', 'around-out', 'after'], $aspect->log);
        $this->assertSame(1, $result);
    }

    public function testAfterReturningReplacesResult(): void
    {
        $aspect = new class {
            public function replace(JoinPointInterface $jp): mixed
            {
                return 'REPLACED';
            }
        };

        [$object, $class, $method] = $this->reflector();

        $set = new AdviceSet(
            afterReturning: [$this->makeAdvice($aspect, 'replace', AdviceType::AfterReturning)],
        );

        $executor = new AdviceExecutor();
        $result = $executor->execute(
            $set,
            $object,
            $class,
            $method,
            ['x'],
            static fn(mixed ...$args): mixed => $object->create(...$args)
        );

        $this->assertSame('REPLACED', $result);
    }

    public function testAfterReturningNullKeepsOriginal(): void
    {
        $aspect = new class {
            public function noop(JoinPointInterface $jp): void
            {
            }
        };

        [$object, $class, $method] = $this->reflector();

        $set = new AdviceSet(
            afterReturning: [$this->makeAdvice($aspect, 'noop', AdviceType::AfterReturning)],
        );

        $executor = new AdviceExecutor();
        $result = $executor->execute(
            $set,
            $object,
            $class,
            $method,
            ['abc'],
            static fn(mixed ...$args): mixed => $object->create(...$args)
        );

        $this->assertSame(3, $result);
    }

    public function testAfterThrowingCatchesAndRethrows(): void
    {
        $aspect = new class {
            /** @var array<int, string> */
            public array $log = [];

            public function onThrow(JoinPointInterface $jp): void
            {
                $this->log[] = $jp->getException()?->getMessage() ?? 'none';
            }

            public function after(JoinPointInterface $jp): void
            {
                $this->log[] = 'after';
            }
        };

        [$object, $class, $method] = $this->reflector();

        $set = new AdviceSet(
            afterThrowing: [$this->makeAdvice($aspect, 'onThrow', AdviceType::AfterThrowing)],
            after: [$this->makeAdvice($aspect, 'after', AdviceType::After)],
        );

        $executor = new AdviceExecutor();

        try {
            $executor->execute(
                $set,
                $object,
                $class,
                $method,
                [],
                static fn(): mixed => throw new RuntimeException('boom')
            );
            $this->fail('Expected RuntimeException to be thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertSame(['boom', 'after'], $aspect->log);
    }

    public function testAroundChainNestedOnion(): void
    {
        $outer = new class {
            /** @var array<int, string> */
            public array $log = [];

            public function wrap(ProceedingJoinPoint $jp): mixed
            {
                $this->log[] = 'outer-in';
                $result = $jp->proceed();
                $this->log[] = 'outer-out';

                return $result;
            }
        };

        $inner = new class {
            /** @var array<int, string> */
            public array $log = [];

            public function wrap(ProceedingJoinPoint $jp): mixed
            {
                $this->log[] = 'inner-in';
                $result = $jp->proceed();
                $this->log[] = 'inner-out';

                return $result;
            }
        };

        [$object, $class, $method] = $this->reflector();

        $set = new AdviceSet(
            around: [
                $this->makeAdvice($outer, 'wrap', AdviceType::Around, 10),
                $this->makeAdvice($inner, 'wrap', AdviceType::Around, 20),
            ],
        );

        $executor = new AdviceExecutor();
        $result = $executor->execute(
            $set,
            $object,
            $class,
            $method,
            ['z'],
            static fn(mixed ...$args): mixed => $object->create(...$args)
        );

        $this->assertSame(1, $result);
        $this->assertSame(['outer-in', 'outer-out'], $outer->log);
        $this->assertSame(['inner-in', 'inner-out'], $inner->log);
    }
}
