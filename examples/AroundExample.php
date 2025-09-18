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
use Kode\Aop\Contract\AspectInterface;

// 示例服务类
class CalculatorService
{
    public function add(int $a, int $b): int
    {
        echo "执行原始方法: add($a, $b)\n";
        return $a + $b;
    }
    
    public function divide(int $a, int $b): float
    {
        echo "执行原始方法: divide($a, $b)\n";
        if ($b === 0) {
            throw new InvalidArgumentException("除数不能为零");
        }
        return $a / $b;
    }
}

// 示例切面类
#[Aspect]
class CalculatorAspect implements AspectInterface
{
    #[Before("execution(* CalculatorService->add(..))")]
    public function logBeforeAdd(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        echo "[Before] 即将执行加法运算: {$args[0]} + {$args[1]}\n";
    }

    #[After("execution(* CalculatorService->add(..))")]
    public function logAfterAdd(JoinPoint $joinPoint): void
    {
        echo "[After] 加法运算执行完成\n";
    }
    
    #[Around("execution(* CalculatorService->divide(..))")]
    public function handleDivision(ProceedingJoinPoint $joinPoint): mixed
    {
        $args = $joinPoint->getArguments();
        echo "[Around] 拦截除法运算: {$args[0]} / {$args[1]}\n";
        
        // 可以修改参数
        if ($args[1] === 0) {
            echo "[Around] 检测到除零操作，返回默认值 0\n";
            return 0;
        }
        
        // 继续执行原方法
        $result = $joinPoint->proceed();
        echo "[Around] 除法运算结果: $result\n";
        
        // 可以修改返回值
        return $result;
    }
}

// 创建 AOP 内核
$kernel = new AspectKernel();

// 注册切面
$kernel->registerAspect(new CalculatorAspect());

// 获取代理对象
$calculator = $kernel->getProxy(CalculatorService::class);

echo "=== AOP 环绕通知示例 ===\n";

// 正常的加法运算（Before/After通知）
echo "1. 加法运算:\n";
$result = $calculator->add(5, 3);
echo "结果: $result\n\n";

// 除法运算（Around通知）
echo "2. 除法运算:\n";
$result = $calculator->divide(10, 2);
echo "结果: $result\n\n";

// 除零运算（Around通知处理异常）
echo "3. 除零运算:\n";
$result = $calculator->divide(10, 0);
echo "结果: $result\n\n";