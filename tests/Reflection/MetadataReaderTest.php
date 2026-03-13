<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Reflection;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Reflection\MetadataReader;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Priority;
use ReflectionClass;
use ReflectionMethod;

/**
 * 测试用的切面类
 */
#[Aspect]
class TestAspect
{
    #[Before('execution(* Test->method(..))')]
    #[Priority(10)]
    public function beforeMethod(): void
    {
    }

    #[After('execution(* Test->method2(..))')]
    public function afterMethod(): void
    {
    }

    #[Around('execution(* Test->method3(..))')]
    public function aroundMethod(): void
    {
    }

    public function normalMethod(): void
    {
    }
}

/**
 * 非切面类
 */
class NonAspectClass
{
}

/**
 * MetadataReader 测试类
 *
 * @package Kode\Aop\Tests\Reflection
 * @author Kode Team <382601296@qq.com>
 */
class MetadataReaderTest extends TestCase
{
    protected function setUp(): void
    {
        MetadataReader::clearCache();
    }

    /**
     * 测试获取切面注解
     */
    public function testGetAspect(): void
    {
        $reflection = new ReflectionClass(TestAspect::class);
        $aspect = MetadataReader::getAspect($reflection);

        $this->assertInstanceOf(Aspect::class, $aspect);
        $this->assertTrue($aspect->enabled);
        $this->assertSame(0, $aspect->priority);
    }

    /**
     * 测试获取前置通知注解
     */
    public function testGetBefores(): void
    {
        $reflection = new ReflectionMethod(TestAspect::class, 'beforeMethod');
        $befores = MetadataReader::getBefores($reflection);

        $this->assertCount(1, $befores);
        $this->assertInstanceOf(Before::class, $befores[0]);
        $this->assertSame('execution(* Test->method(..))', $befores[0]->pointcut);
    }

    /**
     * 测试获取后置通知注解
     */
    public function testGetAfters(): void
    {
        $reflection = new ReflectionMethod(TestAspect::class, 'afterMethod');
        $afters = MetadataReader::getAfters($reflection);

        $this->assertCount(1, $afters);
        $this->assertInstanceOf(After::class, $afters[0]);
        $this->assertSame('execution(* Test->method2(..))', $afters[0]->pointcut);
    }

    /**
     * 测试获取环绕通知注解
     */
    public function testGetArounds(): void
    {
        $reflection = new ReflectionMethod(TestAspect::class, 'aroundMethod');
        $arounds = MetadataReader::getArounds($reflection);

        $this->assertCount(1, $arounds);
        $this->assertInstanceOf(Around::class, $arounds[0]);
        $this->assertSame('execution(* Test->method3(..))', $arounds[0]->pointcut);
    }

    /**
     * 测试获取优先级注解
     */
    public function testGetPriority(): void
    {
        $reflection = new ReflectionMethod(TestAspect::class, 'beforeMethod');
        $priority = MetadataReader::getPriority($reflection);

        $this->assertInstanceOf(Priority::class, $priority);
        $this->assertSame(10, $priority->value);
    }

    /**
     * 测试清空缓存
     */
    public function testClearCache(): void
    {
        $reflection = new ReflectionMethod(TestAspect::class, 'beforeMethod');
        MetadataReader::getBefores($reflection);
        MetadataReader::clearCache();

        $this->expectNotToPerformAssertions();
    }

    /**
     * 测试检查类是否为切面类
     */
    public function testIsAspectClass(): void
    {
        $this->assertTrue(MetadataReader::isAspectClass(TestAspect::class));
        $this->assertFalse(MetadataReader::isAspectClass(NonAspectClass::class));
    }

    /**
     * 测试获取切面类的所有通知方法
     */
    public function testGetAspectMethods(): void
    {
        $methods = MetadataReader::getAspectMethods(TestAspect::class);

        $this->assertCount(3, $methods);
        $this->assertArrayHasKey('beforeMethod', $methods);
        $this->assertArrayHasKey('afterMethod', $methods);
        $this->assertArrayHasKey('aroundMethod', $methods);
        $this->assertArrayNotHasKey('normalMethod', $methods);

        $this->assertSame(10, $methods['beforeMethod']['priority']);
        $this->assertSame(Priority::NORMAL, $methods['afterMethod']['priority']);
    }
}
