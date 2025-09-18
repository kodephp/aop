<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 切入点注解
 * 
 * 定义可复用的切入点表达式
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Pointcut
{
    public function __construct(
        public readonly string $expression
    ) {
    }
}