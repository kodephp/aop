<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Fixture;

/**
 * 用于验证「final 类 + 接口」组合式代理的接口契约
 */
interface GreeterInterface
{
    public function greet(string $name): string;

    public function farewell(string $name): void;

    /**
     * 故意声明为 final 方法，验证组合式代理也能织入 final 方法
     */
    public function fixed(): string;

    /**
     * 读取构造参数，用于验证组合式代理的构造函数参数正确透传
     */
    public function whoami(): string;
}
