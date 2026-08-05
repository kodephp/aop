<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use Kode\Aop\Attribute\AfterReturning;
use PHPUnit\Framework\TestCase;

/**
 * AfterReturning 注解测试类
 *
 * @package Kode\Aop\Tests\Attribute
 * @author Kode Team <382601296@qq.com>
 */
class AfterReturningTest extends TestCase
{
    public function testCanBeCreated(): void
    {
        $pointcut = 'execution(* App\Service\UserService->getUser(..))';
        $attribute = new AfterReturning($pointcut);

        $this->assertSame($pointcut, $attribute->pointcut);
    }

    public function testAcceptsVariousExpressions(): void
    {
        $expressions = [
            'execution(* App\Service\*->*(..))',
            'execution(public App\Service\UserService->getUser())',
            'within(App\Controller\*)',
        ];

        foreach ($expressions as $expression) {
            $attribute = new AfterReturning($expression);
            $this->assertSame($expression, $attribute->pointcut);
        }
    }
}
