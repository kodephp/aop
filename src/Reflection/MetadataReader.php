<?php

declare(strict_types=1);

namespace Kode\Aop\Reflection;

use ReflectionClass;
use ReflectionMethod;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Pointcut;
use Kode\Aop\Attribute\Priority;

/**
 * 元数据读取器
 * 
 * 读取类、方法上的注解信息，带缓存机制
 */
class MetadataReader
{
    /**
     * 类元数据缓存
     *
     * @var array
     */
    private static array $classMetadata = [];

    /**
     * 方法元数据缓存
     *
     * @var array
     */
    private static array $methodMetadata = [];

    /**
     * 获取类上的切面注解
     *
     * @param ReflectionClass $class 类反射对象
     * @return Aspect|null
     */
    public static function getAspect(ReflectionClass $class): ?Aspect
    {
        $className = $class->getName();
        
        if (!isset(self::$classMetadata[$className]['aspect'])) {
            $attributes = $class->getAttributes(Aspect::class);
            self::$classMetadata[$className]['aspect'] = $attributes ? $attributes[0]->newInstance() : null;
        }
        
        return self::$classMetadata[$className]['aspect'];
    }

    /**
     * 获取方法上的前置通知注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return Before[]
     */
    public static function getBefores(ReflectionMethod $method): array
    {
        $key = self::getMethodKey($method);
        
        if (!isset(self::$methodMetadata[$key]['befores'])) {
            $attributes = $method->getAttributes(Before::class);
            self::$methodMetadata[$key]['befores'] = array_map(
                fn($attr) => $attr->newInstance(),
                $attributes
            );
        }
        
        return self::$methodMetadata[$key]['befores'];
    }

    /**
     * 获取方法上的后置通知注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return After[]
     */
    public static function getAfters(ReflectionMethod $method): array
    {
        $key = self::getMethodKey($method);
        
        if (!isset(self::$methodMetadata[$key]['afters'])) {
            $attributes = $method->getAttributes(After::class);
            self::$methodMetadata[$key]['afters'] = array_map(
                fn($attr) => $attr->newInstance(),
                $attributes
            );
        }
        
        return self::$methodMetadata[$key]['afters'];
    }

    /**
     * 获取方法上的环绕通知注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return Around[]
     */
    public static function getArounds(ReflectionMethod $method): array
    {
        $key = self::getMethodKey($method);
        
        if (!isset(self::$methodMetadata[$key]['arounds'])) {
            $attributes = $method->getAttributes(Around::class);
            self::$methodMetadata[$key]['arounds'] = array_map(
                fn($attr) => $attr->newInstance(),
                $attributes
            );
        }
        
        return self::$methodMetadata[$key]['arounds'];
    }

    /**
     * 获取方法上的切入点注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return Pointcut[]
     */
    public static function getPointcuts(ReflectionMethod $method): array
    {
        $key = self::getMethodKey($method);
        
        if (!isset(self::$methodMetadata[$key]['pointcuts'])) {
            $attributes = $method->getAttributes(Pointcut::class);
            self::$methodMetadata[$key]['pointcuts'] = array_map(
                fn($attr) => $attr->newInstance(),
                $attributes
            );
        }
        
        return self::$methodMetadata[$key]['pointcuts'];
    }

    /**
     * 获取方法上的优先级注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return Priority|null
     */
    public static function getPriority(ReflectionMethod $method): ?Priority
    {
        $key = self::getMethodKey($method);
        
        if (!isset(self::$methodMetadata[$key]['priority'])) {
            $attributes = $method->getAttributes(Priority::class);
            self::$methodMetadata[$key]['priority'] = $attributes ? $attributes[0]->newInstance() : null;
        }
        
        return self::$methodMetadata[$key]['priority'];
    }

    /**
     * 获取方法缓存键
     *
     * @param ReflectionMethod $method
     * @return string
     */
    private static function getMethodKey(ReflectionMethod $method): string
    {
        return $method->getDeclaringClass()->getName() . '::' . $method->getName();
    }

    /**
     * 清空缓存
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$classMetadata = [];
        self::$methodMetadata = [];
    }
}