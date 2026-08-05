<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use Kode\Aop\Contract\JoinPointInterface;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * 连接点实现类
 *
 * 封装了方法调用的完整上下文信息：目标类、目标方法、目标对象、
 * 调用参数、返回值与异常。
 *
 * 与 v2 不同，v3 在一次方法调用中只创建**一个**连接点实例并共享给
 * 所有通知，因此 Before 通知调用 {@see setArguments()} 修改的参数
 * 会真正作用于目标方法，AfterReturning 也能读到真实返回值。
 *
 * @package Kode\Aop\Runtime
 * @author Kode Team <382601296@qq.com>
 * @see JoinPointInterface
 */
class JoinPoint implements JoinPointInterface
{
    /**
     * 目标方法返回值
     */
    protected mixed $result = null;

    /**
     * 目标方法抛出的异常
     */
    protected ?Throwable $exception = null;

    /**
     * @param ReflectionClass $class 目标类的反射对象
     * @param ReflectionMethod $method 目标方法的反射对象
     * @param object $object 目标对象实例
     * @param array<int|string, mixed> $arguments 方法参数数组
     * @param string $pointcut 切入点表达式
     * @param mixed $result 方法返回值（用于 After 系列通知）
     */
    public function __construct(
        protected readonly ReflectionClass $class,
        protected readonly ReflectionMethod $method,
        protected readonly object $object,
        protected array $arguments,
        protected readonly string $pointcut = '',
        mixed $result = null
    ) {
        $this->result = $result;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getClass(): ReflectionClass
    {
        return $this->class;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getMethod(): ReflectionMethod
    {
        return $this->method;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getThis(): object
    {
        return $this->object;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function setArguments(array $args): void
    {
        $this->arguments = $args;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getPointcut(): string
    {
        return $this->pointcut;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * 设置目标方法返回值
     *
     * 在 AfterReturning 通知中调用可替换最终返回给调用方的结果。
     */
    public function setResult(mixed $result): void
    {
        $this->result = $result;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    /**
     * 记录目标方法抛出的异常
     *
     * @internal 由内核调用
     */
    public function setException(?Throwable $exception): void
    {
        $this->exception = $exception;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getMethodName(): string
    {
        return $this->method->getName();
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getClassName(): string
    {
        return $this->class->getName();
    }

    /**
     * 获取指定位置或名称的参数
     *
     * @param int|string $key 参数下标或参数名
     * @param mixed $default 取不到时的默认值
     */
    public function getArgument(int|string $key, mixed $default = null): mixed
    {
        if (is_int($key)) {
            return $this->arguments[$key] ?? $default;
        }

        $index = $this->indexOfParameter($key);

        return $index === null ? $default : ($this->arguments[$index] ?? $default);
    }

    /**
     * 按位置或名称设置参数
     *
     * @param int|string $key 参数下标或参数名
     * @param mixed $value 参数值
     */
    public function setArgument(int|string $key, mixed $value): void
    {
        if (is_int($key)) {
            $this->arguments[$key] = $value;

            return;
        }

        $index = $this->indexOfParameter($key);

        if ($index !== null) {
            $this->arguments[$index] = $value;
        }
    }

    /**
     * 以「参数名 => 值」形式返回全部参数
     *
     * @return array<string, mixed>
     */
    public function getNamedArguments(): array
    {
        $named = [];

        foreach ($this->method->getParameters() as $parameter) {
            $position = $parameter->getPosition();

            if (array_key_exists($position, $this->arguments)) {
                $named[$parameter->getName()] = $this->arguments[$position];
            }
        }

        return $named;
    }

    /**
     * 获取形如 `App\Service\UserService::createUser` 的签名
     */
    public function getSignature(): string
    {
        return $this->getClassName() . '::' . $this->getMethodName();
    }

    /**
     * 查找参数名对应的位置
     */
    private function indexOfParameter(string $name): ?int
    {
        foreach ($this->method->getParameters() as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter->getPosition();
            }
        }

        return null;
    }
}
