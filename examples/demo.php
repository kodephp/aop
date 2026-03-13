<?php

declare(strict_types=1);

/**
 * Kode/AOP 使用示例
 *
 * 本示例演示如何使用 kode/aop 包实现面向切面编程。
 *
 * @author Kode Team <382601296@qq.com>
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Attribute\Priority;
use Kode\Aop\Runtime\AspectKernel;
use Kode\Aop\Runtime\JoinPoint;
use Kode\Aop\Runtime\ProceedingJoinPoint;

// ============================================
// 定义服务类
// ============================================

/**
 * 用户服务类
 */
class UserService
{
    /**
     * 获取用户信息
     */
    public function getUser(int $id): string
    {
        echo "  [UserService] 执行 getUser 方法，ID: {$id}\n";
        return "User-{$id}";
    }

    /**
     * 创建用户
     */
    public function createUser(string $name, string $email): array
    {
        echo "  [UserService] 执行 createUser 方法\n";
        return [
            'id' => rand(1, 1000),
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * 删除用户
     */
    public function deleteUser(int $id): bool
    {
        echo "  [UserService] 执行 deleteUser 方法，ID: {$id}\n";
        return true;
    }
}

// ============================================
// 定义切面类
// ============================================

/**
 * 日志切面
 *
 * 记录方法调用日志
 */
#[Aspect]
class LoggingAspect
{
    /**
     * 前置通知：方法执行前记录日志
     */
    #[Before("execution(* UserService->getUser(..))")]
    #[Priority(Priority::HIGH)]
    public function logBeforeGetUser(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "  [Before] 准备获取用户，ID: {$args[0]}\n";
    }

    /**
     * 后置通知：方法执行后记录日志
     */
    #[After("execution(* UserService->getUser(..))")]
    public function logAfterGetUser(JoinPoint $joinPoint): void
    {
        echo "  [After] 用户获取完成\n";
    }

    /**
     * 前置通知：创建用户前验证
     */
    #[Before("execution(* UserService->createUser(..))")]
    public function validateBeforeCreate(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "  [Before] 验证用户数据: name={$args[0]}, email={$args[1]}\n";
    }

    /**
     * 后置通知：创建用户后记录
     */
    #[After("execution(* UserService->createUser(..))")]
    public function logAfterCreate(JoinPoint $joinPoint): void
    {
        $result = $joinPoint->getResult();
        echo "  [After] 用户创建完成，ID: {$result['id']}\n";
    }
}

/**
 * 性能监控切面
 *
 * 使用环绕通知实现方法执行时间统计
 */
#[Aspect]
class PerformanceAspect
{
    /**
     * 环绕通知：统计方法执行时间
     */
    #[Around("execution(* UserService->deleteUser(..))")]
    public function measureTime(ProceedingJoinPoint $joinPoint): mixed
    {
        $methodName = $joinPoint->getMethodName();
        echo "  [Around] 开始执行 {$methodName}\n";

        $startTime = microtime(true);
        $result = $joinPoint->proceed();
        $endTime = microtime(true);

        $duration = round(($endTime - $startTime) * 1000, 2);
        echo "  [Around] {$methodName} 执行完成，耗时: {$duration}ms\n";

        return $result;
    }
}

// ============================================
// 初始化 AOP 内核
// ============================================

echo "=== Kode/AOP 使用示例 ===\n\n";

$kernel = AspectKernel::getInstance();

// 注册切面
$kernel->registerAspect(new LoggingAspect());
$kernel->registerAspect(new PerformanceAspect());

// 初始化内核
$kernel->init();

// 获取代理对象
$userService = $kernel->getProxy(UserService::class);

// ============================================
// 测试方法调用
// ============================================

echo "1. 测试前置和后置通知 (getUser):\n";
$result = $userService->getUser(123);
echo "  结果: {$result}\n\n";

echo "2. 测试多通知 (createUser):\n";
$result = $userService->createUser('John Doe', 'john@example.com');
echo "  结果: " . json_encode($result) . "\n\n";

echo "3. 测试环绕通知 (deleteUser):\n";
$result = $userService->deleteUser(456);
echo "  结果: " . ($result ? '成功' : '失败') . "\n\n";

echo "=== 示例结束 ===\n";
