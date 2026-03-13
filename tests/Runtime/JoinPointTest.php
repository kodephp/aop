<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Runtime;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Runtime\JoinPoint;
use ReflectionClass;
use ReflectionMethod;

/**
 * JoinPoint 测试类
 *
 * 测试连接点的创建、属性访问和参数操作
 *
 * @package Kode\Aop\Tests\Runtime
 * @author Kode Team <382601296@qq.com>
 */
class JoinPointTest extends TestCase
{
    /**
     * 测试 JoinPoint 可以正确创建和访问属性
     */
    public function testJoinPointCanBeCreatedAndAccessed(): void
    {
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

        $joinPoint = new JoinPoint($class, $method, $mockObject, $arguments, $pointcut);

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
        $testClass = new class {
            public function testMethod(): void
            {
            }
        };

        $class = new ReflectionClass($testClass);
        $method = new ReflectionMethod($testClass, 'testMethod');
        $arguments = ['original'];
        $pointcut = '';

        $joinPoint = new JoinPoint($class, $method, $testClass, $arguments, $pointcut);

        $this->assertSame($arguments, $joinPoint->getArguments());

        $newArguments = ['new', 'arguments'];
        $joinPoint->setArguments($newArguments);

        $this->assertSame($newArguments, $joinPoint->getArguments());
    }

    /**
     * 测试便捷方法 getMethodName
     */
    public function testGetMethodName(): void
    {
        $testClass = new class {
            public function myTestMethod(): void
            {
            }
        };

        $class = new ReflectionClass($testClass);
        $method = new ReflectionMethod($testClass, 'myTestMethod');

        $joinPoint = new JoinPoint($class, $method, $testClass, [], '');

        $this->assertSame('myTestMethod', $joinPoint->getMethodName());
    }

    /**
     * 测试便捷方法 getClassName
     */
    public function testGetClassName(): void
    {
        $testClass = new class {
            public function testMethod(): void
            {
            }
        };

        $class = new ReflectionClass($testClass);
        $method = new ReflectionMethod($testClass, 'testMethod');
        $className = $class->getName();

        $joinPoint = new JoinPoint($class, $method, $testClass, [], '');

        $this->assertSame($className, $joinPoint->getClassName());
    }

    /**
     * 测试便捷方法 getArgument
     */
    public function testGetArgument(): void
    {
        $testClass = new class {
            public function testMethod(): void
            {
            }
        };

        $class = new ReflectionClass($testClass);
        $method = new ReflectionMethod($testClass, 'testMethod');
        $arguments = ['first', 'second', 'third'];

        $joinPoint = new JoinPoint($class, $method, $testClass, $arguments, '');

        $this->assertSame('first', $joinPoint->getArgument(0));
        $this->assertSame('second', $joinPoint->getArgument(1));
        $this->assertSame('third', $joinPoint->getArgument(2));
        $this->assertNull($joinPoint->getArgument(3));
        $this->assertSame('default', $joinPoint->getArgument(3, 'default'));
    }

    /**
     * 测试返回值获取
     */
    public function testGetResult(): void
    {
        $testClass = new class {
            public function testMethod(): string
            {
                return 'result';
            }
        };

        $class = new ReflectionClass($testClass);
        $method = new ReflectionMethod($testClass, 'testMethod');
        $result = 'test_result';

        $joinPoint = new JoinPoint($class, $method, $testClass, [], '', $result);

        $this->assertSame($result, $joinPoint->getResult());
    }
}
