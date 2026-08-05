<?php

declare(strict_types=1);

namespace Kode\Aop\Reflection;

use Kode\Attributes\Attr;
use Kode\Attributes\CacheInterface;
use Kode\Attributes\Meta;
use ReflectionClass;
use ReflectionMethod;
use Kode\Aop\Reflection\Reflector;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\AfterReturning;
use Kode\Aop\Attribute\AfterThrowing;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Pointcut;
use Kode\Aop\Attribute\Priority;

/**
 * 元数据读取器
 *
 * 基于 kode/attributes 2.x 包实现的属性读取器，用于读取类、方法上的注解信息。
 *
 * 相对 1.x 的关键改进（均借助 kode/attributes 2.x 的新能力）：
 * - 统一走 {@see Attr} 门面，支持直接以 ReflectionClass / ReflectionMethod 等
 *   Reflector 实例作为目标（2.x 修复了 1.x 传入反射对象会被当作普通对象、
 *   静默读取"反射类自身"属性的致命缺陷）。
 * - 方法级属性读取开启 `inherited`（沿继承链合并），可正确发现定义在 trait 或
 *   父类中的通知方法。
 * - 支持注入共享缓存（{@see CacheInterface}，如 RedisCache / APCu），让多进程
 *   复用反射元数据；支持严格模式，属性实例化失败时立即抛出而非静默跳过。
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
     * 是否启用严格模式：属性实例化失败时抛出异常而非静默跳过。
     */
    private static bool $strict = true;

    /**
     * 可选的共享缓存实现（如 RedisCache / APCu），用于跨进程复用反射元数据。
     */
    private static ?CacheInterface $cache = null;

    /**
     * 标记 Attr 门面配置是否已应用（严格模式 / 缓存只需要在首次读取时应用一次）。
     */
    private static bool $configured = false;

    /**
     * 将本读取器的配置应用到 kode/attributes 的全局 Attr 门面。
     *
     * 2.x 起 Attr 门面持有单例 Reader，严格模式与缓存均为全局生效，
     * 因此只需在首次使用时应用一次即可；setStrict/setCache 会重新应用两者，
     * 确保严格模式与缓存配置互不丢失。
     */
    private static function configure(): void
    {
        if (self::$configured) {
            return;
        }

        self::applyConfig();
        self::$configured = true;
    }

    /**
     * 重新应用严格模式与缓存配置到 Attr 门面。
     */
    private static function applyConfig(): void
    {
        Attr::strict(self::$strict);

        if (self::$cache !== null) {
            Attr::setCache(self::$cache);
        }
    }

    /**
     * 设置严格模式（默认开启）。
     *
     * 开启后，任何无法实例化的通知属性会立即抛出异常，而不是被静默跳过，
     * 便于在开发期尽早暴露问题。
     */
    public static function setStrict(bool $strict): void
    {
        self::$strict = $strict;
        self::applyConfig();
        self::$configured = true;
    }

    /**
     * 注入共享缓存实现（如 RedisCache 用于多 worker 共享元数据）。
     *
     * @param CacheInterface $cache 缓存实现
     */
    public static function setCache(CacheInterface $cache): void
    {
        self::$cache = $cache;
        self::applyConfig();
        self::$configured = true;
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
        self::configure();

        // 2.x：直接以 ReflectionClass 作为目标，正确读取类级属性（1.x 会静默失效）。
        $meta = Attr::get($class, Aspect::class);

        /** @var Aspect|null $aspect */
        $aspect = $meta?->getInstance();

        return $aspect;
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
     * 获取方法上的返回后通知注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return array<int, AfterReturning> 返回后通知注解数组
     */
    public static function getAfterReturnings(ReflectionMethod $method): array
    {
        return self::getMethodAttributes($method, AfterReturning::class);
    }

    /**
     * 获取方法上的异常通知注解
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return array<int, AfterThrowing> 异常通知注解数组
     */
    public static function getAfterThrowings(ReflectionMethod $method): array
    {
        return self::getMethodAttributes($method, AfterThrowing::class);
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
        self::configure();

        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        $meta = Attr::ofMethod($className, $methodName, inherited: false)->get(Priority::class);

        /** @var Priority|null $priority */
        $priority = $meta?->getInstance();

        return $priority;
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
        self::configure();

        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        // 2.x：方法级属性读取统一走 Attr::ofMethod（替代 1.x 的私有 Reader）。
        // 此处使用 inherited:false（即仅读取该方法自身声明的属性）：对从 trait /
        // 父类继承来的方法，ReflectionMethod::getAttributes() 已包含其属性，
        // 而 inherited:true 反而会把 trait 方法的同一属性重复计入。
        $metas = Attr::ofMethod($className, $methodName, inherited: false)->getAll($attributeClass);

        /** @var array<int, T> $attributes */
        $attributes = array_map(
            static fn(Meta $meta): object => $meta->getInstance(),
            $metas->all()
        );

        return $attributes;
    }

    /**
     * 清空所有缓存
     *
     * 用于在测试环境或需要重新加载元数据时清空缓存。
     */
    public static function clearCache(): void
    {
        Attr::clear();
        self::$configured = false;
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
            'strict' => self::$strict,
            'cache' => self::$cache === null ? null : get_debug_type(self::$cache),
            'configured' => self::$configured,
            'note' => '属性元数据缓存由 kode/attributes 2.x 管理',
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
        self::configure();

        return Attr::has($className, Aspect::class);
    }

    /**
     * 获取切面类的所有通知方法
     *
     * @param string $className 切面类名
     * @return array<string, array{
     *     befores: array<int, Before>,
     *     afters: array<int, After>,
     *     arounds: array<int, Around>,
     *     afterReturnings: array<int, AfterReturning>,
     *     afterThrowings: array<int, AfterThrowing>,
     *     priority: int
     * }> 方法元数据
     */
    public static function getAspectMethods(string $className): array
    {
        $reflection = Reflector::getClass($className);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                continue;
            }

            $befores = self::getBefores($method);
            $afters = self::getAfters($method);
            $arounds = self::getArounds($method);
            $afterReturnings = self::getAfterReturnings($method);
            $afterThrowings = self::getAfterThrowings($method);

            if ($befores || $afters || $arounds || $afterReturnings || $afterThrowings) {
                $priority = self::getPriority($method);
                $methods[$method->getName()] = [
                    'befores' => $befores,
                    'afters' => $afters,
                    'arounds' => $arounds,
                    'afterReturnings' => $afterReturnings,
                    'afterThrowings' => $afterThrowings,
                    'priority' => $priority === null ? Priority::NORMAL : $priority->value,
                ];
            }
        }

        return $methods;
    }

    /**
     * 获取切面类中通过 #[Pointcut] 定义的命名切点
     *
     * 方法名即切点名，注解的 expression 即其表达式，可在其他通知中以
     * `切点名()` 的形式引用。
     *
     * @param string $className 切面类名
     * @return array<string, string> 切点名 => 表达式
     */
    public static function getPointcutDefinitions(string $className): array
    {
        $reflection = Reflector::getClass($className);
        $definitions = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach (self::getPointcuts($method) as $pointcut) {
                $definitions[$method->getName()] = $pointcut->expression;
            }
        }

        return $definitions;
    }
}
