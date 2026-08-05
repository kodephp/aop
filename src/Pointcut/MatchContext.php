<?php

declare(strict_types=1);

namespace Kode\Aop\Pointcut;

use ReflectionClass;
use ReflectionMethod;

/**
 * 切入点匹配上下文
 *
 * 承载一次切入点匹配所需的全部信息：目标类名、目标方法名，
 * 以及按需惰性创建的反射对象。反射对象仅在表达式确实需要时
 * （例如 @annotation、修饰符匹配）才会被创建，避免无谓开销。
 *
 * @package Kode\Aop\Pointcut
 * @author Kode Team <382601296@qq.com>
 */
final class MatchContext
{
    /**
     * 类反射对象缓存
     */
    private ?ReflectionClass $classReflection = null;

    /**
     * 方法反射对象缓存
     */
    private ?ReflectionMethod $methodReflection = null;

    /**
     * 反射对象是否已尝试解析
     */
    private bool $resolved = false;

    /**
     * @param string $className 目标类全限定名
     * @param string $methodName 目标方法名
     * @param ReflectionMethod|null $method 已知的方法反射对象（可选）
     */
    public function __construct(
        public readonly string $className,
        public readonly string $methodName,
        ?ReflectionMethod $method = null
    ) {
        if ($method !== null) {
            $this->methodReflection = $method;
            $this->classReflection = $method->getDeclaringClass();
            $this->resolved = true;
        }
    }

    /**
     * 获取方法反射对象（惰性解析，失败返回 null）
     */
    public function method(): ?ReflectionMethod
    {
        $this->resolve();

        return $this->methodReflection;
    }

    /**
     * 获取类反射对象（惰性解析，失败返回 null）
     */
    public function class(): ?ReflectionClass
    {
        $this->resolve();

        return $this->classReflection;
    }

    /**
     * 获取签名字符串，形如 `App\Service\UserService->createUser`
     */
    public function signature(): string
    {
        return $this->className . '->' . $this->methodName;
    }

    /**
     * 惰性解析反射对象
     */
    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;

        if (!class_exists($this->className) && !interface_exists($this->className)) {
            return;
        }

        try {
            $this->classReflection = new ReflectionClass($this->className);

            if ($this->classReflection->hasMethod($this->methodName)) {
                $this->methodReflection = $this->classReflection->getMethod($this->methodName);
            }
        } catch (\ReflectionException) {
            $this->classReflection = null;
            $this->methodReflection = null;
        }
    }
}
