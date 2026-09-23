<?php

declare(strict_types=1);

/*
 * 代理载入子进程脚本（供 ProxyLoadFallbackTest 以 `php <本文件> <cacheDir> <mode>` 调用）。
 *
 * mode=write：正常织入一次，把生成的代理文件名打到标准输出后退出，供父进程改造该文件。
 * mode=load ：以完全相同的切面集合再织入一次，此时磁盘上已存在那个代理文件；
 *             父进程已把它改成读不了的权限，require 必然失败。
 *             修复前：require_once 直接 fatal（Uncaught Error: Failed opening required）；
 *             修复后：退回内存织入，输出 ok。
 */

require __DIR__ . '/../../vendor/autoload.php';

use Kode\Aop\Aop;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use Kode\Aop\Tests\Fixture\SampleService;

[$script, $cacheDir, $mode] = $argv;

#[Aspect]
final class ProxyLoadChildAspect
{
    #[After('execution(* Kode\Aop\Tests\Fixture\SampleService->create(..))')]
    public function mark(ProceedingJoinPoint $jp): void
    {
    }
}

Aop::reset();
Aop::boot([ProxyLoadChildAspect::class], $cacheDir);

/** @var SampleService $service */
$service = Aop::proxy(SampleService::class);

if ($mode === 'write') {
    $files = glob($cacheDir . '/*.php') ?: [];
    echo $files === [] ? 'missing' : basename($files[0]);

    exit(0);
}

echo 'ok:' . $service->create('abc') . PHP_EOL;
