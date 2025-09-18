# IDE 支持说明

本包使用 PHP 8.1 原生 Attribute，主流 IDE（PhpStorm、VS Code + Intelephense）可自动识别：

- `#[Before]`, `#[After]`, `#[Around]` 可点击跳转
- 方法参数提示 `JoinPoint` 类型
- 支持 `@var` 注解辅助推断

## 示例

```php
#[Before("execution(* App\Service\UserService->createUser(..))")]
public function logBefore(JoinPoint $jp): void
{
    // $jp->getArguments() 自动提示
}
```

## 类型提示

所有接口和类都提供了完整的类型提示，IDE 可以自动识别并提供以下功能：

1. 方法参数自动补全
2. 返回值类型推断
3. 错误检查和警告
4. 代码跳转和引用查找

## 最佳实践

为了获得最佳的 IDE 支持，建议：

1. 使用完整的类名导入：
   ```php
   use Kode\Aop\Attribute\Before;
   use Kode\Aop\Runtime\JoinPoint;
   ```

2. 在方法注释中使用类型提示：
   ```php
   #[Before("execution(* App\Service\*->save(..))")]
   public function logSave(JoinPoint $joinPoint): void
   {
       // IDE 可以推断 $joinPoint 的所有方法
   }
   ```

3. 使用 Psalm 或 PHPStan 进行静态分析，确保类型安全。