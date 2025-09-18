<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 前置通知注解
 * 
 * 在目标方法执行前执行
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Before
{
    public function __construct(
        public readonly string $expression
    ) {
    }
}