<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Runtime;

use Kode\Aop\Aop;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Runtime\AspectKernel;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use Kode\Aop\Tests\Fixture\SampleService;
use PHPUnit\Framework\TestCase;

/**
 * 同名切面经类名重复登记，只认第一次。
 *
 * 框架侧 AopServiceProvider 把「config 显式登记的类名」与「目录扫描出的类名」拼成一份列表，
 * 同一切面既写在配置里又躺在扫描目录中时就会出现两次；两个类名各自 new 一个实例，
 * 编译出两份逐条相同的通知，被代理方法每次调用都把同一段切面逻辑跑两遍。
 */
#[Aspect]
class DuplicateNameAspect
{
    /** @var int 通知实际执行次数，跨实例累加 */
    public static int $calls = 0;

    #[After('execution(* Kode\Aop\Tests\Fixture\SampleService->create(..))')]
    public function mark(ProceedingJoinPoint $jp): void
    {
        ++self::$calls;
    }
}

final class AspectKernelRegistrationTest extends TestCase
{
    private AspectKernel $kernel;

    protected function setUp(): void
    {
        Aop::reset();
        DuplicateNameAspect::$calls = 0;
        $this->kernel = Aop::kernel();
    }

    protected function tearDown(): void
    {
        Aop::reset();
    }

    public function testSameClassNameListedTwiceRegistersOnce(): void
    {
        $this->kernel->registerAspects([DuplicateNameAspect::class, DuplicateNameAspect::class]);

        self::assertCount(1, $this->kernel->getRegistry()->aspects());
        self::assertCount(1, $this->kernel->getRegistry()->advices());
    }

    public function testSameClassNameListedTwiceRunsAdviceOnce(): void
    {
        /** @var array<int, class-string|object> $listed 前导反斜杠写法来自用户手写的配置文件 */
        $listed = [
            DuplicateNameAspect::class,
            '\\' . DuplicateNameAspect::class,
            DuplicateNameAspect::class,
        ];

        $this->kernel->registerAspects($listed);

        /** @var SampleService $service */
        $service = Aop::proxy(SampleService::class);
        $service->create('abc');

        self::assertSame(1, DuplicateNameAspect::$calls, '同名切面重复登记不得让通知执行两遍');
    }

    public function testEarlierRegistrationWinsOverLaterClassName(): void
    {
        $explicit = new DuplicateNameAspect();
        $this->kernel->registerAspect($explicit);

        // 目录扫描随后又交出同一个类名：生效的必须是先登记的那一份
        $this->kernel->registerAspects([DuplicateNameAspect::class]);

        self::assertSame([$explicit], $this->kernel->getRegistry()->aspects());
    }

    public function testClassNameAcrossSeparateCallsStillRegistersOnce(): void
    {
        $this->kernel->registerAspects([DuplicateNameAspect::class]);
        $this->kernel->registerAspects([DuplicateNameAspect::class]);

        self::assertCount(1, $this->kernel->getRegistry()->advices(), '插件重复 boot 不得叠加第二份通知');
    }

    public function testUnknownClassNameStillThrows(): void
    {
        $this->expectException(\Kode\Aop\Exception\AopException::class);

        // 传一个不存在的类名正是这里要验证的输入，静态类型上它当然不是 class-string
        $this->kernel->registerAspects(['Kode\\Aop\\Tests\\Fixture\\NoSuchAspectClass']);
    }
}
