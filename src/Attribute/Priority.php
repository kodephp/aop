<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 优先级注解
 * 
 * 控制切面执行顺序，数字越小优先级越高
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Priority
{
    public function __construct(
        public readonly int $value
    ) {
    }
}