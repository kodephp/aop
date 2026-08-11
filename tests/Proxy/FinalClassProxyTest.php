<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Proxy;

use Kode\Aop\Aop;
use Kode\Aop\Attribute\AfterReturning;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Contract\JoinPointInterface;
use Kode\Aop\Contract\ProxyInterface;
use Kode\Aop\Tests\Fixture\FinalGreeter;
use Kode\Aop\Tests\Fixture\FinalPlain;
use Kode\Aop\Tests\Fixture\GreeterInterface;
use PHPUnit\Framework\TestCase;

/**
 * final 类代理测试
 *
 * 验证 v3.2 引入的组合式代理：final 类（无论是否实现接口）都能被织入，
 * 且代理对象实现目标接口（而非继承目标类）。
 *
 * @package Kode\Aop\Tests\Proxy
 * @author Kode Team <382601296@qq.com>
 */
#[Aspect]
class FinalGreeterAspect
{
    /** @var array<int, string> */
    public array $log = [];

    #[Before('execution(* *FinalGreeter->greet(..))')]
    public function before(): void
    {
        $this->log[] = 'before';
    }

    #[AfterReturning('execution(* *FinalGreeter->greet(..))')]
    public function upper(JoinPointInterface $joinPoint): mixed
    {
        return strtoupper((string) $joinPoint->getResult());
    }

    #[AfterReturning('execution(* *FinalGreeter->fixed(..))')]
    public function fixedBang(JoinPointInterface $joinPoint): mixed
    {
        return $joinPoint->getResult() . '!';
    }
}

#[Aspect]
class FinalPlainAspect
{
    /** @var array<int, string> */
    public array $log = [];

    #[Before('execution(* *FinalPlain->shout(..))')]
    public function before(): void
    {
        $this->log[] = 'before';
    }

    #[AfterReturning('execution(* *FinalPlain->shout(..))')]
    public function bang(JoinPointInterface $joinPoint): mixed
    {
        return $joinPoint->getResult() . '!';
    }
}

class FinalClassProxyTest extends TestCase
{
    protected function setUp(): void
    {
        Aop::reset();
    }

    public function testFinalClassImplementsInterfaceButNotExtends(): void
    {
        $aspect = new FinalGreeterAspect();
        Aop::boot([$aspect]);

        $proxy = Aop::proxy(FinalGreeter::class);

        // 组合式代理：实现接口，但不继承 final 类本身
        $this->assertInstanceOf(GreeterInterface::class, $proxy);
        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertFalse($proxy instanceof FinalGreeter);
    }

    public function testFinalClassAdviceAndConstructor(): void
    {
        $aspect = new FinalGreeterAspect();
        Aop::boot([$aspect]);

        /** @var GreeterInterface $proxy */
        $proxy = Aop::proxy(FinalGreeter::class, ['name' => 'bob']);

        // 构造函数参数正确透传到被包装实例
        $this->assertSame('bob', $proxy->whoami());

        // Before + AfterReturning 均生效，返回值被改写
        $this->assertSame('HELLO, BOB', $proxy->greet('bob'));
        $this->assertSame(['before'], $aspect->log);
    }

    public function testFinalMethodIsWeavable(): void
    {
        $aspect = new FinalGreeterAspect();
        Aop::boot([$aspect]);

        /** @var GreeterInterface $proxy */
        $proxy = Aop::proxy(FinalGreeter::class);

        // final 方法也能被织入（组合式代理调用被包装实例的 final 方法）
        $this->assertSame('fixed-original!', $proxy->fixed());
    }

    public function testFinalPlainClassViaMagicCall(): void
    {
        $aspect = new FinalPlainAspect();
        Aop::boot([$aspect]);

        $proxy = Aop::proxy(FinalPlain::class);

        // 组合式代理不继承 final 类本身（$proxy 保持 object 类型，便于动态调用）
        $this->assertFalse($proxy instanceof FinalPlain);

        // 通过闭包以「非字面量方法名」调用，绕过静态分析（运行时代理已声明该方法 / 走 __call）
        $result = (static function (object $proxy, string $method): string {
            return $proxy->$method('hello');
        })($proxy, 'shout');

        $this->assertSame('HELLO!', $result);
        $this->assertSame(['before'], $aspect->log);
    }

    public function testWrapFinalInstance(): void
    {
        $aspect = new FinalGreeterAspect();
        Aop::boot([$aspect]);

        $real = new FinalGreeter('alice');

        /** @var GreeterInterface $proxy */
        $proxy = Aop::wrap($real);

        $this->assertInstanceOf(GreeterInterface::class, $proxy);
        $this->assertSame('alice', $proxy->whoami());
        $this->assertSame('HELLO, ALICE', $proxy->greet('alice'));
    }
}
