<?php

declare(strict_types=1);

// 简单的入口文件，用于演示 AOP 功能
// 手动加载必要的类文件

// 加载核心类文件
require_once __DIR__ . '/src/Attribute/Aspect.php';
require_once __DIR__ . '/src/Attribute/Before.php';
require_once __DIR__ . '/src/Attribute/After.php';
require_once __DIR__ . '/src/Contract/AspectInterface.php';
require_once __DIR__ . '/src/Contract/JoinPointInterface.php';
require_once __DIR__ . '/src/Contract/ProceedingJoinPointInterface.php';
require_once __DIR__ . '/src/Contract/AspectKernelInterface.php';
require_once __DIR__ . '/src/Runtime/JoinPoint.php';
require_once __DIR__ . '/src/Runtime/ProceedingJoinPoint.php';
require_once __DIR__ . '/src/Runtime/AspectKernel.php';
require_once __DIR__ . '/src/Reflection/Reflector.php';
require_once __DIR__ . '/src/Reflection/MetadataReader.php';
require_once __DIR__ . '/src/Exception/AopException.php';

use Kode\Aop\Runtime\AspectKernel;
use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\After;
use Kode\Aop\Runtime\JoinPoint;

// 示例服务类
class UserService
{
    public function createUser(array $data): array
    {
        echo "Creating user with data: " . json_encode($data) . "\n";
        return ['id' => 1, 'name' => $data['name'], 'email' => $data['email']];
    }

    public function updateUser(int $id, array $data): array
    {
        echo "Updating user {$id} with data: " . json_encode($data) . "\n";
        return ['id' => $id, 'name' => $data['name'], 'email' => $data['email']];
    }

    public function deleteUser(int $id): bool
    {
        echo "Deleting user {$id}\n";
        return true;
    }
}

// 示例切面类
#[Aspect]
class LoggingAspect implements \Kode\Aop\Contract\AspectInterface
{
    #[Before("execution(* UserService->createUser(..))")]
    public function logBeforeCreateUser(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "[LOG] Before creating user with data: " . json_encode($args[0]) . "\n";
    }

    #[After("execution(* UserService->createUser(..))")]
    public function logAfterCreateUser(JoinPoint $joinPoint): void
    {
        echo "[LOG] After creating user\n";
    }
}

// 创建 AOP 内核
$kernel = new AspectKernel();

// 注册切面
$kernel->registerAspect(new LoggingAspect());

// 获取代理对象
$userService = $kernel->getProxy(UserService::class);

// 调用方法，会自动应用切面逻辑
echo "=== AOP Demo ===\n";
$user = $userService->createUser(['name' => 'John Doe', 'email' => 'john@example.com']);
echo "Created user: " . json_encode($user) . "\n\n";

$userService->updateUser(1, ['name' => 'Jane Doe', 'email' => 'jane@example.com']);
$userService->deleteUser(1);