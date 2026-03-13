<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

/**
 * 继续执行连接点接口
 *
 * 继承自 JoinPointInterface，专门用于 Around（环绕）通知。
 * 提供了 proceed() 方法，允许控制是否继续执行原方法。
 *
 * Around 通知是最强大的通知类型，可以：
 * - 完全控制目标方法的执行
 * - 决定是否执行原方法
 * - 修改方法参数
 * - 修改返回值
 * - 捕获或转换异常
 * - 实现缓存、事务、重试等横切关注点
 *
 * @package Kode\Aop\Contract
 * @author Kode Team <382601296@qq.com>
 */
interface ProceedingJoinPointInterface extends JoinPointInterface
{
    /**
     * 继续执行原方法
     *
     * 调用此方法将执行目标方法。可以在调用前后添加自定义逻辑，
     * 也可以修改传入的参数或返回值。
     *
     * 注意事项：
     * - 如果不调用此方法，原方法将不会被执行
     * - 可以多次调用此方法（例如实现重试逻辑）
     * - 应该返回原方法的返回值（或修改后的值）
     *
     * @param array $arguments 可选的方法参数，如果为空则使用原始参数
     * @return mixed 原方法的返回值
     *
     * @example
     * ```php
     * // 基本用法
     * $result = $joinPoint->proceed();
     * return $result;
     *
     * // 修改参数后执行
     * $args = $joinPoint->getArguments();
     * $args[0] = trim($args[0]);
     * $result = $joinPoint->proceed($args);
     * return $result;
     *
     * // 实现缓存
     * $key = md5(serialize($joinPoint->getArguments()));
     * if ($cached = $cache->get($key)) {
     *     return $cached;
     * }
     * $result = $joinPoint->proceed();
     * $cache->set($key, $result);
     * return $result;
     *
     * // 实现重试
     * $maxRetries = 3;
     * $lastException = null;
     * for ($i = 0; $i < $maxRetries; $i++) {
     *     try {
     *         return $joinPoint->proceed();
     *     } catch (\Exception $e) {
     *         $lastException = $e;
     *         if ($i < $maxRetries - 1) {
     *             sleep(1);
     *         }
     *     }
     * }
     * throw $lastException;
     * ```
     */
    public function proceed(array $arguments = []): mixed;
}
