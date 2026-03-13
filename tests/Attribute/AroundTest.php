<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Attribute\Around;

/**
 * Around 注解测试类
 *
 * @package Kode\Aop\Tests\Attribute
 * @author Kode Team <382601296@qq.com>
 */
class AroundTest extends TestCase
{
    /**
     * 测试 Around 注解可以正确创建
     */
    public function testAroundCanBeCreated(): void
    {
        $pointcut = 'execution(* App\Service\UserService->createUser(..))';
        $around = new Around($pointcut);

        $this->assertSame($pointcut, $around->pointcut);
    }

    /**
     * 测试 Around 注解的切入点表达式
     */
    public function testAroundPointcutExpression(): void
    {
        $expressions = [
            'execution(* App\Service\*->*(..))',
            'execution(public App\Service\UserService->createUser())',
            'within(App\Controller\*)',
        ];

        foreach ($expressions as $expression) {
            $around = new Around($expression);
            $this->assertSame($expression, $around->pointcut);
        }
    }
}
