# Kode/AOP - PHP 8.1+ 轻量级AOP框架

基于 PHP 8.1+ 原生属性（Attribute）实现的轻量级、高性能、高扩展性 AOP（面向切面编程）组件。

## 特性

- **原生支持**：基于 PHP 8.1+ 原生属性（Attribute）实现
- **轻量级**：无框架依赖，可集成到任何现代 PHP 项目中
- **高性能**：使用缓存机制避免重复反射操作
- **类型安全**：充分利用 PHP 8.1+ 的类型系统
- **IDE友好**：支持现代 IDE 的自动提示与跳转
- **扩展性强**：支持前置通知（Before）、后置通知（After）、环绕通知（Around）

## 安装

```bash
composer require kode/aop
```

## 快速开始

### 1. 创建切面类

```php
<?php

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Runtime\JoinPoint;

#[Aspect]
class LoggingAspect
{
    #[Before("execution(* App\Service\UserService->createUser(..))")]
    public function logBefore(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "Creating user with data: " . json_encode($args[0]) . "\n";
    }
}
```

### 2. 配置AOP内核

```php
<?php

use Kode\Aop\Runtime\AspectKernel;

// 创建AOP内核
$kernel = new AspectKernel();

// 注册切面
$kernel->registerAspect(new LoggingAspect());

// 获取代理对象
$userService = $kernel->getProxy(UserService::class);
```

### 3. 使用代理对象

```php
<?php

// 使用代理对象调用方法
$result = $userService->createUser([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

## 高级使用

### 使用After通知

```php
<?php

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\After;
use Kode\Aop\Runtime\JoinPoint;

#[Aspect]
class LoggingAspect
{
    #[After("execution(* App\Service\UserService->createUser(..))")]
    public function logAfter(JoinPoint $joinPoint): void
    {
        echo "User created successfully\n";
    }
}
```

### 使用Around通知

```php
<?php

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Runtime\ProceedingJoinPoint;

#[Aspect]
class TransactionAspect
{
    #[Around("execution(* App\Service\UserService->*(..))")]
    public function transactional(ProceedingJoinPoint $joinPoint)
    {
        // 开始事务
        echo "Starting transaction\n";
        
        try {
            // 执行原方法
            $result = $joinPoint->proceed();
            
            // 提交事务
            echo "Committing transaction\n";
            
            return $result;
        } catch (Exception $e) {
            // 回滚事务
            echo "Rolling back transaction\n";
            throw $e;
        }
    }
}
```

### 使用Priority设置执行顺序

```php
<?php

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\Priority;

#[Aspect]
class PriorityAspect
{
    #[Before("execution(* App\Service\UserService->*(..))")]
    #[Priority(10)]
    public function first(JoinPoint $joinPoint): void
    {
        echo "First aspect executed\n";
    }
    
    #[Before("execution(* App\Service\UserService->*(..))")]
    #[Priority(20)]
    public function second(JoinPoint $joinPoint): void
    {
        echo "Second aspect executed\n";
    }
}
```

## 核心组件

```
src/
├── Attribute/               # 原生注解定义
│   ├── Aspect.php           # 切面标记
│   ├── Before.php           # 方法前执行
│   ├── After.php            # 方法后执行（无论异常）
│   ├── Around.php           # 环绕执行（可控制流程）
│   ├── Pointcut.php         # 切入点表达式（支持类名、方法名通配）
│   └── Priority.php         # 执行优先级（数字越小越先执行）
│
├── Contract/                # 接口契约
│   ├── AspectInterface.php
│   ├── JoinPointInterface.php
│   ├── ProceedingJoinPointInterface.php
│   └── AspectKernelInterface.php
│
├── Runtime/                 # 运行时核心
│   ├── JoinPoint.php        # 封装调用上下文
│   ├── ProceedingJoinPoint.php # Around 场景专用
│   └── AspectKernel.php     # 核心调度器（AOP 内核）
│
├── Reflection/              # 安全反射封装
│   ├── Reflector.php        # 安全获取类/方法/属性元数据
│   └── MetadataReader.php   # Attribute 元数据读取器（带缓存）
│
├── Exception/               # 自定义异常
│   └── AopException.php
│
└── Helper/                  # 工具函数
    └── Str.php              # 字符串匹配（通配符、正则）
```

## 注解说明

| 注解 | 说明 |
|------|------|
| `#[Aspect]` | 标记一个类为切面类 |
| `#[Before("expression")]` | 在目标方法前执行 |
| `#[After("expression")]` | 在目标方法后执行（无论是否异常） |
| `#[Around("expression")]` | 环绕执行，可控制是否继续调用原方法 |
| `#[Pointcut("expression")]` | 定义可复用的切入点表达式 |
| `#[Priority(number)]` | 控制切面执行顺序 |

## 切入点表达式

支持的切入点表达式语法：
- `execution(* App\Service\*->send*(..))` - 匹配 send 开头的方法
- `execution(public App\Payment\*->process())` - 匹配特定方法
- `within(App\Controller\*)` - 匹配某个命名空间下所有类

## 配置

可以通过 `config/aop.php` 配置文件进行配置：

```php
return [
    'enable' => true,
    'mode' => 'proxy', // 'proxy' | 'compile'
    'cache' => [
        'driver' => 'file', // 'file', 'apcu', 'redis'
        'path' => '/tmp/aop/cache',
    ],
    'aspects' => [
        App\Aspect\LoggingAspect::class,
        App\Aspect\TransactionAspect::class,
    ],
];
```

## 框架集成

### Laravel 集成

```php
// 在 ServiceProvider 中注册
public function register()
{
    $this->app->singleton(AspectKernel::class, function ($app) {
        $kernel = new AspectKernel();
        $kernel->registerAspect(new LoggingAspect());
        return $kernel;
    });
}
```

## 测试

运行测试：

```bash
./vendor/bin/phpunit
```

## 许可证

Apache License, Version 2.0