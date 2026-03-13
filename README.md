# Kode/AOP - PHP 8.1+ 轻量级 AOP 框架

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF)](https://php.net/)
[![License](https://img.shields.io/badge/License-Apache--2.0-green)](LICENSE)

基于 PHP 8.1+ 原生属性（Attribute）实现的轻量级、高性能、高扩展性 AOP（面向切面编程）组件。

## ✨ 特性

- **原生支持**：基于 PHP 8.1+ 原生属性（Attribute）实现，IDE 友好
- **轻量级**：依赖 `kode/attributes` 包，无其他框架依赖
- **高性能**：使用缓存机制避免重复反射操作
- **类型安全**：充分利用 PHP 8.1+ 的类型系统，支持 readonly 属性
- **扩展性强**：支持前置通知（Before）、后置通知（After）、环绕通知（Around）
- **优先级控制**：支持通过 `#[Priority]` 注解控制切面执行顺序
- **切入点表达式**：支持通配符匹配，灵活定义切入点

## 📦 安装

```bash
composer require kode/aop
```

## 🚀 快速开始

### 1. 创建切面类

```php
<?php

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Attribute\After;
use Kode\Aop\Attribute\Around;
use Kode\Aop\Runtime\JoinPoint;
use Kode\Aop\Runtime\ProceedingJoinPoint;

#[Aspect]
class LoggingAspect
{
    #[Before("execution(* App\Service\UserService->createUser(..))")]
    public function logBefore(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "准备创建用户: " . json_encode($args[0]) . "\n";
    }

    #[After("execution(* App\Service\UserService->createUser(..))")]
    public function logAfter(JoinPoint $joinPoint): void
    {
        echo "用户创建操作已完成\n";
    }
}
```

### 2. 配置 AOP 内核

```php
<?php

use Kode\Aop\Runtime\AspectKernel;

$kernel = AspectKernel::getInstance();
$kernel->registerAspect(new LoggingAspect());
$kernel->init();

$userService = $kernel->getProxy(UserService::class);
```

### 3. 使用代理对象

```php
<?php

$result = $userService->createUser([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

## 📖 详细文档

### 通知类型

#### Before（前置通知）

在目标方法执行前执行，可以修改方法参数或执行预处理逻辑。

```php
#[Before("execution(* App\Service\*->*(..))")]
public function logBefore(JoinPoint $joinPoint): void
{
    $methodName = $joinPoint->getMethodName();
    $args = $joinPoint->getArguments();

    echo "方法 {$methodName} 即将执行\n";

    // 修改参数
    if (isset($args[0])) {
        $args[0] = trim($args[0]);
        $joinPoint->setArguments($args);
    }
}
```

#### After（后置通知）

在目标方法执行后执行（无论是否抛出异常），适用于资源清理、日志记录等场景。

```php
#[After("execution(* App\Service\*->*(..))")]
public function logAfter(JoinPoint $joinPoint): void
{
    $result = $joinPoint->getResult();
    echo "方法执行完成，返回值: " . json_encode($result) . "\n";
}
```

#### Around（环绕通知）

环绕目标方法执行，可以完全控制方法的执行流程。

```php
#[Around("execution(* App\Service\UserService->*(..))")]
public function transactional(ProceedingJoinPoint $joinPoint): mixed
{
    echo "开始事务\n";

    try {
        $result = $joinPoint->proceed();
        echo "提交事务\n";
        return $result;
    } catch (\Exception $e) {
        echo "回滚事务\n";
        throw $e;
    }
}
```

### 优先级控制

使用 `#[Priority]` 注解控制切面执行顺序，数字越小优先级越高。

```php
#[Aspect]
class PriorityAspect
{
    #[Before("execution(* App\Service\*->*(..))")]
    #[Priority(Priority::HIGHEST)]  // 最先执行
    public function first(JoinPoint $joinPoint): void
    {
        echo "第一个执行\n";
    }

    #[Before("execution(* App\Service\*->*(..))")]
    #[Priority(100)]
    public function second(JoinPoint $joinPoint): void
    {
        echo "第二个执行\n";
    }
}
```

### 切入点表达式

支持的切入点表达式语法：

| 表达式 | 说明 | 示例 |
|--------|------|------|
| `execution(* Class->method(..))` | 执行方法 | `execution(* UserService->createUser(..))` |
| `execution(* Class->*(..))` | 类的所有方法 | `execution(* UserService->*(..))` |
| `execution(* Namespace\*->*(..))` | 命名空间下所有类的所有方法 | `execution(* App\Service\*->*(..))` |
| `within(Namespace\*)` | 命名空间下所有类 | `within(App\Controller\*)` |

通配符说明：
- `*`：匹配任意数量的任意字符
- `..`：匹配任意参数列表
- `?`：匹配单个任意字符

### JoinPoint API

`JoinPoint` 类提供了丰富的方法来获取方法调用的上下文信息：

```php
$joinPoint->getClass();        // 获取目标类的反射对象
$joinPoint->getMethod();       // 获取目标方法的反射对象
$joinPoint->getThis();         // 获取目标对象实例
$joinPoint->getArguments();    // 获取方法参数
$joinPoint->setArguments([]);  // 设置方法参数
$joinPoint->getPointcut();     // 获取切入点表达式
$joinPoint->getResult();       // 获取返回值（After 通知）
$joinPoint->getMethodName();   // 获取方法名
$joinPoint->getClassName();    // 获取类名
$joinPoint->getArgument(0);    // 获取指定位置的参数
```

### ProceedingJoinPoint API

`ProceedingJoinPoint` 继承自 `JoinPoint`，额外提供了控制原方法执行的能力：

```php
$result = $joinPoint->proceed();                    // 使用原始参数执行
$result = $joinPoint->proceed(['newArg']);          // 使用新参数执行
$result = $joinPoint->proceedWithNamedParams([...]); // 使用命名参数执行
$closure = $joinPoint->getProceedClosure();         // 获取执行闭包
```

## 🏗️ 核心组件

```
src/
├── Attribute/               # 原生注解定义
│   ├── Aspect.php           # 切面标记
│   ├── Before.php           # 方法前执行
│   ├── After.php            # 方法后执行（无论异常）
│   ├── Around.php           # 环绕执行（可控制流程）
│   ├── Pointcut.php         # 切入点表达式
│   └── Priority.php         # 执行优先级
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
│   └── AspectKernel.php     # 核心调度器
│
├── Reflection/              # 安全反射封装
│   ├── Reflector.php        # 安全获取类/方法/属性元数据
│   └── MetadataReader.php   # Attribute 元数据读取器
│
├── Exception/               # 自定义异常
│   └── AopException.php
│
└── Helper/                  # 工具函数
    └── Str.php              # 字符串匹配
```

## 🔧 框架集成

### Laravel 集成

```php
// app/Providers/AopServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kode\Aop\Runtime\AspectKernel;

class AopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AspectKernel::class, function () {
            $kernel = AspectKernel::getInstance();
            $kernel->registerAspect(new \App\Aspects\LoggingAspect());
            $kernel->registerAspect(new \App\Aspects\TransactionAspect());
            $kernel->init();
            return $kernel;
        });
    }
}
```

### Symfony 集成

```php
// config/services.yaml
services:
    Kode\Aop\Runtime\AspectKernel:
        factory: ['@App\Aop\AspectKernelFactory', 'create']
        calls:
            - [init, []]

    App\Aop\AspectKernelFactory:
        class: App\Aop\AspectKernelFactory
```

### Hyperf 集成

```php
// config/autoload/dependencies.php
<?php

return [
    Kode\Aop\Runtime\AspectKernel::class => function () {
        $kernel = Kode\Aop\Runtime\AspectKernel::getInstance();
        $kernel->registerAspect(new \App\Aspect\LoggingAspect());
        $kernel->init();
        return $kernel;
    },
];
```

## 🧪 测试

运行测试：

```bash
composer test
```

运行代码覆盖率：

```bash
composer coverage
```

静态分析：

```bash
composer analyse
```

## 📋 系统要求

- PHP >= 8.1
- Composer >= 2.0
- kode/attributes ^1.0

## 📄 许可证

[Apache License 2.0](LICENSE)

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📮 联系方式

- Email: 382601296@qq.com
- GitHub: https://github.com/kodephp/aop
