<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Attribute\After;

/**
 * After 注解测试类
 *
 * @package Kode\Aop\Tests\Attribute
 * @author Kode Team <382601296@qq.com>
 */
class AfterTest extends TestCase
{
    /**
     * 测试 After 注解可以正确创建
     */
    public function testAfterCanBeCreated(): void
    {
        $pointcut = 'execution(* App\Service\UserService->createUser(..))';
        $after = new After($pointcut);

        $this->assertSame($pointcut, $after->pointcut);
    }

    /**
     * 测试 After 注解的切入点表达式
     */
    public function testAfterPointcutExpression(): void
    {
        $expressions = [
            'execution(* App\Service\*->*(..))',
            'execution(public App\Service\UserService->createUser())',
            'within(App\Controller\*)',
        ];

        foreach ($expressions as $expression) {
            $after = new After($expression);
            $this->assertSame($expression, $after->pointcut);
        }
    }
}
