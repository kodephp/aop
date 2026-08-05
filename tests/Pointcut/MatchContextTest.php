<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Pointcut;

use Kode\Aop\Pointcut\MatchContext;
use Kode\Aop\Tests\Fixture\SampleService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 切入点匹配上下文单元测试
 *
 * @package Kode\Aop\Tests\Pointcut
 * @author Kode Team <382601296@qq.com>
 */
class MatchContextTest extends TestCase
{
    public function testSignatureFormat(): void
    {
        $context = new MatchContext(SampleService::class, 'create');

        $this->assertSame(SampleService::class . '->create', $context->signature());
    }

    public function testMethodAndClassAreResolvedWhenReflectionProvided(): void
    {
        $context = new MatchContext(
            SampleService::class,
            'create',
            new ReflectionMethod(SampleService::class, 'create')
        );

        $this->assertNotNull($context->method());
        $this->assertNotNull($context->class());
        $this->assertSame('create', $context->methodName);
        $this->assertSame(SampleService::class, $context->className);
    }

    public function testReflectionIsResolvedLazily(): void
    {
        $context = new MatchContext(SampleService::class, 'find');

        $this->assertSame('find', $context->methodName);
        $this->assertNotNull($context->method());
        $this->assertNotNull($context->class());
    }

    public function testNonExistentClassResolvesToNull(): void
    {
        $context = new MatchContext('Kode\\Aop\\Tests\\Fixture\\NoSuchClass', 'foo');

        $this->assertNull($context->class());
        $this->assertNull($context->method());
    }
}
