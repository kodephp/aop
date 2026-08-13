<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Aspect;

use Kode\Aop\Aop;
use Kode\Aop\Provider\AopProvider;
use Kode\Aop\Tests\Fixture\ConcernService;
use Kode\Aop\Tests\Fixture\InMemoryLogger;
use PHPUnit\Framework\TestCase;

/**
 * 声明式 #[Log] 切面测试。
 *
 * @package Kode\Aop\Tests\Aspect
 * @author Kode Team <382601296@qq.com>
 */
final class LoggingAspectTest extends TestCase
{
    protected function setUp(): void
    {
        Aop::reset();
    }

    public function testLogsAnnotatedMethod(): void
    {
        $logger = new InMemoryLogger();
        AopProvider::create()->withLogger($logger)->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);
        $svc->logged(4);

        $this->assertCount(1, $logger->records);
        $rec = $logger->records[0];
        $this->assertSame('info', $rec['level']);
        $this->assertStringContainsString('logged', $rec['message']);
        $this->assertSame([4], $rec['context']['arguments']);
        $this->assertSame(5, $rec['context']['result']);
        $this->assertArrayHasKey('elapsed_ms', $rec['context']);
    }

    public function testSkipsUnannotatedMethod(): void
    {
        $logger = new InMemoryLogger();
        AopProvider::create()->withLogger($logger)->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);
        $svc->plain(2);

        $this->assertEmpty($logger->records);
    }

    public function testLogsExceptionAndRethrows(): void
    {
        $logger = new InMemoryLogger();
        AopProvider::create()->withLogger($logger)->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);

        try {
            $svc->boom();
            $this->fail('expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('fail', $e->getMessage());
        }

        $this->assertCount(1, $logger->records);
        $this->assertArrayHasKey('exception', $logger->records[0]['context']);
    }

    public function testNoLoggerNoRegistration(): void
    {
        // 未注入日志器时不会注册内置日志切面，且一切正常透传
        AopProvider::create()->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);
        $this->assertSame(6, $svc->logged(5));
    }
}
