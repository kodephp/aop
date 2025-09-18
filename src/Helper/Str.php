<?php

declare(strict_types=1);

namespace Kode\Aop\Helper;

/**
 * 字符串处理工具类
 * 
 * 提供字符串匹配、通配符处理等功能
 */
class Str
{
    /**
     * 通配符匹配
     *
     * @param string $pattern 匹配模式，支持 * 和 ? 通配符
     * @param string $string 待匹配字符串
     * @return bool 是否匹配
     */
    public static function match(string $pattern, string $string): bool
    {
        // 将通配符模式转换为正则表达式
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace(['\*', '\?'], ['.*', '.'], $pattern);
        
        return (bool) preg_match('/^' . $pattern . '$/', $string);
    }

    /**
     * 判断字符串是否匹配表达式
     *
     * @param string $expression 表达式
     * @param string $string 待匹配字符串
     * @return bool 是否匹配
     */
    public static function matchExpression(string $expression, string $string): bool
    {
        // 简单的表达式匹配实现
        if ($expression === $string) {
            return true;
        }

        // 处理通配符
        if (str_contains($expression, '*')) {
            return self::match($expression, $string);
        }

        return false;
    }
}