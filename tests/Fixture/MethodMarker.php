<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

use Attribute;

/**
 * 方法级标记注解（测试 @annotation）
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class MethodMarker
{
}
