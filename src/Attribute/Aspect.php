<?php

declare(strict_types=1);

namespace Kode\Aop\Attribute;

use Attribute;

/**
 * 切面注解
 * 
 * 标记一个类为切面类
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Aspect
{
    public function __construct()
    {
    }
}