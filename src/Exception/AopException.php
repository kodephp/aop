<?php

declare(strict_types=1);

namespace Kode\Aop\Exception;

use Exception;
use Throwable;

/**
 * AOP 异常类
 *
 * AOP 框架中所有异常的基类。
 * 提供了丰富的异常信息，便于调试和错误处理。
 *
 * @package Kode\Aop\Exception
 * @author Kode Team <382601296@qq.com>
 */
class AopException extends Exception
{
    /**
     * 异常上下文信息
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param int $code 异常代码
     * @param Throwable|null $previous 前一个异常
     * @param array<string, mixed> $context 异常上下文信息
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * 获取异常上下文信息
     *
     * @return array<string, mixed> 上下文信息
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 设置异常上下文信息
     *
     * @param array<string, mixed> $context 上下文信息
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * 添加上下文信息
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return self
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * 创建切面无效异常
     *
     * @param string $className 类名
     * @return self
     */
    public static function invalidAspect(string $className): self
    {
        return new self(
            sprintf('切面类 %s 必须实现 AspectInterface 接口或使用 #[Aspect] 注解标记', $className),
            1001
        );
    }

    /**
     * 创建类不存在异常
     *
     * @param string $className 类名
     * @return self
     */
    public static function classNotFound(string $className): self
    {
        return new self(
            sprintf('类 %s 不存在', $className),
            1002,
            null,
            ['class' => $className]
        );
    }

    /**
     * 创建方法不存在异常
     *
     * @param string $className 类名
     * @param string $methodName 方法名
     * @return self
     */
    public static function methodNotFound(string $className, string $methodName): self
    {
        return new self(
            sprintf('方法 %s::%s 不存在', $className, $methodName),
            1003,
            null,
            ['class' => $className, 'method' => $methodName]
        );
    }

    /**
     * 创建切入点表达式无效异常
     *
     * @param string $expression 切入点表达式
     * @return self
     */
    public static function invalidPointcutExpression(string $expression): self
    {
        return new self(
            sprintf('切入点表达式无效: %s', $expression),
            1004,
            null,
            ['expression' => $expression]
        );
    }

    /**
     * 创建代理生成失败异常
     *
     * @param string $className 类名
     * @param string $reason 原因
     * @return self
     */
    public static function proxyGenerationFailed(string $className, string $reason): self
    {
        return new self(
            sprintf('无法为类 %s 生成代理: %s', $className, $reason),
            1005,
            null,
            ['class' => $className, 'reason' => $reason]
        );
    }
}
