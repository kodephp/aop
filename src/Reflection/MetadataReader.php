<?php

declare(strict_types=1);

namespace Kode\Aop\Reflection;

use Kode\Attributes\Attr;
use Kode\Attributes\Reader;
use Kode\Attributes\Meta;
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
 * 基于 kode/attributes 包实现的属性读取器，用于读取类、方法上的注解信息。
 * 提供高性能的缓存机制，避免重复的反射操作。
 *
 * 支持的注解类型：
 * - Aspect：类级别，标记切面类
 * - Before：方法级别，前置通知
 * - After：方法级别，后置通知
 * - Around：方法级别，环绕通知
 * - Pointcut：方法级别，切入点定义
 * - Priority：方法级别，执行优先级
 *
 * @package Kode\Aop\Reflection
 * @author Kode Team <382601296@qq.com>
 */
class MetadataReader
{
    /**
     * 属性读取器实例
     */
    private static ?Reader $reader = null;

    /**
     * 获取属性读取器实例
     */
    private static function getReader(): Reader
    {
        return self::$reader ??= new Reader();
    }

    /**
     * 获取类上的切面注解
     *
     * @param ReflectionClass $class 类反射对象
     * @return Aspect|null 切面注解实例，如果不存在则返回 null
     *
     * @example
     * ```php
     * $reflection = new ReflectionClass(LoggingAspect::class);
     * $aspect = MetadataReader::getAspect($reflection);
     * if ($aspect) {
     *     echo "这是一个切面类，优先级: {$aspect->priority}";
     * }
     * ```
     */
    public static function getAspect(ReflectionClass $class): ?Aspect
    {
        $meta = Attr::get($class->getName(), Aspect::class);
        return $meta?->getInstance();
    }

    /**
     * 获取方法上的前置通知注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return array<int, Before> 前置通知注解数组
     *
     * @example
     * ```php
     * $reflection = new ReflectionMethod(LoggingAspect::class, 'logBefore');
     * $befores = MetadataReader::getBefores($reflection);
     * foreach ($befores as $before) {
     *     echo "切入点: {$before->pointcut}";
     * }
     * ```
     */
    public static function getBefores(ReflectionMethod $method): array
    {
        return self::getMethodAttributes($method, Before::class);
    }

    /**
     * 获取方法上的后置通知注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return array<int, After> 后置通知注解数组
     *
     * @example
     * ```php
     * $reflection = new ReflectionMethod(LoggingAspect::class, 'logAfter');
     * $afters = MetadataReader::getAfters($reflection);
     * foreach ($afters as $after) {
     *     echo "切入点: {$after->pointcut}";
     * }
     * ```
     */
    public static function getAfters(ReflectionMethod $method): array
    {
        return self::getMethodAttributes($method, After::class);
    }

    /**
     * 获取方法上的环绕通知注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return array<int, Around> 环绕通知注解数组
     *
     * @example
     * ```php
     * $reflection = new ReflectionMethod(TransactionAspect::class, 'transactional');
     * $arounds = MetadataReader::getArounds($reflection);
     * foreach ($arounds as $around) {
     *     echo "切入点: {$around->pointcut}";
     * }
     * ```
     */
    public static function getArounds(ReflectionMethod $method): array
    {
        return self::getMethodAttributes($method, Around::class);
    }

    /**
     * 获取方法上的切入点注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return array<int, Pointcut> 切入点注解数组
     *
     * @example
     * ```php
     * $reflection = new ReflectionMethod(LoggingAspect::class, 'servicePointcut');
     * $pointcuts = MetadataReader::getPointcuts($reflection);
     * foreach ($pointcuts as $pointcut) {
     *     echo "切入点表达式: {$pointcut->expression}";
     * }
     * ```
     */
    public static function getPointcuts(ReflectionMethod $method): array
    {
        return self::getMethodAttributes($method, Pointcut::class);
    }

    /**
     * 获取方法上的优先级注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return Priority|null 优先级注解实例，如果不存在则返回 null
     *
     * @example
     * ```php
     * $reflection = new ReflectionMethod(LoggingAspect::class, 'logBefore');
     * $priority = MetadataReader::getPriority($reflection);
     * echo $priority ? "优先级: {$priority->value}" : "使用默认优先级";
     * ```
     */
    public static function getPriority(ReflectionMethod $method): ?Priority
    {
        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        $metaList = self::getReader()->getMethodAttrs($className, $methodName);
        $meta = $metaList->get(Priority::class);

        return $meta?->getInstance();
    }

    /**
     * 获取方法上指定类型的属性实例数组
     *
     * @template T of object
     * @param ReflectionMethod $method 方法反射对象
     * @param class-string<T> $attributeClass 属性类名
     * @return array<int, T> 属性实例数组
     */
    private static function getMethodAttributes(ReflectionMethod $method, string $attributeClass): array
    {
        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        $metaList = self::getReader()->getMethodAttrs($className, $methodName);
        $filteredList = $metaList->filter(fn(Meta $meta) => $meta->name === $attributeClass);

        return array_map(
            static fn(Meta $meta) => $meta->getInstance(),
            $filteredList->all()
        );
    }

    /**
     * 清空所有缓存
     *
     * 用于在测试环境或需要重新加载元数据时清空缓存。
     */
    public static function clearCache(): void
    {
        self::$reader = null;
        Attr::clear();
    }

    /**
     * 获取缓存统计信息
     *
     * 用于调试和性能分析。
     *
     * @return array<string, mixed> 缓存统计
     */
    public static function getCacheStats(): array
    {
        return [
            'note' => '缓存由 kode/attributes 包管理',
        ];
    }

    /**
     * 检查类是否为切面类
     *
     * @param string $className 类名
     * @return bool 是否为切面类
     */
    public static function isAspectClass(string $className): bool
    {
        return Attr::has($className, Aspect::class);
    }

    /**
     * 获取切面类的所有通知方法
     *
     * @param string $className 切面类名
     * @return array<string, array{befores: array, afters: array, arounds: array, priority: int}> 方法元数据
     */
    public static function getAspectMethods(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            $befores = self::getBefores($method);
            $afters = self::getAfters($method);
            $arounds = self::getArounds($method);

            if ($befores || $afters || $arounds) {
                $priority = self::getPriority($method);
                $methods[$method->getName()] = [
                    'befores' => $befores,
                    'afters' => $afters,
                    'arounds' => $arounds,
                    'priority' => $priority?->value ?? Priority::NORMAL,
                ];
            }
        }

        return $methods;
    }
}
