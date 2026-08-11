<?php

declare(strict_types=1);

namespace Kode\Aop\Proxy;

use Kode\Aop\Advice\AdviceRegistry;
use Kode\Aop\Contract\AspectKernelInterface;
use Kode\Aop\Exception\AopException;
use Kode\Aop\Reflection\Reflector;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;

/**
 * 代理类工厂
 *
 * 负责代理类的命名、生成、缓存与实例化。
 *
 * 缓存策略：
 * 1. **进程内缓存** —— 同一请求内同一类只生成一次；
 * 2. **文件缓存（可选）** —— 指定 cacheDir 后代理类会落盘为真实 PHP 文件，
 *    可被 OPcache 编译缓存，比 eval() 更快且便于调试；
 * 3. **指纹隔离** —— 代理类名包含切面集合指纹，切面变化会自动生成新代理类，
 *    不会出现「改了切面但代理没更新」的脏缓存问题。
 *
 * @package Kode\Aop\Proxy
 * @author Kode Team <382601296@qq.com>
 */
final class ProxyFactory
{
    /**
     * 进程内代理类缓存：`目标类|指纹` => 代理类名 或 false（无需代理）
     *
     * @var array<string, class-string|false>
     */
    private array $cache = [];

    /**
     * 源码生成器
     */
    private ProxyGenerator $generator;

    /**
     * 因不可织入而被跳过的方法：`类名` => [方法名 => 原因]
     *
     * @var array<string, array<string, string>>
     */
    private array $skipped = [];

    /**
     * @param AdviceRegistry $registry 通知注册表
     * @param string|null $cacheDir 代理类文件缓存目录，null 表示使用 eval()
     */
    public function __construct(
        private readonly AdviceRegistry $registry,
        private ?string $cacheDir = null
    ) {
        $this->generator = new ProxyGenerator();
    }

    /**
     * 设置代理类文件缓存目录
     *
     * @param string|null $directory 目录路径，null 关闭文件缓存
     */
    public function setCacheDir(?string $directory): void
    {
        $this->cacheDir = $directory;
        $this->cache = [];
    }

    /**
     * 获取代理类文件缓存目录
     */
    public function getCacheDir(): ?string
    {
        return $this->cacheDir;
    }

    /**
     * 清空进程内缓存
     */
    public function reset(): void
    {
        $this->cache = [];
        $this->skipped = [];
    }

    /**
     * 获取被跳过的方法及原因，便于排查「为什么没生效」
     *
     * @return array<string, array<string, string>>
     */
    public function skippedMethods(): array
    {
        return $this->skipped;
    }

    /**
     * 获取（必要时生成）目标类的代理类名
     *
     * @param string $className 目标类名
     * @param AspectKernelInterface $kernel 归属内核
     * @return string|null 代理类名；返回 null 表示该类无需代理
     * @throws AopException 目标类无法被代理时抛出
     */
    public function proxyClassFor(string $className, AspectKernelInterface $kernel): ?string
    {
        $className = ltrim($className, '\\');
        $key = $className . '|' . $this->registry->fingerprint();

        if (array_key_exists($key, $this->cache)) {
            $cached = $this->cache[$key];

            if ($cached === false) {
                return null;
            }

            $this->bindKernel($cached, $kernel);

            return $cached;
        }

        $class = Reflector::getClass($className);
        $methods = $this->collectWeavableMethods($class);

        if ($methods === []) {
            $this->cache[$key] = false;

            return null;
        }

        $this->generator->assertProxyable($class);

        $suffix = substr(hash('xxh128', $key), 0, 16);
        $shortName = $class->getShortName() . '__AopProxy_' . $suffix;
        $namespace = $class->getNamespaceName();
        $proxyClass = $namespace === '' ? $shortName : $namespace . '\\' . $shortName;

        if (!class_exists($proxyClass, false)) {
            $this->materialize($class, $shortName, $proxyClass, $methods);
        }

        /** @var class-string $proxyClass */
        $this->bindKernel($proxyClass, $kernel);
        $this->cache[$key] = $proxyClass;

        return $proxyClass;
    }

    /**
     * 创建代理实例
     *
     * @param string $className 目标类名
     * @param array<int|string, mixed> $constructorArgs 传给目标类构造函数的参数
     * @throws AopException 目标类无法被代理时抛出
     */
    public function create(string $className, AspectKernelInterface $kernel, array $constructorArgs = []): object
    {
        $proxyClass = $this->proxyClassFor($className, $kernel);

        if ($proxyClass === null) {
            return new $className(...$constructorArgs);
        }

        // 组合式代理（final 类）的构造函数接收「单一数组参数」，需原样透传而非展开
        if (Reflector::getClass($className)->isFinal()) {
            return new $proxyClass($constructorArgs);
        }

        return new $proxyClass(...$constructorArgs);
    }

    /**
     * 把一个已存在的实例包装为代理
     *
     * 会跳过构造函数，并逐一复制原实例上的全部属性（含私有属性），
     * 适合由 DI 容器创建好对象后再织入的场景。
     *
     * @param object $instance 已存在的实例
     * @throws AopException 目标类无法被代理时抛出
     */
    public function wrap(object $instance, AspectKernelInterface $kernel): object
    {
        if ($instance instanceof \Kode\Aop\Contract\ProxyInterface) {
            return $instance;
        }

        $proxyClass = $this->proxyClassFor($instance::class, $kernel);

        if ($proxyClass === null) {
            return $instance;
        }

        $proxy = Reflector::getClass($proxyClass)->newInstanceWithoutConstructor();

        // 组合式代理（final 类）：属性都在被包装实例上，直接绑定即可，无需拷贝。
        if (Reflector::getClass($instance::class)->isFinal()) {
            $proxyReflection = Reflector::getClass($proxyClass);
            $proxy = $proxyReflection->newInstanceWithoutConstructor();
            $proxyReflection->getMethod('__aopBind')->invoke($proxy, $instance);

            return $proxy;
        }

        // 继承式代理：属性直接存在于代理实例上，需逐一复制原实例属性。
        $source = new ReflectionObject($instance);

        foreach ($source->getProperties() as $property) {
            if ($property->isStatic() || !$property->isInitialized($instance)) {
                continue;
            }

            $property->setValue($proxy, $property->getValue($instance));
        }

        return $proxy;
    }

    /**
     * 收集目标类中需要生成代理方法的方法
     *
     * - 继承式（非 final）：仅收集命中通知且可织入的方法（含 protected）。
     * - 组合式（final）：收集命中通知的公开方法，并补齐目标实现的全部接口方法，
     *   以满足 `implements` 契约（无通知的接口方法仅做透传转发）。
     *
     * @return array<int, ReflectionMethod>
     */
    private function collectWeavableMethods(ReflectionClass $class): array
    {
        $className = $class->getName();
        $composition = $class->isFinal();
        $filter = $composition ? ReflectionMethod::IS_PUBLIC : (ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);
        $collected = [];

        foreach ($class->getMethods($filter) as $method) {
            if (!$this->registry->hasAdvice($className, $method->getName())) {
                continue;
            }

            if (!$this->generator->isWeavable($method, $reason, $composition)) {
                $this->skipped[$className][$method->getName()] = (string) $reason;

                continue;
            }

            $collected[$method->getName()] = $method;
        }

        if ($composition) {
            foreach ($class->getInterfaces() as $interface) {
                foreach ($interface->getMethods() as $method) {
                    $name = $method->getName();

                    if (isset($collected[$name])) {
                        continue;
                    }

                    if (!$this->generator->isWeavable($method, $reason, true)) {
                        continue;
                    }

                    $collected[$name] = $method;
                }
            }
        }

        return array_values($collected);
    }

    /**
     * 生成并载入代理类
     *
     * @param array<int, ReflectionMethod> $methods
     */
    private function materialize(ReflectionClass $class, string $shortName, string $proxyClass, array $methods): void
    {
        if ($this->cacheDir === null) {
            eval($this->generator->generate($class, $shortName, $methods));

            return;
        }

        if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0o775, true) && !is_dir($this->cacheDir)) {
            throw AopException::proxyGenerationFailed(
                $class->getName(),
                sprintf('无法创建代理缓存目录: %s', $this->cacheDir)
            );
        }

        $file = rtrim($this->cacheDir, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace('\\', '_', $proxyClass)
            . '.php';

        if (!is_file($file)) {
            $code = $this->generator->generate($class, $shortName, $methods, true);
            $temp = $file . '.' . getmypid() . '.tmp';

            if (file_put_contents($temp, $code, LOCK_EX) === false || !@rename($temp, $file)) {
                @unlink($temp);

                throw AopException::proxyGenerationFailed(
                    $class->getName(),
                    sprintf('代理类文件写入失败: %s', $file)
                );
            }
        }

        require_once $file;

        if (!class_exists($proxyClass, false)) {
            throw AopException::proxyGenerationFailed(
                $class->getName(),
                sprintf('代理类文件已加载但未定义类 %s，请清理缓存目录 %s', $proxyClass, $this->cacheDir)
            );
        }
    }

    /**
     * 把内核实例绑定到代理类的静态属性上
     *
     * @param class-string $proxyClass
     */
    private function bindKernel(string $proxyClass, AspectKernelInterface $kernel): void
    {
        $proxyClass::$__aopKernel = $kernel;
    }
}
