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
 * MetadataReader 测试类
 */
class MetadataReaderTest extends TestCase
{
    /**
     * 测试切面注解的读取
     */
    public function testGetAspect(): void
    {
        $class = new ReflectionClass(TestAspect::class);
        $aspect = MetadataReader::getAspect($class);
        
        $this->assertInstanceOf(Aspect::class, $aspect);
    }

    /**
     * 测试前置通知注解的读取
     */
    public function testGetBefores(): void
    {
        $class = new ReflectionClass(TestAspect::class);
        $method = new ReflectionMethod(TestAspect::class, 'beforeMethod');
        $befores = MetadataReader::getBefores($method);
        
        $this->assertCount(1, $befores);
        $this->assertInstanceOf(Before::class, $befores[0]);
        $this->assertEquals("execution(* Test->method(..))", $befores[0]->expression);
    }

    /**
     * 测试后置通知注解的读取
     */
    public function testGetAfters(): void
    {
        $class = new ReflectionClass(TestAspect::class);
        $method = new ReflectionMethod(TestAspect::class, 'afterMethod');
        $afters = MetadataReader::getAfters($method);
        
        $this->assertCount(1, $afters);
        $this->assertInstanceOf(After::class, $afters[0]);
        $this->assertEquals("execution(* Test->method(..))", $afters[0]->expression);
    }

    /**
     * 测试环绕通知注解的读取
     */
    public function testGetArounds(): void
    {
        $class = new ReflectionClass(TestAspect::class);
        $method = new ReflectionMethod(TestAspect::class, 'aroundMethod');
        $arounds = MetadataReader::getArounds($method);
        
        $this->assertCount(1, $arounds);
        $this->assertInstanceOf(Around::class, $arounds[0]);
        $this->assertEquals("execution(* Test->method(..))", $arounds[0]->expression);
    }

    /**
     * 测试优先级注解的读取
     */
    public function testGetPriority(): void
    {
        $class = new ReflectionClass(TestAspect::class);
        $method = new ReflectionMethod(TestAspect::class, 'priorityMethod');
        $priority = MetadataReader::getPriority($method);
        
        $this->assertInstanceOf(Priority::class, $priority);
        $this->assertEquals(100, $priority->value);
    }

    /**
     * 测试缓存清除功能
     */
    public function testClearCache(): void
    {
        // 先读取一些元数据以填充缓存
        $class = new ReflectionClass(TestAspect::class);
        $method = new ReflectionMethod(TestAspect::class, 'beforeMethod');
        MetadataReader::getBefores($method);
        
        // 清除缓存
        MetadataReader::clearCache();
        
        // 重新读取（应该重新加载而不是使用缓存）
        $befores = MetadataReader::getBefores($method);
        
        $this->assertCount(1, $befores);
        $this->assertInstanceOf(Before::class, $befores[0]);
    }
}

#[Aspect]
class TestAspect
{
    #[Before("execution(* Test->method(..))")]
    public function beforeMethod(): void
    {
    }

    #[After("execution(* Test->method(..))")]
    public function afterMethod(): void
    {
    }

    #[Around("execution(* Test->method(..))")]
    public function aroundMethod(): void
    {
    }

    #[Priority(100)]
    public function priorityMethod(): void
    {
    }
}