<?php

declare(strict_types=1);

namespace Kode\Aop\Helper;

/**
 * 字符串处理工具类
 *
 * 提供字符串匹配、通配符处理等功能，主要用于切入点表达式的匹配。
 *
 * 支持的通配符：
 * - `*`：匹配任意数量的任意字符
 * - `?`：匹配单个任意字符
 *
 * @package Kode\Aop\Helper
 * @author Kode Team <382601296@qq.com>
 */
final class Str
{
    /**
     * 通配符匹配
     *
     * 将通配符模式转换为正则表达式进行匹配。
     *
     * @param string $pattern 匹配模式，支持 * 和 ? 通配符
     * @param string $subject 待匹配字符串
     * @return bool 是否匹配
     *
     * @example
     * ```php
     * // 匹配任意字符
     * Str::match('App\Service\*', 'App\Service\UserService'); // true
     * Str::match('App\Service\*', 'App\Repository\UserRepository'); // false
     *
     * // 匹配单个字符
     * Str::match('User?', 'User1'); // true
     * Str::match('User?', 'User12'); // false
     *
     * // 组合使用
     * Str::match('*Service', 'UserService'); // true
     * Str::match('get*', 'getUser'); // true
     * ```
     */
    public static function match(string $pattern, string $subject): bool
    {
        $regex = '/^' . str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        ) . '$/';

        return (bool) preg_match($regex, $subject);
    }

    /**
     * 判断字符串是否匹配表达式
     *
     * 支持精确匹配和通配符匹配。
     *
     * @param string $expression 表达式
     * @param string $subject 待匹配字符串
     * @return bool 是否匹配
     *
     * @example
     * ```php
     * // 精确匹配
     * Str::matchExpression('UserService', 'UserService'); // true
     * Str::matchExpression('UserService', 'OrderService'); // false
     *
     * // 通配符匹配
     * Str::matchExpression('*Service', 'UserService'); // true
     * Str::matchExpression('App\*', 'App\Service\UserService'); // true
     * ```
     */
    public static function matchExpression(string $expression, string $subject): bool
    {
        if ($expression === $subject) {
            return true;
        }

        if (str_contains($expression, '*') || str_contains($expression, '?')) {
            return self::match($expression, $subject);
        }

        return false;
    }

    /**
     * 检查字符串是否以指定前缀开头
     *
     * @param string $prefix 前缀
     * @param string $subject 待检查字符串
     * @return bool 是否以前缀开头
     */
    public static function startsWith(string $prefix, string $subject): bool
    {
        return str_starts_with($subject, $prefix);
    }

    /**
     * 检查字符串是否以指定后缀结尾
     *
     * @param string $suffix 后缀
     * @param string $subject 待检查字符串
     * @return bool 是否以后缀结尾
     */
    public static function endsWith(string $suffix, string $subject): bool
    {
        return str_ends_with($subject, $suffix);
    }

    /**
     * 检查字符串是否包含指定子串
     *
     * @param string $needle 子串
     * @param string $subject 待检查字符串
     * @return bool 是否包含
     */
    public static function contains(string $needle, string $subject): bool
    {
        return str_contains($subject, $needle);
    }

    /**
     * 将命名空间和类名转换为切入点格式
     *
     * @param string $className 完整类名
     * @param string $methodName 方法名
     * @return string 切入点格式字符串
     *
     * @example
     * ```php
     * Str::toPointcutFormat('App\Service\UserService', 'createUser');
     * // 返回: 'App\Service\UserService->createUser'
     * ```
     */
    public static function toPointcutFormat(string $className, string $methodName): string
    {
        return $className . '->' . $methodName;
    }

    /**
     * 解析切入点格式字符串
     *
     * @param string $pointcut 切入点格式字符串
     * @return array{class: string, method: string}|null 解析结果，格式无效时返回 null
     *
     * @example
     * ```php
     * $result = Str::parsePointcutFormat('App\Service\UserService->createUser');
     * // 返回: ['class' => 'App\Service\UserService', 'method' => 'createUser']
     * ```
     */
    public static function parsePointcutFormat(string $pointcut): ?array
    {
        $parts = explode('->', $pointcut, 2);

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'class' => $parts[0],
            'method' => $parts[1],
        ];
    }
}
