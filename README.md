# Kode/AOP - PHP 8.3+ 轻量级 AOP 框架

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.3-8892BF)](https://php.net/)
[![License](https://img.shields.io/badge/License-Apache--2.0-green)](LICENSE)

基于 PHP 8.3+ 原生属性（Attribute）实现的轻量级、高性能、高扩展性 AOP（面向切面编程）组件。

## ✨ 特性

- **原生支持**：基于 PHP 8.3+ 原生属性（Attribute）实现，IDE 友好
- **轻量级**：仅依赖 `kode/attributes` 包，无其他框架依赖
- **门面 API**：一行代码完成「注册切面 + 初始化 + 取代理」（`Aop::boot()` / `Aop::proxy()` / `Aop::wrap()`）
- **共享属性缓存**：基于 `kode/attributes` 2.x，可注入共享缓存（如 `RedisCache` / APCu）让多进程复用反射元数据，`Aop::setCache()` 一行接入
- **严格模式**：通知属性实例化失败立即抛错（不再静默跳过），`Aop::strict()` 可切换
- **五种通知**：前置（Before）、后置（After）、环绕（Around）、返回后（AfterReturning）、异常（AfterThrowing）
- **洋葱式 Around 链**：支持同一方法上多个 Around 通知正确嵌套（修复 v2 仅优先级最高者生效的问题）
- **丰富切入点**：`execution` / `within` / `@annotation` / `@within` / `@target` / `method`，支持 `&&` `||` `!` 逻辑运算、`类名+` 子类型、参数类型签名
- **命名空间代理**：生成的代理类与目标类处于同一命名空间，彻底修复 v2 的 `ParseError`
- **构造函数保留**：代理类完全继承目标类构造函数，不会吞掉构造逻辑
- **文件缓存**：代理类可落盘为真实 PHP 文件并被 OPcache 缓存，且按切面集合指纹隔离，避免脏缓存
- **类型安全**：充分利用 PHP 8.3 的类型系统与 `#[\Override]` 属性
- **优先级控制**：通过 `#[Priority]` 注解控制通知执行顺序（After 系列遵循「先进后出」栈语义）

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

### Aop 门面（推荐用法）

`Kode\Aop\Aop` 是对内核单例的静态封装，一行即可完成「注册切面 + 初始化 + 取代理」：

```php
use Kode\Aop\Aop;

// 方式一：直接传切面实例/类名
Aop::boot([LoggingAspect::class, TransactionAspect::class], __DIR__ . '/runtime/aop');

/** @var UserService $userService */
$userService = Aop::proxy(UserService::class);
$userService->getUser(1);

// 方式二：吃配置数组（结构见 config/aop.php）
Aop::bootFromConfig(require __DIR__ . '/config/aop.php');

// 把已有实例包装为代理（适合 DI 容器场景）
$proxied = Aop::wrap($alreadyCreatedService);

// 注入共享属性缓存（kode/attributes 2.x）：多 worker / 多节点复用反射元数据
Aop::setCache(new \Kode\Attributes\Cache\RedisCache($redis));

// 严格模式默认开启；若需回退为宽容模式（属性实例化失败静默跳过）可关闭
Aop::strict(false);
```

门面还提供 `Aop::advicesFor()`（调试命中通知）、`Aop::diagnostics()`（运行期诊断）、`Aop::reset()`（测试隔离）等方法。

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

#### AfterReturning（返回后通知）

仅在目标方法**正常返回**后执行，可读取甚至替换返回值；与 `#[After]` 的区别是它不在异常时执行。

```php
#[AfterReturning("execution(* App\Service\UserService->getUser(..))")]
public function cacheResult(JoinPointInterface $joinPoint): mixed
{
    $value = $joinPoint->getResult();
    // 写缓存……
    return $value; // 返回非 null 会覆盖原返回值；返回 null 保持原值
}
```

#### AfterThrowing（异常通知）

仅在目标方法**抛出异常**时执行，适用于异常上报、告警、审计。可通过 `$throwable` 限定只捕获特定异常类型；执行完毕后异常继续向上抛出（不吞异常）。

```php
#[AfterThrowing("execution(* App\Service\*->*(..))", throwable: \RuntimeException::class)]
public function report(JoinPointInterface $joinPoint): void
{
    $e = $joinPoint->getException();
    error_log($e?->getMessage() ?? '');
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
| `execution(<修饰符> <返回> <类>-><方法>(<参数>))` | 按方法签名匹配 | `execution(public * App\Service\UserService->createUser(..))` |
| `execution(* Class->method(..))` | 执行方法 | `execution(* UserService->createUser(..))` |
| `execution(* Class->*(..))` | 类的所有方法 | `execution(* UserService->*(..))` |
| `execution(* Namespace\*->*(..))` | 命名空间下所有类的所有方法 | `execution(* App\Service\*->*(..))` |
| `within(Namespace\*)` | 命名空间下所有类 | `within(App\Controller\*)` |
| `within(Class+)` | 类及其子类 / 实现类 | `within(App\Service\BaseService+)` |
| `@annotation(Ann)` | 目标方法带有指定注解 | `@annotation(App\Attr\NoLog)` |
| `@within(Ann)` / `@target(Ann)` | 目标类带有指定注解 | `@within(App\Attr\Logged)` |
| `method(name)` | 仅按方法名匹配 | `method(createUser)` |
| `namedPointcut()` | 引用 `#[Pointcut]` 命名的切点 | `logAll()` |

`execution` 还支持：

- **修饰符**：`public` / `protected` / `private` / `static` / `final`
- **参数签名**：`(..)` 任意参数、`()` 无参、`(int, string)` 精确类型、`(int, ..)` 前缀类型匹配
- **逻辑运算**：`&&`（与）、`||`（或）、`!`（非）、`()`（分组），关键字 `and` / `or` 等同
- **子类型**：类模式后缀 `+` 表示包含子类与实现类

示例：

```php
// 匹配某命名空间下所有 save* 方法，但排除带 @NoLog 注解的方法
execution(* App\Service\*->save*(..)) && !@annotation(App\Attr\NoLog)

// 匹配基类及其全部子类的任意方法
within(App\Service\BaseService+)
```

通配符说明：
- `*`：匹配任意数量的任意字符（类 / 方法名中均可使用）
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
├── Aop.php                  # 门面：一行完成注册/初始化/取代理
├── Attribute/               # 原生注解定义
│   ├── Aspect.php           # 切面标记
│   ├── Before.php           # 方法前执行
│   ├── After.php            # 方法后执行（无论异常）
│   ├── Around.php           # 环绕执行（可控制流程）
│   ├── AfterReturning.php   # 返回后执行（可替换返回值）
│   ├── AfterThrowing.php    # 异常时执行（按异常类型过滤）
│   ├── Pointcut.php         # 命名切点
│   └── Priority.php         # 执行优先级
│
├── Pointcut/                # 切入点表达式
│   ├── PointcutParser.php   # 递归下降解析器（编译为匹配闭包）
│   └── MatchContext.php     # 匹配上下文（惰性反射）
│
├── Advice/                  # 通知编排
│   ├── Advice.php           # 单条通知值对象
│   ├── AdviceSet.php        # 某方法命中的通知集合
│   ├── AdviceRegistry.php   # 注册表 + 匹配结果缓存
│   ├── AdviceExecutor.php   # Before/Around/After... 时序编排
│   └── AdviceType.php       # 通知类型枚举
│
├── Proxy/                   # 代理生成
│   ├── ProxyGenerator.php   # 代理类源码生成器
│   └── ProxyFactory.php     # 命名/生成/缓存/实例化
│
├── Contract/                # 接口契约
│   ├── AspectInterface.php
│   ├── ProxyInterface.php   # 代理对象标记
│   ├── JoinPointInterface.php
│   ├── ProceedingJoinPointInterface.php
│   └── AspectKernelInterface.php
│
├── Runtime/                 # 运行时核心
│   ├── JoinPoint.php        # 封装调用上下文
│   ├── ProceedingJoinPoint.php # Around 场景专用（洋葱链）
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

- PHP >= 8.3
- Composer >= 2.0
- kode/attributes ^2.1

## 📄 许可证

[Apache License 2.0](LICENSE)

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📮 联系方式

- Email: 382601296@qq.com
- GitHub: https://github.com/kodephp/aop
