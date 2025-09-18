<?php

declare(strict_types=1);

namespace Example;

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\After;
use Kode\Aop\Runtime\JoinPoint;

/**
 * 日志切面类
 * 
 * 用于演示 AOP 的日志功能
 */
#[Aspect]
class LoggingAspect
{
    /**
     * 在方法执行前记录日志
     */
    #[Before("execution(* Example\UserService->createUser(..))")]
    public function logBeforeCreateUser(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "[LOG] Before creating user with data: " . json_encode($args[0]) . "\n";
    }

    /**
     * 在方法执行后记录日志
     */
    #[After("execution(* Example\UserService->createUser(..))")]
    public function logAfterCreateUser(JoinPoint $joinPoint): void
    {
        echo "[LOG] After creating user\n";
    }

    /**
     * 在更新用户方法执行前记录日志
     */
    #[Before("execution(* Example\UserService->updateUser(..))")]
    public function logBeforeUpdateUser(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "[LOG] Before updating user {$args[0]} with data: " . json_encode($args[1]) . "\n";
    }

    /**
     * 在删除用户方法执行前记录日志
     */
    #[Before("execution(* Example\UserService->deleteUser(..))")]
    public function logBeforeDeleteUser(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "[LOG] Before deleting user {$args[0]}\n";
    }
}