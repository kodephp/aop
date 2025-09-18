<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Attribute\After;

/**
 * After 注解测试类
 */
class AfterTest extends TestCase
{
    /**
     * 测试 After 注解可以正确创建
     */
    public function testAfterAttributeCanBeCreated(): void
    {
        $expression = "execution(* App\Service\*->save(..))";
        $after = new After($expression);
        
        $this->assertInstanceOf(After::class, $after);
        $this->assertEquals($expression, $after->expression);
    }

    /**
     * 测试 After 注解的表达式是只读的
     */
    public function testAfterExpressionIsReadOnly(): void
    {
        $expression = "execution(* App\Service\*->save(..))";
        $after = new After($expression);
        
        // 验证表达式属性是只读的
        $this->assertEquals($expression, $after->expression);
        
        // 验证不能修改只读属性
        $this->expectException(\Error::class);
        $after->expression = "new expression";
    }
}