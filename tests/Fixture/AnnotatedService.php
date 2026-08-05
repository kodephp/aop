<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

/**
 * 带类级注解的服务
 */
#[Marker]
class AnnotatedService
{
    public function doWork(): void
    {
    }
}
