<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Kode\Aop\Attribute\Priority;

/**
 * Priority 注解测试类
 *
 * @package Kode\Aop\Tests\Attribute
 * @author Kode Team <382601296@qq.com>
 */
class PriorityTest extends TestCase
{
    /**
     * 测试 Priority 注解可以正确创建
     */
    public function testPriorityCanBeCreated(): void
    {
        $priority = new Priority(100);

        $this->assertSame(100, $priority->value);
    }

    /**
     * 测试 Priority 注解支持负数
     */
    public function testPrioritySupportsNegativeValues(): void
    {
        $priority = new Priority(-100);

        $this->assertSame(-100, $priority->value);
    }

    /**
     * 测试 Priority 常量
     */
    public function testPriorityConstants(): void
    {
        $this->assertSame(-1000, Priority::HIGHEST);
        $this->assertSame(-100, Priority::HIGH);
        $this->assertSame(0, Priority::NORMAL);
        $this->assertSame(100, Priority::LOW);
        $this->assertSame(1000, Priority::LOWEST);
    }

    /**
     * 测试不同优先级值
     */
    public function testDifferentPriorityValues(): void
    {
        $values = [-1000, -100, 0, 100, 1000, 50, -50];

        foreach ($values as $value) {
            $priority = new Priority($value);
            $this->assertSame($value, $priority->value);
        }
    }
}
