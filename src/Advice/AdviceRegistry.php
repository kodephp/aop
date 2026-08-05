<?php

declare(strict_types=1);

namespace Kode\Aop\Advice;

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Contract\AspectInterface;
use Kode\Aop\Exception\AopException;
use Kode\Aop\Pointcut\MatchContext;
use Kode\Aop\Pointcut\PointcutParser;
use Kode\Aop\Reflection\MetadataReader;
use Kode\Aop\Reflection\Reflector;
use ReflectionClass;

/**
 * 通知注册表
 *
 * 负责：
 * 1. 校验并登记切面对象；
 * 2. 把切面上的注解编译成 {@see Advice} 列表（切入点表达式只编译一次）；
 * 3. 按「目标类::目标方法」缓存匹配结果，避免每次调用都遍历全部切面。
 *
 * 这是 v3 相较 v2 性能提升的关键：v2 在每一次被代理的方法调用中都会
 * 重新遍历所有切面、重新做字符串匹配，v3 只在首次调用时计算并缓存。
 *
 * @package Kode\Aop\Advice
 * @author Kode Team <382601296@qq.com>
 */
final class AdviceRegistry
{
    /**
     * 已注册的切面对象
     *
     * @var array<int, object>
     */
    private array $aspects = [];

    /**
     * 已编译的通知列表
     *
     * @var array<int, Advice>
     */
    private array $advices = [];

    /**
     * 匹配结果缓存：`类名::方法名` => AdviceSet
     *
     * @var array<string, AdviceSet>
     */
    private array $matchCache = [];

    /**
     * 切面元数据缓存（兼容旧 API）
     *
     * @var array<string, array{class: ReflectionClass, methods: array<string, array<string, mixed>>}>
     */
    private array $metadata = [];

    /**
     * 注册一个切面对象
     *
     * @param object $aspect 切面实例
     * @throws AopException 切面非法或被禁用时抛出
     */
    public function register(object $aspect): void
    {
        $className = $aspect::class;
        $aspectMeta = $this->resolveAspectAttribute($className);

        if ($aspectMeta === null && !$aspect instanceof AspectInterface) {
            throw AopException::invalidAspect($className);
        }

        if ($aspectMeta !== null && !$aspectMeta->enabled) {
            return;
        }

        $this->aspects[] = $aspect;
        $priority = $aspectMeta !== null ? $aspectMeta->priority : 0;
        $this->compileAspect($aspect, $priority);
        $this->matchCache = [];
    }

    /**
     * 移除所有已注册切面
     */
    public function reset(): void
    {
        $this->aspects = [];
        $this->advices = [];
        $this->matchCache = [];
        $this->metadata = [];
    }

    /**
     * 获取已注册的切面对象列表
     *
     * @return array<int, object>
     */
    public function aspects(): array
    {
        return $this->aspects;
    }

    /**
     * 获取全部已编译通知
     *
     * @return array<int, Advice>
     */
    public function advices(): array
    {
        return $this->advices;
    }

    /**
     * 获取切面元数据（兼容旧 API）
     *
     * @return array<string, mixed>
     */
    public function metadataOf(object $aspect): array
    {
        return $this->metadata[$aspect::class] ?? [];
    }

    /**
     * 解析某个目标方法上命中的所有通知
     *
     * @param string $className 目标类名
     * @param string $methodName 目标方法名
     */
    public function resolve(string $className, string $methodName): AdviceSet
    {
        $key = $className . '::' . $methodName;

        if (isset($this->matchCache[$key])) {
            return $this->matchCache[$key];
        }

        if ($this->advices === []) {
            return $this->matchCache[$key] = AdviceSet::empty();
        }

        $context = new MatchContext($className, $methodName);

        $buckets = [
            AdviceType::Before->value => [],
            AdviceType::Around->value => [],
            AdviceType::AfterReturning->value => [],
            AdviceType::AfterThrowing->value => [],
            AdviceType::After->value => [],
        ];

        foreach ($this->advices as $advice) {
            if ($advice->matches($context)) {
                $buckets[$advice->type->value][] = $advice;
            }
        }

        foreach ($buckets as $type => $list) {
            $reversed = AdviceType::from($type)->isReversed();
            usort(
                $list,
                static fn(Advice $a, Advice $b): int => $reversed
                    ? $b->order <=> $a->order
                    : $a->order <=> $b->order
            );
            $buckets[$type] = $list;
        }

        return $this->matchCache[$key] = new AdviceSet(
            before: $buckets[AdviceType::Before->value],
            around: $buckets[AdviceType::Around->value],
            afterReturning: $buckets[AdviceType::AfterReturning->value],
            afterThrowing: $buckets[AdviceType::AfterThrowing->value],
            after: $buckets[AdviceType::After->value],
        );
    }

    /**
     * 判断目标方法上是否存在任何通知
     */
    public function hasAdvice(string $className, string $methodName): bool
    {
        return !$this->resolve($className, $methodName)->isEmpty();
    }

    /**
     * 判断目标类是否存在任何需要织入的方法
     */
    public function shouldProxy(string $className): bool
    {
        foreach (Reflector::getPublicMethods($className) as $method) {
            if ($this->hasAdvice($className, $method->getName())) {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算当前注册表状态的指纹
     *
     * 代理类名会带上该指纹，从而保证「切面集合发生变化后自动重新生成代理」，
     * 同时相同切面集合可以命中缓存。
     */
    public function fingerprint(): string
    {
        if ($this->advices === []) {
            return 'empty';
        }

        $parts = array_map(
            static fn(Advice $advice): string => $advice->aspect::class
                . '::' . $advice->aspectMethod
                . ':' . $advice->type->value
                . ':' . $advice->order
                . ':' . $advice->pointcut,
            $this->advices
        );

        sort($parts);

        return hash('xxh128', implode('|', $parts));
    }

    /**
     * 读取类上的 #[Aspect] 注解
     */
    private function resolveAspectAttribute(string $className): ?Aspect
    {
        $attributes = Reflector::getClass($className)->getAttributes(Aspect::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * 把一个切面对象编译成通知列表
     */
    private function compileAspect(object $aspect, int $aspectPriority): void
    {
        $className = $aspect::class;
        $named = MetadataReader::getPointcutDefinitions($className);
        $resolver = static fn(string $name): ?string => $named[$name] ?? null;

        $methods = MetadataReader::getAspectMethods($className);

        $this->metadata[$className] = [
            'class' => new ReflectionClass($className),
            'methods' => $methods,
        ];

        foreach ($methods as $methodName => $meta) {
            $order = $meta['priority'] !== 0 ? $meta['priority'] : $aspectPriority;

            $groups = [
                AdviceType::Before->value => $meta['befores'],
                AdviceType::Around->value => $meta['arounds'],
                    AdviceType::AfterReturning->value => $meta['afterReturnings'],
                    AdviceType::AfterThrowing->value => $meta['afterThrowings'],
                AdviceType::After->value => $meta['afters'],
            ];

            foreach ($groups as $type => $attributes) {
                foreach ($attributes as $attribute) {
                    $this->advices[] = new Advice(
                        aspect: $aspect,
                        aspectMethod: $methodName,
                        type: AdviceType::from($type),
                        pointcut: $attribute->pointcut,
                        order: $order,
                        matcher: PointcutParser::compile($attribute->pointcut, $resolver),
                        throwable: property_exists($attribute, 'throwable')
                            ? $attribute->throwable
                            : \Throwable::class,
                    );
                }
            }
        }
    }
}
