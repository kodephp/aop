<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Attribute\Priority;

/**
 * Priority 注解测试类
 */
class PriorityTest extends TestCase
{
    /**
     * 测试 Priority 注解可以正确创建
     */
    public function testPriorityAttributeCanBeCreated(): void
    {
        $priorityValue = 100;
        $priority = new Priority($priorityValue);
        
        $this->assertInstanceOf(Priority::class, $priority);
        $this->assertEquals($priorityValue, $priority->value);
    }

    /**
     * 测试 Priority 注解的值是只读的
     */
    public function testPriorityValueIsReadOnly(): void
    {
        $priorityValue = 100;
        $priority = new Priority($priorityValue);
        
        // 验证值属性是只读的
        $this->assertEquals($priorityValue, $priority->value);
        
        // 验证不能修改只读属性
        $this->expectException(\Error::class);
        $priority->value = 200;
    }
}