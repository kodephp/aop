<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Pointcut;

use Kode\Aop\Exception\AopException;
use Kode\Aop\Pointcut\MatchContext;
use Kode\Aop\Pointcut\PointcutParser;
use Kode\Aop\Tests\Fixture\AnnotatedMethodService;
use Kode\Aop\Tests\Fixture\MethodMarker;
use Kode\Aop\Tests\Fixture\OrderService;
use Kode\Aop\Tests\Fixture\SampleService;
use Kode\Aop\Tests\Fixture\SubService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 切入点表达式解析器单元测试
 *
 * @package Kode\Aop\Tests\Pointcut
 * @author Kode Team <382601296@qq.com>
 */
class PointcutParserTest extends TestCase
{
    protected function setUp(): void
    {
        PointcutParser::clearCache();
    }

    /**
     * 构造一个携带反射对象的匹配上下文
     */
    private function context(string $class, string $method): MatchContext
    {
        return new MatchContext($class, $method, new ReflectionMethod($class, $method));
    }

    public function testExecutionWildcardClassAndMethod(): void
    {
        $matcher = PointcutParser::compile('execution(* *Service->create(..))');

        $this->assertTrue($matcher($this->context(SampleService::class, 'create')));
        $this->assertFalse($matcher($this->context(SampleService::class, 'find')));
        $this->assertFalse($matcher($this->context(OrderService::class, 'save')));
    }

    public function testWithinMatcher(): void
    {
        $matcher = PointcutParser::compile('within(*SampleService)');

        $this->assertTrue($matcher($this->context(SampleService::class, 'create')));
        $this->assertFalse($matcher($this->context(OrderService::class, 'save')));
    }

    public function testMethodNameMatcher(): void
    {
        $matcher = PointcutParser::compile('method(create)');

        $this->assertTrue($matcher($this->context(SampleService::class, 'create')));
        $this->assertFalse($matcher($this->context(SampleService::class, 'delete')));
    }

    public function testAnnotationOnMethod(): void
    {
        $matcher = PointcutParser::compile('@annotation(' . MethodMarker::class . ')');

        $this->assertTrue($matcher($this->context(AnnotatedMethodService::class, 'tagged')));
        $this->assertFalse($matcher($this->context(AnnotatedMethodService::class, 'plain')));
    }

    public function testLogicalAnd(): void
    {
        $matcher = PointcutParser::compile('execution(* *Service->*(..)) && !method(delete)');

        $this->assertTrue($matcher($this->context(SampleService::class, 'create')));
        $this->assertFalse($matcher($this->context(SampleService::class, 'delete')));
    }

    public function testLogicalOr(): void
    {
        $matcher = PointcutParser::compile('method(create) || method(find)');

        $this->assertTrue($matcher($this->context(SampleService::class, 'create')));
        $this->assertTrue($matcher($this->context(SampleService::class, 'find')));
        $this->assertFalse($matcher($this->context(SampleService::class, 'delete')));
    }

    public function testLogicalNot(): void
    {
        $matcher = PointcutParser::compile('!method(delete)');

        $this->assertTrue($matcher($this->context(SampleService::class, 'create')));
        $this->assertFalse($matcher($this->context(SampleService::class, 'delete')));
    }

    public function testArgumentSpecExact(): void
    {
        $matcher = PointcutParser::compile('execution(* *Service->find(int))');

        $this->assertTrue($matcher($this->context(SampleService::class, 'find')));
        $this->assertFalse($matcher($this->context(SampleService::class, 'create')));
    }

    public function testArgumentVariadic(): void
    {
        $matcher = PointcutParser::compile('execution(* *Service->find(int, ..))');

        $this->assertTrue($matcher($this->context(SampleService::class, 'find')));
    }

    public function testModifierMatch(): void
    {
        $public = PointcutParser::compile('execution(public *Service->create(..))');
        $this->assertTrue($public($this->context(SampleService::class, 'create')));

        $private = PointcutParser::compile('execution(private *Service->create(..))');
        $this->assertFalse($private($this->context(SampleService::class, 'create')));
    }

    public function testSubtypePlus(): void
    {
        $matcher = PointcutParser::compile('execution(* *SampleService+->create(..))');

        $this->assertTrue($matcher($this->context(SubService::class, 'create')));
        $this->assertTrue($matcher($this->context(SampleService::class, 'create')));
        $this->assertFalse($matcher($this->context(OrderService::class, 'save')));
    }

    public function testNamedPointcutResolver(): void
    {
        $definitions = ['logAll' => 'execution(* *SampleService->*(..))'];
        $matcher = PointcutParser::compile(
            'logAll()',
            static fn(string $name): ?string => $definitions[$name] ?? null
        );

        $this->assertTrue($matcher($this->context(SampleService::class, 'create')));
        $this->assertFalse($matcher($this->context(OrderService::class, 'save')));
    }

    public function testInvalidExpressionThrows(): void
    {
        $this->expectException(AopException::class);
        PointcutParser::compile('execution()');
    }

    public function testEmptyExpressionMatchesNothing(): void
    {
        $matcher = PointcutParser::compile('');

        $this->assertFalse($matcher($this->context(SampleService::class, 'create')));
    }
}
