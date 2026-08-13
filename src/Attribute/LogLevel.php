<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

/**
 * 日志级别
 *
 * 对齐 PSR-3（{@see \Psr\Log\LogLevel}）的八个标准级别，
 * 便于直接透传给任意 PSR-3 日志器。
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';
}
