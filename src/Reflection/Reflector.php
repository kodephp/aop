<?php

declare(strict_types=1);

namespace Kode\Aop\Reflection;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Kode\Aop\Exception\AopException;

/**
 * 安全反射封装类
 * 
 * 提供安全的反射操作，防止非法访问
 */
class Reflector
{
    /**
     * 获取类的反射对象
     *
     * @param string|object $class 类名或对象实例
     * @return ReflectionClass
     * @throws AopException
     */
    public static function getClass(object|string $class): ReflectionClass
    {
        try {
            return new ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new AopException("Failed to reflect class: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取方法的反射对象
     *
     * @param string|object $class 类名或对象实例
     * @param string $method 方法名
     * @return ReflectionMethod
     * @throws AopException
     */
    public static function getMethod(object|string $class, string $method): ReflectionMethod
    {
        try {
            return new ReflectionMethod($class, $method);
        } catch (\ReflectionException $e) {
            throw new AopException("Failed to reflect method: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取属性的反射对象
     *
     * @param string|object $class 类名或对象实例
     * @param string $property 属性名
     * @return ReflectionProperty
     * @throws AopException
     */
    public static function getProperty(object|string $class, string $property): ReflectionProperty
    {
        try {
            return new ReflectionProperty($class, $property);
        } catch (\ReflectionException $e) {
            throw new AopException("Failed to reflect property: " . $e->getMessage(), 0, $e);
        }
    }
}