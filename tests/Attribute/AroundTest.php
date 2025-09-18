<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Attribute\Around;

/**
 * Around 注解测试类
 */
class AroundTest extends TestCase
{
    /**
     * 测试 Around 注解可以正确创建
     */
    public function testAroundAttributeCanBeCreated(): void
    {
        $expression = "execution(* App\Service\*->save(..))";
        $around = new Around($expression);
        
        $this->assertInstanceOf(Around::class, $around);
        $this->assertEquals($expression, $around->expression);
    }

    /**
     * 测试 Around 注解的表达式是只读的
     */
    public function testAroundExpressionIsReadOnly(): void
    {
        $expression = "execution(* App\Service\*->save(..))";
        $around = new Around($expression);
        
        // 验证表达式属性是只读的
        $this->assertEquals($expression, $around->expression);
        
        // 验证不能修改只读属性
        $this->expectException(\Error::class);
        $around->expression = "new expression";
    }
}