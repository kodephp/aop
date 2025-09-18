<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 环绕通知注解
 * 
 * 环绕目标方法执行，可以控制是否继续执行原方法
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Around
{
    public function __construct(
        public readonly string $expression
    ) {
    }
}