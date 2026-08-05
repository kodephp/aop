<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

/**
 * 带方法级注解的服务
 */
class AnnotatedMethodService
{
    #[MethodMarker]
    public function tagged(): void
    {
    }

    public function plain(): void
    {
    }
}
