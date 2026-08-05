<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

use Attribute;

/**
 * 类级标记注解（测试 @within / @target）
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Marker
{
}
