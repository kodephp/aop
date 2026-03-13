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
 * 用于读取类、方法上的注解信息，并提供缓存机制以提高性能。
 * 所有读取操作都会被缓存，避免重复的反射操作。
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
     * 类元数据缓存
     *
     * @var array<string, array>
     */
    private static array $classMetadata = [];

    /**
     * 方法元数据缓存
     *
     * @var array<string, array>
     */
    private static array $methodMetadata = [];

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
        $className = $class->getName();

        return self::$classMetadata[$className]['aspect'] ??= (function () use ($class) {
            $attributes = $class->getAttributes(Aspect::class);
            return $attributes ? $attributes[0]->newInstance() : null;
        })();
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
        return self::$methodMetadata[self::getMethodKey($method)]['befores'] ??= array_map(
            static fn($attr) => $attr->newInstance(),
            $method->getAttributes(Before::class)
        );
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
        return self::$methodMetadata[self::getMethodKey($method)]['afters'] ??= array_map(
            static fn($attr) => $attr->newInstance(),
            $method->getAttributes(After::class)
        );
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
        return self::$methodMetadata[self::getMethodKey($method)]['arounds'] ??= array_map(
            static fn($attr) => $attr->newInstance(),
            $method->getAttributes(Around::class)
        );
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
        return self::$methodMetadata[self::getMethodKey($method)]['pointcuts'] ??= array_map(
            static fn($attr) => $attr->newInstance(),
            $method->getAttributes(Pointcut::class)
        );
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
        return self::$methodMetadata[self::getMethodKey($method)]['priority'] ??= (function () use ($method) {
            $attributes = $method->getAttributes(Priority::class);
            return $attributes ? $attributes[0]->newInstance() : null;
        })();
    }

    /**
     * 获取方法缓存键
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return string 缓存键
     */
    private static function getMethodKey(ReflectionMethod $method): string
    {
        return $method->getDeclaringClass()->getName() . '::' . $method->getName();
    }

    /**
     * 清空所有缓存
     *
     * 用于在测试环境或需要重新加载元数据时清空缓存。
     */
    public static function clearCache(): void
    {
        self::$classMetadata = [];
        self::$methodMetadata = [];
    }

    /**
     * 获取缓存统计信息
     *
     * 用于调试和性能分析。
     *
     * @return array{classes: int, methods: int} 缓存统计
     */
    public static function getCacheStats(): array
    {
        return [
            'classes' => count(self::$classMetadata),
            'methods' => count(self::$methodMetadata),
        ];
    }
}
