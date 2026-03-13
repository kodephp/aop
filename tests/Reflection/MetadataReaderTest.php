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
        $aspectClass = new #[Aspect] class {
        };

        $reflection = new ReflectionClass($aspectClass);
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
        $aspectClass = new class {
            #[Before('execution(* Test->method(..))')]
            public function beforeMethod(): void
            {
            }
        };

        $reflection = new ReflectionMethod($aspectClass, 'beforeMethod');
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
        $aspectClass = new class {
            #[After('execution(* Test->method(..))')]
            public function afterMethod(): void
            {
            }
        };

        $reflection = new ReflectionMethod($aspectClass, 'afterMethod');
        $afters = MetadataReader::getAfters($reflection);

        $this->assertCount(1, $afters);
        $this->assertInstanceOf(After::class, $afters[0]);
        $this->assertSame('execution(* Test->method(..))', $afters[0]->pointcut);
    }

    /**
     * 测试获取环绕通知注解
     */
    public function testGetArounds(): void
    {
        $aspectClass = new class {
            #[Around('execution(* Test->method(..))')]
            public function aroundMethod(): void
            {
            }
        };

        $reflection = new ReflectionMethod($aspectClass, 'aroundMethod');
        $arounds = MetadataReader::getArounds($reflection);

        $this->assertCount(1, $arounds);
        $this->assertInstanceOf(Around::class, $arounds[0]);
        $this->assertSame('execution(* Test->method(..))', $arounds[0]->pointcut);
    }

    /**
     * 测试获取优先级注解
     */
    public function testGetPriority(): void
    {
        $aspectClass = new class {
            #[Before('execution(* Test->method(..))')]
            #[Priority(100)]
            public function beforeMethod(): void
            {
            }
        };

        $reflection = new ReflectionMethod($aspectClass, 'beforeMethod');
        $priority = MetadataReader::getPriority($reflection);

        $this->assertInstanceOf(Priority::class, $priority);
        $this->assertSame(100, $priority->value);
    }

    /**
     * 测试缓存功能
     */
    public function testCacheFunctionality(): void
    {
        $aspectClass = new class {
            #[Before('execution(* Test->method(..))')]
            public function beforeMethod(): void
            {
            }
        };

        $reflection = new ReflectionMethod($aspectClass, 'beforeMethod');

        $befores1 = MetadataReader::getBefores($reflection);
        $befores2 = MetadataReader::getBefores($reflection);

        $this->assertSame($befores1, $befores2);
    }

    /**
     * 测试清空缓存
     */
    public function testClearCache(): void
    {
        $aspectClass = new class {
            #[Before('execution(* Test->method(..))')]
            public function beforeMethod(): void
            {
            }
        };

        $reflection = new ReflectionMethod($aspectClass, 'beforeMethod');

        MetadataReader::getBefores($reflection);
        $stats = MetadataReader::getCacheStats();

        $this->assertGreaterThan(0, $stats['methods']);

        MetadataReader::clearCache();
        $stats = MetadataReader::getCacheStats();

        $this->assertSame(0, $stats['classes']);
        $this->assertSame(0, $stats['methods']);
    }
}
