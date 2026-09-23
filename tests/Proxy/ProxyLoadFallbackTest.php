<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Proxy;

use PHPUnit\Framework\TestCase;

/**
 * 代理文件读不到时必须退回内存织入，而不是把整条启动/请求链路打死。
 *
 * 常驻多进程下会真实撞到：各进程的切面集合可以不同（http worker 与 queue worker 各装各的
 * 插件），彼此的代理文件同为兄弟文件却互不相干，A 进程清理旧版本时正好删掉 B 进程刚
 * rename 出来、还没 require 的那一个。require 失败抛 Error，兜不住就是 fatal。
 */
final class ProxyLoadFallbackTest extends TestCase
{
    private string $cacheDir = '';

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/kode_aop_load_' . uniqid('', true);
        mkdir($this->cacheDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
            @chmod($file, 0o644);
            @unlink($file);
        }

        @rmdir($this->cacheDir);
    }

    public function testUnreadableProxyFileFallsBackToInMemoryWeaving(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root 下 chmod 000 不拦读取，本用例失去意义');
        }

        $name = trim($this->runChild('write'));

        self::assertNotSame('missing', $name, '首次织入应当落盘一个代理文件');
        self::assertFileExists($this->cacheDir . '/' . $name);

        // 模拟「文件在，但读不了」：等价于被并发进程写坏/清理后留下的不可用中间态
        self::assertTrue(@chmod($this->cacheDir . '/' . $name, 0o000));

        $out = trim($this->runChild('load'));

        self::assertSame(
            'ok:3',
            $out,
            '代理文件读不到时应退回内存织入，而不是 fatal（修复前：Uncaught Error: Failed opening required）'
        );
    }

    private function runChild(string $mode): string
    {
        $cmd = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/../Fixture/proxy_load_child.php'),
            escapeshellarg($this->cacheDir),
            escapeshellarg($mode)
        );

        exec($cmd, $lines, $status);

        self::assertSame(0, $status, '子进程不应异常退出：' . implode("\n", $lines));

        return implode("\n", $lines);
    }
}
