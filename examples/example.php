<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Example\UserService;
use Example\LoggingAspect;
use Kode\Aop\Runtime\AspectKernel;

// 创建 AOP 内核
$kernel = new AspectKernel();

// 注册切面
$kernel->registerAspect(new LoggingAspect());

// 获取代理对象
$userService = $kernel->getProxy(UserService::class);

echo "=== AOP 示例 ===\n\n";

// 调用创建用户方法
echo "1. 调用 createUser 方法:\n";
$userData = ['name' => 'John Doe', 'email' => 'john@example.com'];
$user = $userService->createUser($userData);
echo "创建的用户: " . json_encode($user) . "\n\n";

// 调用更新用户方法
echo "2. 调用 updateUser 方法:\n";
$updatedUser = $userService->updateUser(1, ['name' => 'Jane Doe', 'email' => 'jane@example.com']);
echo "更新的用户: " . json_encode($updatedUser) . "\n\n";

// 调用删除用户方法
echo "3. 调用 deleteUser 方法:\n";
$result = $userService->deleteUser(1);
echo "删除结果: " . ($result ? '成功' : '失败') . "\n\n";

echo "=== 示例结束 ===\n";