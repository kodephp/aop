<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use Closure;
use Kode\Aop\Advice\AdviceExecutor;
use Kode\Aop\Advice\AdviceRegistry;
use Kode\Aop\Advice\AdviceSet;
use Kode\Aop\Contract\AspectKernelInterface;
use Kode\Aop\Contract\ProxyInterface;
use Kode\Aop\Exception\AopException;
use Kode\Aop\Pointcut\MatchContext;
use Kode\Aop\Pointcut\PointcutParser;
use Kode\Aop\Proxy\ProxyFactory;
use Kode\Aop\Reflection\MetadataReader;
use Kode\Aop\Reflection\Reflector;
use ReflectionClass;
use ReflectionMethod;

/**
 * AOP 内核
 *
 * 框架的中枢，把三个职责清晰的组件粘合起来：
 * - {@see AdviceRegistry}：切面登记、切入点编译、匹配结果缓存
 * - {@see ProxyFactory}：代理类生成、命名与缓存
 * - {@see AdviceExecutor}：运行期通知编排
 *
 * 典型用法：
 * ```php
 * $kernel = AspectKernel::getInstance();
 * $kernel->registerAspect(new LoggingAspect());
 * $kernel->setCacheDir(__DIR__ . '/runtime/aop');   // 可选：文件缓存
 *
 * $service = $kernel->getProxy(UserService::class);
 * $service->getUser(1);
 * ```
 *
 * @package Kode\Aop\Runtime
 * @author Kode Team <382601296@qq.com>
 * @see AspectKernelInterface
 */
class AspectKernel implements AspectKernelInterface
{
    /**
     * 单例实例
     */
    protected static ?AspectKernel $instance = null;

    /**
     * 通知注册表
     */
    protected AdviceRegistry $registry;

    /**
     * 代理工厂
     */
    protected ProxyFactory $factory;

    /**
     * 通知执行器
     */
    protected AdviceExecutor $executor;

    /**
     * 原始类反射缓存
     *
     * @var array<string, ReflectionClass>
     */
    protected array $classCache = [];

    /**
     * 目标方法反射缓存
     *
     * @var array<string, ReflectionMethod>
     */
    protected array $methodCache = [];

    /**
     * 是否已初始化
     */
    protected bool $initialized = false;

    /**
     * AOP 总开关，关闭后代理方法直接透传
     */
    protected bool $enabled = true;

    /**
     * @param string|null $cacheDir 代理类文件缓存目录，null 表示使用 eval()
     */
    public function __construct(?string $cacheDir = null)
    {
        $this->registry = new AdviceRegistry();
        $this->factory = new ProxyFactory($this->registry, $cacheDir);
        $this->executor = new AdviceExecutor();
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): AspectKernel
    {
        return self::$instance ??= new self();
    }

    /**
     * 重置单例实例（主要用于测试）
     */
    public static function resetInstance(): void
    {
        self::$instance?->reset();
        self::$instance = null;
        PointcutParser::clearCache();
    }

    /**
     * 清空本内核的全部状态
     */
    public function reset(): void
    {
        $this->registry->reset();
        $this->factory->reset();
        $this->classCache = [];
        $this->methodCache = [];
        $this->initialized = false;
        $this->enabled = true;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function registerAspect(object $aspect): void
    {
        $this->registry->register($aspect);
        $this->factory->reset();
    }

    /**
     * 批量注册切面
     *
     * 支持传入切面实例，或可无参实例化的切面类名。
     *
     * @param array<int, object|class-string> $aspects
     * @throws AopException 切面非法时抛出
     */
    public function registerAspects(array $aspects): void
    {
        foreach ($aspects as $aspect) {
            if (is_string($aspect)) {
                if (!class_exists($aspect)) {
                    throw AopException::classNotFound($aspect);
                }

                $aspect = new $aspect();
            }

            $this->registerAspect($aspect);
        }
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getProxy(string $className, array $constructorArgs = []): object
    {
        return $this->factory->create($className, $this, $constructorArgs);
    }

    /**
     * 获取目标类对应的代理类名
     *
     * 适合与 DI 容器集成：容器拿到类名后自行决定如何实例化。
     *
     * @param string $className 目标类名
     * @return string|null 代理类名，null 表示该类无需代理
     */
    public function getProxyClass(string $className): ?string
    {
        return $this->factory->proxyClassFor($className, $this);
    }

    /**
     * 把已存在的实例包装为代理对象
     *
     * @param object $instance 已构造好的实例
     * @return object 代理实例；若该类无需代理则原样返回
     */
    public function wrap(object $instance): object
    {
        return $this->factory->wrap($instance, $this);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
    }

    /**
     * 设置代理类文件缓存目录
     *
     * 落盘为真实 PHP 文件后可被 OPcache 缓存，生产环境建议开启。
     *
     * @param string|null $directory 目录路径，null 表示改用 eval()
     */
    public function setCacheDir(?string $directory): self
    {
        $this->factory->setCacheDir($directory);

        return $this;
    }

    /**
     * 获取代理类文件缓存目录
     */
    public function getCacheDir(): ?string
    {
        return $this->factory->getCacheDir();
    }

    /**
     * 开启 AOP 织入
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * 关闭 AOP 织入
     *
     * 关闭后已生成的代理对象会直接调用原方法，便于压测对比或临时排障。
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * AOP 是否处于开启状态
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function invokeAdvice(object $target, string $method, array $arguments, Closure $invoker): mixed
    {
        if (!$this->enabled) {
            return $invoker(...$arguments);
        }

        $className = $target instanceof ProxyInterface ? $target::__aopTargetClass() : $target::class;
        $set = $this->registry->resolve($className, $method);

        if ($set->isEmpty()) {
            return $invoker(...$arguments);
        }

        return $this->executor->execute(
            $set,
            $target,
            $this->reflectClass($className),
            $this->reflectMethod($className, $method),
            array_values($arguments),
            $invoker
        );
    }

    /**
     * 解析目标方法上命中的通知集合
     */
    public function resolveAdvices(string $className, string $methodName): AdviceSet
    {
        return $this->registry->resolve($className, $methodName);
    }

    /**
     * 判断目标方法上是否存在指定类型的通知
     *
     * @param string $adviceType 通知类型，兼容旧版的 befores/afters/arounds 写法
     */
    public function hasMatchingAdvice(string $className, string $methodName, string $adviceType): bool
    {
        $set = $this->registry->resolve($className, $methodName);

        return match ($adviceType) {
            'befores', 'before' => $set->before !== [],
            'afters', 'after' => $set->after !== [],
            'arounds', 'around' => $set->around !== [],
            'afterReturnings', 'afterReturning' => $set->afterReturning !== [],
            'afterThrowings', 'afterThrowing' => $set->afterThrowing !== [],
            default => false,
        };
    }

    /**
     * 判断类名+方法名是否匹配给定切入点表达式
     *
     * @param string $className 目标类名
     * @param string $methodName 目标方法名
     * @param string $pointcut 切入点表达式
     */
    public function matchesPointcut(string $className, string $methodName, string $pointcut): bool
    {
        if (trim($pointcut) === '') {
            return false;
        }

        try {
            $matcher = PointcutParser::compile($pointcut);
        } catch (AopException) {
            return false;
        }

        return $matcher(new MatchContext($className, $methodName));
    }

    /**
     * 获取已注册的切面对象列表
     *
     * @return array<int, object>
     */
    public function getRegisteredAspects(): array
    {
        return $this->registry->aspects();
    }

    /**
     * 获取切面元数据
     *
     * @return array<string, mixed>
     */
    public function getAspectMetadata(object $aspect): array
    {
        return $this->registry->metadataOf($aspect);
    }

    /**
     * 获取通知注册表
     */
    public function getRegistry(): AdviceRegistry
    {
        return $this->registry;
    }

    /**
     * 获取代理工厂
     */
    public function getProxyFactory(): ProxyFactory
    {
        return $this->factory;
    }

    /**
     * 获取运行期诊断信息
     *
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        return [
            'enabled' => $this->enabled,
            'aspects' => count($this->registry->aspects()),
            'advices' => count($this->registry->advices()),
            'fingerprint' => $this->registry->fingerprint(),
            'cacheDir' => $this->factory->getCacheDir(),
            'skippedMethods' => $this->factory->skippedMethods(),
            'metadata' => MetadataReader::getCacheStats(),
        ];
    }

    /**
     * 带缓存的类反射
     */
    protected function reflectClass(string $className): ReflectionClass
    {
        return $this->classCache[$className] ??= Reflector::getClass($className);
    }

    /**
     * 带缓存的方法反射
     */
    protected function reflectMethod(string $className, string $methodName): ReflectionMethod
    {
        return $this->methodCache[$className . '::' . $methodName]
            ??= Reflector::getMethod($className, $methodName);
    }
}
