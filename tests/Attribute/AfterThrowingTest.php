<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use Kode\Aop\Attribute\AfterThrowing;
use PHPUnit\Framework\TestCase;

/**
 * AfterThrowing 注解测试类
 *
 * @package Kode\Aop\Tests\Attribute
 * @author Kode Team <382601296@qq.com>
 */
class AfterThrowingTest extends TestCase
{
    public function testCanBeCreatedWithDefaultThrowable(): void
    {
        $pointcut = 'execution(* App\Service\*->*(..))';
        $attribute = new AfterThrowing($pointcut);

        $this->assertSame($pointcut, $attribute->pointcut);
        $this->assertSame(\Throwable::class, $attribute->throwable);
    }

    public function testCanRestrictThrowable(): void
    {
        $pointcut = 'execution(* App\Service\*->*(..))';
        $attribute = new AfterThrowing($pointcut, \RuntimeException::class);

        $this->assertSame($pointcut, $attribute->pointcut);
        $this->assertSame(\RuntimeException::class, $attribute->throwable);
    }
}
