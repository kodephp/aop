<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Runtime;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Runtime\JoinPoint;
use ReflectionClass;
use ReflectionMethod;

/**
 * JoinPoint 测试类
 */
class JoinPointTest extends TestCase
{
    /**
     * 测试 JoinPoint 可以正确创建和访问属性
     */
    public function testJoinPointCanBeCreatedAndAccessed(): void
    {
        // 创建模拟对象和反射信息
        $mockObject = new class {
            public function testMethod(string $param1, int $param2): string
            {
                return $param1 . $param2;
            }
        };
        
        $class = new ReflectionClass($mockObject);
        $method = new ReflectionMethod($mockObject, 'testMethod');
        $arguments = ['test', 123];
        $pointcut = 'execution(* Test->testMethod(..))';
        
        // 创建 JoinPoint
        $joinPoint = new JoinPoint($class, $method, $mockObject, $arguments, $pointcut);
        
        // 验证属性
        $this->assertSame($class, $joinPoint->getClass());
        $this->assertSame($method, $joinPoint->getMethod());
        $this->assertSame($mockObject, $joinPoint->getThis());
        $this->assertSame($arguments, $joinPoint->getArguments());
        $this->assertSame($pointcut, $joinPoint->getPointcut());
    }

    /**
     * 测试可以设置和获取参数
     */
    public function testCanSetAndGetArguments(): void
    {
        // 创建测试类
        $testClass = new class {
            public function testMethod(): void
            {
                // 空方法，仅用于测试
            }
        };
        
        // 创建反射信息
        $class = new ReflectionClass($testClass);
        $method = new ReflectionMethod($testClass, 'testMethod');
        $arguments = ['original'];
        $pointcut = '';
        
        // 创建 JoinPoint
        $joinPoint = new JoinPoint($class, $method, $testClass, $arguments, $pointcut);
        
        // 验证初始参数
        $this->assertSame($arguments, $joinPoint->getArguments());
        
        // 设置新参数
        $newArguments = ['new', 'arguments'];
        $joinPoint->setArguments($newArguments);
        
        // 验证参数已更新
        $this->assertSame($newArguments, $joinPoint->getArguments());
    }
}