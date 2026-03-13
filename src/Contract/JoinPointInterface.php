<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

use ReflectionClass;
use ReflectionMethod;

/**
 * 连接点接口
 *
 * 连接点代表程序执行过程中的某个特定点，在 AOP 中通常指方法的调用点。
 * 此接口封装了方法调用的完整上下文信息，包括：
 * - 目标类的反射信息
 * - 目标方法的反射信息
 * - 目标对象实例
 * - 方法参数
 * - 切入点表达式
 *
 * 连接点对象会在通知方法执行时自动创建并传入，开发者可以通过它获取或修改方法调用的相关信息。
 *
 * @package Kode\Aop\Contract
 * @author Kode Team <382601296@qq.com>
 */
interface JoinPointInterface
{
    /**
     * 获取目标类的反射对象
     *
     * 返回目标类的 ReflectionClass 实例，可用于获取类的元数据信息。
     *
     * @return ReflectionClass 目标类的反射对象
     *
     * @example
     * ```php
     * $className = $joinPoint->getClass()->getName();
     * $namespace = $joinPoint->getClass()->getNamespaceName();
     * ```
     */
    public function getClass(): ReflectionClass;

    /**
     * 获取目标方法的反射对象
     *
     * 返回目标方法的 ReflectionMethod 实例，可用于获取方法的元数据信息。
     *
     * @return ReflectionMethod 目标方法的反射对象
     *
     * @example
     * ```php
     * $methodName = $joinPoint->getMethod()->getName();
     * $returnType = $joinPoint->getMethod()->getReturnType();
     * ```
     */
    public function getMethod(): ReflectionMethod;

    /**
     * 获取目标对象实例
     *
     * 返回被代理的目标对象实例，可以用于调用对象的其他方法或访问属性。
     *
     * @return object 目标对象实例
     *
     * @example
     * ```php
     * $object = $joinPoint->getThis();
     * $className = get_class($object);
     * ```
     */
    public function getThis(): object;

    /**
     * 获取方法参数
     *
     * 返回方法调用的参数数组，参数按顺序排列。
     *
     * @return array 方法参数数组
     *
     * @example
     * ```php
     * $args = $joinPoint->getArguments();
     * $firstArg = $args[0] ?? null;
     * ```
     */
    public function getArguments(): array;

    /**
     * 设置方法参数
     *
     * 用于修改方法调用的参数，设置后后续通知和目标方法将使用新的参数值。
     * 注意：参数数组的顺序必须与方法签名一致。
     *
     * @param array $args 新的方法参数数组
     *
     * @example
     * ```php
     * $args = $joinPoint->getArguments();
     * $args[0] = trim($args[0]); // 修改第一个参数
     * $joinPoint->setArguments($args);
     * ```
     */
    public function setArguments(array $args): void;

    /**
     * 获取切入点表达式
     *
     * 返回匹配此连接点的切入点表达式字符串。
     *
     * @return string 切入点表达式
     *
     * @example
     * ```php
     * $pointcut = $joinPoint->getPointcut();
     * // 例如: "execution(* App\Service\UserService->createUser(..))"
     * ```
     */
    public function getPointcut(): string;
}
