<?php

declare(strict_types=1);

namespace Kode\Aop\Contract;

/**
 * 代理对象标记接口
 *
 * 所有由 AOP 内核动态生成的代理类都会实现此接口，
 * 业务代码可据此判断一个对象是否已被织入。
 *
 * ```php
 * if ($service instanceof ProxyInterface) {
 *     echo '原始类: ' . $service::__aopTargetClass();
 * }
 * ```
 *
 * @package Kode\Aop\Contract
 * @author Kode Team <382601296@qq.com>
 */
interface ProxyInterface
{
    /**
     * 获取被代理的原始类名
     *
     * @return class-string 原始类的全限定名
     */
    public static function __aopTargetClass(): string;
}
