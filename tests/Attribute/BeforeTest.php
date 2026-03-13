<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Attribute\Before;

/**
 * Before 注解测试类
 *
 * @package Kode\Aop\Tests\Attribute
 * @author Kode Team <382601296@qq.com>
 */
class BeforeTest extends TestCase
{
    /**
     * 测试 Before 注解可以正确创建
     */
    public function testBeforeCanBeCreated(): void
    {
        $pointcut = 'execution(* App\Service\UserService->createUser(..))';
        $before = new Before($pointcut);

        $this->assertSame($pointcut, $before->pointcut);
    }

    /**
     * 测试 Before 注解的切入点表达式
     */
    public function testBeforePointcutExpression(): void
    {
        $expressions = [
            'execution(* App\Service\*->*(..))',
            'execution(public App\Service\UserService->createUser())',
            'within(App\Controller\*)',
        ];

        foreach ($expressions as $expression) {
            $before = new Before($expression);
            $this->assertSame($expression, $before->pointcut);
        }
    }
}
