<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Runtime\AspectKernel;
use Kode\Aop\Runtime\JoinPoint;
use Kode\Aop\Runtime\ProceedingJoinPoint;

// 定义一个简单的服务类
class UserService
{
    public function getUser(int $id): string
    {
        echo "执行UserService::getUser方法，参数: $id\n";
        return "User $id";
    }
    
    public function createUser(string $name): string
    {
        echo "执行UserService::createUser方法，参数: $name\n";
        return "Created user: $name";
    }
}

// 定义一个切面类
#[Aspect]
class LoggingAspect
{
    #[Before("execution(* UserService->getUser(..))")]
    public function logBefore(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "前置通知: 调用方法 " . $joinPoint->getMethod()->getName() . " 参数: " . json_encode($args) . "\n";
    }
    
    #[After("execution(* UserService->getUser(..))")]
    public function logAfter(JoinPoint $joinPoint): void
    {
        echo "后置通知: 方法 " . $joinPoint->getMethod()->getName() . " 执行完成\n";
    }
    
    #[Around("execution(* UserService->createUser(..))")]
    public function logAround(ProceedingJoinPoint $proceedingJoinPoint): mixed
    {
        $args = $proceedingJoinPoint->getArguments();
        echo "环绕通知开始: 调用方法 " . $proceedingJoinPoint->getMethod()->getName() . " 参数: " . json_encode($args) . "\n";
        
        // 执行原方法
        $result = $proceedingJoinPoint->proceed();
        
        echo "环绕通知结束: 方法执行结果: $result\n";
        return $result;
    }
}

// 初始化AOP内核
$aspectKernel = AspectKernel::getInstance();
$aspectKernel->registerAspect(new LoggingAspect());

// 获取代理对象
$userService = $aspectKernel->getProxy(UserService::class);

// 测试前置和后置通知
echo "=== 测试前置和后置通知 ===\n";
$result = $userService->getUser(123);
echo "方法返回值: $result\n\n";

// 测试环绕通知
echo "=== 测试环绕通知 ===\n";
$result = $userService->createUser("John");
echo "方法返回值: $result\n";