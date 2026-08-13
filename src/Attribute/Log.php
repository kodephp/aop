<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 声明式日志切面注解
 *
 * 直接标注在业务方法（或业务类）上，由库内置的 {@see \Kode\Aop\Aspect\LoggingAspect}
 * 自动织入日志逻辑，无需手写切入点表达式。
 *
 * 标注在类上时，对类中所有公共方法生效；标注在方法上时仅对该方法生效，
 * 方法级注解优先级高于类级注解。
 *
 * 使用示例：
 * ```php
 * use Kode\Aop\Attribute\Log;
 * use Kode\Aop\Attribute\LogLevel;
 *
 * class UserService
 * {
 *     // 记录入参、出参与耗时
 *     #[Log(level: LogLevel::Info, logArgs: true, logResult: true)]
 *     public function getUser(int $id): array
 *     {
 *         return ['id' => $id];
 *     }
 * }
 * ```
 *
 * @package Kode\Aop\Attribute
 * @author Kode Team <382601296@qq.com>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Log
{
    /**
     * @param LogLevel $level 日志级别，默认 Info
     * @param string|null $message 自定义日志文案，null 时使用「类名::方法名」签名
     * @param bool $logArgs 是否记录方法入参
     * @param bool $logResult 是否记录方法返回值
     * @param bool $logException 是否记录异常（异常仍会向上抛出）
     * @param bool $includeTime 是否记录方法耗时
     */
    public function __construct(
        public LogLevel $level = LogLevel::Info,
        public ?string $message = null,
        public bool $logArgs = true,
        public bool $logResult = true,
        public bool $logException = true,
        public bool $includeTime = true
    ) {
    }
}
