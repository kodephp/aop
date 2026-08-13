<?php

declare(strict_types=1);

namespace Kode\Aop\Aspect;

use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Log;
use Kode\Aop\Contract\AspectInterface;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use Kode\Attributes\Attr;
use Kode\Attributes\MetaList;
use Psr\Log\LoggerInterface;

/**
 * 内置声明式日志切面
 *
 * 元切面（meta-aspect）：以 {@see Around} 拦截全部方法调用，命中目标方法上的
 * {@see Log} 注解时才织入日志，否则直接 proceed（零逻辑开销）。
 *
 * 由 {@see \Kode\Aop\Provider\AopProvider} 在装配阶段注入 PSR-3 日志器后自动注册，
 * 用户只需在业务方法上标注 `#[Log]` 即可，无需手写切入点表达式。
 *
 * @package Kode\Aop\Aspect
 * @author Kode Team <382601296@qq.com>
 */
#[Aspect]
final class LoggingAspect implements AspectInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * 环绕全部方法：命中 #[Log] 时记录入参 / 出参 / 耗时 / 异常。
     */
    #[Around("execution(* *->*(..))")]
    public function around(ProceedingJoinPoint $jp): mixed
    {
        $logs = $this->resolveLogs($jp);

        if ($logs === []) {
            return $jp->proceed();
        }

        $signature = $jp->getSignature();
        $start = microtime(true);

        try {
            $result = $jp->proceed();
            $elapsed = microtime(true) - $start;

            foreach ($logs as $log) {
                $context = ['signature' => $signature];

                if ($log->logArgs) {
                    $context['arguments'] = $jp->getArguments();
                }
                if ($log->logResult) {
                    $context['result'] = $result;
                }
                if ($log->includeTime) {
                    $context['elapsed_ms'] = round($elapsed * 1000, 2);
                }

                $this->logger->log($log->level->value, $log->message ?? "{$signature} 执行成功", $context);
            }

            return $result;
        } catch (\Throwable $e) {
            $elapsed = microtime(true) - $start;

            foreach ($logs as $log) {
                if (!$log->logException) {
                    continue;
                }

                $context = ['signature' => $signature, 'exception' => $e];

                if ($log->logArgs) {
                    $context['arguments'] = $jp->getArguments();
                }
                if ($log->includeTime) {
                    $context['elapsed_ms'] = round($elapsed * 1000, 2);
                }

                $this->logger->log(
                    $log->level->value,
                    ($log->message ?? "{$signature} 执行失败") . '：' . $e->getMessage(),
                    $context
                );
            }

            throw $e;
        }
    }

    /**
     * 解析目标方法 / 类上的 #[Log] 注解（方法级优先于类级）。
     *
     * @return array<int, Log>
     */
    private function resolveLogs(ProceedingJoinPoint $jp): array
    {
        $methodMetas = Attr::ofMethod($jp->getClassName(), $jp->getMethodName(), inherited: false)
            ->getAll(Log::class);

        if ($methodMetas->all() !== []) {
            return $this->instances($methodMetas);
        }

        $classMetas = Attr::of($jp->getClassName())->getAll(Log::class);

        return $this->instances($classMetas);
    }

    /**
     * @return array<int, Log>
     */
    private function instances(MetaList $metas): array
    {
        $out = [];

        foreach ($metas->all() as $meta) {
            $instance = $meta->getInstance();

            if ($instance instanceof Log) {
                $out[] = $instance;
            }
        }

        return $out;
    }
}
