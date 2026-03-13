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
 * 提供安全的反射操作，封装了常见的反射功能并处理异常。
 * 所有方法都会捕获反射异常并转换为 AopException。
 *
 * @package Kode\Aop\Reflection
 * @author Kode Team <382601296@qq.com>
 */
class Reflector
{
    /**
     * 获取类的反射对象
     *
     * @param object|class-string $class 类名或对象实例
     * @return ReflectionClass 类反射对象
     * @throws AopException 如果类不存在或无法反射
     *
     * @example
     * ```php
     * // 使用类名
     * $reflection = Reflector::getClass(UserService::class);
     *
     * // 使用对象实例
     * $reflection = Reflector::getClass($userService);
     * ```
     */
    public static function getClass(object|string $class): ReflectionClass
    {
        try {
            return new ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new AopException(
                sprintf('无法反射类: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * 获取方法的反射对象
     *
     * @param object|class-string $class 类名或对象实例
     * @param string $method 方法名
     * @return ReflectionMethod 方法反射对象
     * @throws AopException 如果方法不存在或无法反射
     *
     * @example
     * ```php
     * $reflection = Reflector::getMethod(UserService::class, 'createUser');
     * echo "方法名: " . $reflection->getName();
     * ```
     */
    public static function getMethod(object|string $class, string $method): ReflectionMethod
    {
        try {
            return new ReflectionMethod($class, $method);
        } catch (\ReflectionException $e) {
            throw new AopException(
                sprintf('无法反射方法: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * 获取属性的反射对象
     *
     * @param object|class-string $class 类名或对象实例
     * @param string $property 属性名
     * @return ReflectionProperty 属性反射对象
     * @throws AopException 如果属性不存在或无法反射
     *
     * @example
     * ```php
     * $reflection = Reflector::getProperty(UserService::class, 'repository');
     * echo "属性名: " . $reflection->getName();
     * ```
     */
    public static function getProperty(object|string $class, string $property): ReflectionProperty
    {
        try {
            return new ReflectionProperty($class, $property);
        } catch (\ReflectionException $e) {
            throw new AopException(
                sprintf('无法反射属性: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * 检查类是否存在
     *
     * @param string $className 类名
     * @return bool 类是否存在
     */
    public static function classExists(string $className): bool
    {
        return class_exists($className);
    }

    /**
     * 检查方法是否存在
     *
     * @param object|class-string $class 类名或对象实例
     * @param string $method 方法名
     * @return bool 方法是否存在
     */
    public static function methodExists(object|string $class, string $method): bool
    {
        return method_exists($class, $method);
    }

    /**
     * 检查属性是否存在
     *
     * @param object|class-string $class 类名或对象实例
     * @param string $property 属性名
     * @return bool 属性是否存在
     */
    public static function propertyExists(object|string $class, string $property): bool
    {
        return property_exists($class, $property);
    }

    /**
     * 获取类的所有公共方法
     *
     * @param object|class-string $class 类名或对象实例
     * @return array<int, ReflectionMethod> 方法反射对象数组
     * @throws AopException 如果类不存在
     */
    public static function getPublicMethods(object|string $class): array
    {
        return self::getClass($class)->getMethods(ReflectionMethod::IS_PUBLIC);
    }

    /**
     * 获取类的短名称（不含命名空间）
     *
     * @param object|class-string $class 类名或对象实例
     * @return string 类的短名称
     * @throws AopException 如果类不存在
     */
    public static function getShortName(object|string $class): string
    {
        return self::getClass($class)->getShortName();
    }

    /**
     * 获取类的命名空间
     *
     * @param object|class-string $class 类名或对象实例
     * @return string 命名空间
     * @throws AopException 如果类不存在
     */
    public static function getNamespaceName(object|string $class): string
    {
        return self::getClass($class)->getNamespaceName();
    }
}
