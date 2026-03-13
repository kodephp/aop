<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

/**
 * AOP 内核接口
 *
 * 定义 AOP 内核的核心功能，包括切面注册、代理对象生成和内核初始化。
 * AspectKernel 是整个 AOP 框架的核心调度器。
 *
 * 使用示例：
 * ```php
 * // 创建 AOP 内核
 * $kernel = new AspectKernel();
 *
 * // 注册切面
 * $kernel->registerAspect(new LoggingAspect());
 * $kernel->registerAspect(new TransactionAspect());
 *
 * // 初始化内核
 * $kernel->init();
 *
 * // 获取代理对象
 * $userService = $kernel->getProxy(UserService::class);
 * ```
 *
 * @package Kode\Aop\Contract
 * @author Kode Team <382601296@qq.com>
 */
interface AspectKernelInterface
{
    /**
     * 注册切面
     *
     * 将一个切面对象注册到 AOP 内核中。注册后，切面中定义的通知
     * 将会应用到匹配的目标方法上。
     *
     * 切面对象必须实现 AspectInterface 接口或使用 #[Aspect] 注解标记。
     *
     * @param object $aspect 切面对象实例
     *
     * @throws \Kode\Aop\Exception\AopException 如果切面不符合要求
     *
     * @example
     * ```php
     * $kernel->registerAspect(new LoggingAspect());
     * $kernel->registerAspect(new TransactionAspect());
     * ```
     */
    public function registerAspect(object $aspect): void;

    /**
     * 获取代理对象
     *
     * 根据类名获取该类的代理对象。代理对象会拦截方法调用，
     * 并执行匹配的切面通知。
     *
     * 注意：
     * - 返回的代理对象继承自原始类
     * - 代理对象会在方法调用前后执行匹配的通知
     * - 每次调用都会创建新的代理对象实例
     *
     * @param string $className 原始类名
     * @return object 代理对象实例
     *
     * @throws \Kode\Aop\Exception\AopException 如果类不存在或无法创建代理
     *
     * @example
     * ```php
     * $userService = $kernel->getProxy(UserService::class);
     * $result = $userService->createUser(['name' => 'John']);
     * ```
     */
    public function getProxy(string $className): object;

    /**
     * 初始化内核
     *
     * 初始化 AOP 内核，包括：
     * - 清理和预热缓存
     * - 解析已注册切面的元数据
     * - 准备代理生成器
     *
     * 建议在应用启动时调用此方法，以提高首次方法调用的性能。
     *
     * @example
     * ```php
     * $kernel = new AspectKernel();
     * $kernel->registerAspect(new LoggingAspect());
     * $kernel->init(); // 初始化内核
     * ```
     */
    public function init(): void;
}
