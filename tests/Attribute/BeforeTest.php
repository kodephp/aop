<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Attribute\Before;

/**
 * Before 注解测试类
 */
class BeforeTest extends TestCase
{
    /**
     * 测试 Before 注解可以正确创建
     */
    public function testBeforeAttributeCanBeCreated(): void
    {
        $expression = "execution(* App\Service\*->save(..))";
        $before = new Before($expression);
        
        $this->assertInstanceOf(Before::class, $before);
        $this->assertEquals($expression, $before->expression);
    }

    /**
     * 测试 Before 注解的表达式是只读的
     */
    public function testBeforeExpressionIsReadOnly(): void
    {
        $expression = "execution(* App\Service\*->save(..))";
        $before = new Before($expression);
        
        // 验证表达式属性是只读的
        $this->assertEquals($expression, $before->expression);
        
        // 验证不能修改只读属性
        $this->expectException(\Error::class);
        $before->expression = "new expression";
    }
}