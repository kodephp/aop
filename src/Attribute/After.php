<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 后置通知注解
 * 
 * 在目标方法执行后执行（无论是否抛出异常）
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class After
{
    public function __construct(
        public readonly string $expression
    ) {
    }
}