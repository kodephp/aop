<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Aspect;

use Kode\Aop\Aop;
use Kode\Aop\Provider\AopProvider;
use Kode\Aop\Tests\Fixture\ConcernService;
use Kode\Aop\Tests\Fixture\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

/**
 * 声明式 #[Transactional] 切面测试。
 *
 * @package Kode\Aop\Tests\Aspect
 * @author Kode Team <382601296@qq.com>
 */
final class TransactionalAspectTest extends TestCase
{
    protected function setUp(): void
    {
        Aop::reset();
    }

    public function testCommitsOnSuccess(): void
    {
        $tm = new FakeTransactionManager();
        AopProvider::create()->withTransactionManager($tm)->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);
        $this->assertSame(7, $svc->transact(7));

        $this->assertSame(1, $tm->begins);
        $this->assertSame(1, $tm->commits);
        $this->assertSame(0, $tm->rollbacks);
    }

    public function testRollsBackOnFailure(): void
    {
        $tm = new FakeTransactionManager();
        AopProvider::create()->withTransactionManager($tm)->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);

        try {
            $svc->failTx();
            $this->fail('expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('tx-fail', $e->getMessage());
        }

        $this->assertSame(1, $tm->begins);
        $this->assertSame(0, $tm->commits);
        $this->assertSame(1, $tm->rollbacks);
    }

    public function testNoManagerNoRegistration(): void
    {
        AopProvider::create()->boot();

        /** @var ConcernService $svc */
        $svc = Aop::proxy(ConcernService::class);
        $this->assertSame(9, $svc->transact(9));
    }
}
