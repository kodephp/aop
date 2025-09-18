<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use Kode\Aop\Contract\AspectInterface;
use Kode\Aop\Contract\AspectKernelInterface;
use Kode\Aop\Contract\ProceedingJoinPointInterface;
use Kode\Aop\Reflection\MetadataReader;
use Kode\Aop\Reflection\Reflector;
use Kode\Aop\Exception\AopException;
use Kode\Aop\Runtime\JoinPoint;
use Kode\Aop\Runtime\ProceedingJoinPoint;
use ReflectionClass;
use ReflectionMethod;
use ReflectionUnionType;
use ReflectionNamedType;

/**
 * AOP 内核实现类
 * 
 * 核心调度器，负责注册切面、生成代理对象等
 */
class AspectKernel implements AspectKernelInterface
{
    /**
     * @var object[] 已注册的切面对象
     */
    protected array $aspects = [];

    /**
     * @var bool 是否已初始化
     */
    protected bool $initialized = false;

    /**
     * @var array 代理类缓存
     */
    protected static array $proxyClasses = [];

    /**
     * {@inheritDoc}
     */
    public function registerAspect(object $aspect): void
    {
        if (!$aspect instanceof AspectInterface) {
            throw new AopException('Aspect must implement AspectInterface');
        }

        $this->aspects[] = $aspect;
    }

    /**
     * 获取代理对象
     *
     * @param string $className 原始类名
     * @return object 代理对象
     */
    public function getProxy(string $className): object
    {
        // 生成代理类名
        $proxyClassName = $className . '_Proxy_' . uniqid();
        
        // 生成代理类
        $this->generateProxyClass($className, $proxyClassName);
        
        // 返回代理类的实例
        return new $proxyClassName();
    }

    /**
     * {@inheritDoc}
     */
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        // 初始化元数据读取器缓存等
        MetadataReader::clearCache();

        $this->initialized = true;
    }

    /**
     * 检查类是否有匹配的切面
     *
     * @param string $className 类名
     * @return bool
     */
    protected function hasMatchingAspects(string $className): bool
    {
        try {
            $class = Reflector::getClass($className);
        } catch (AopException $e) {
            return false;
        }

        // 检查所有已注册的切面
        foreach ($this->aspects as $aspect) {
            $aspectClass = Reflector::getClass($aspect);
            
            // 获取切面类中所有方法
            $methods = $aspectClass->getMethods(\ReflectionMethod::IS_PUBLIC);
            
            foreach ($methods as $method) {
                // 检查方法上的通知注解
                $befores = MetadataReader::getBefores($method);
                $afters = MetadataReader::getAfters($method);
                $arounds = MetadataReader::getArounds($method);
                
                if (!empty($befores) || !empty($afters) || !empty($arounds)) {
                    // 简单实现：如果有任何通知注解，就认为可能匹配
                    // 实际实现中需要解析表达式进行精确匹配
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 获取代理类名
     *
     * @param string $className 原始类名
     * @return string 代理类名
     */
    protected function getProxyClassName(string $className): string
    {
        return $className . '__AopProxy';
    }

    /**
     * 生成代理类
     *
     * @param string $className 原始类名
     * @param string $proxyClassName 代理类名
     * @return void
     */
    protected function generateProxyClass(string $className, string $proxyClassName): void
    {
        // 这里应该使用代码生成器（如 PHP-Parser）来动态生成代理类
        // 为简化实现，这里使用 eval，实际项目中应使用更安全的方式
        
        $reflection = new \ReflectionClass($className);
        
        // 获取原始类的所有公共方法
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        $proxyMethods = '';
        foreach ($methods as $method) {
            // 跳过构造函数、析构函数和魔术方法
            if ($method->isConstructor() || $method->isDestructor() || strpos($method->getName(), '__') === 0) {
                continue;
            }
            
            $methodName = $method->getName();
            $parameters = $this->getMethodParameters($method);
            $parameterNames = $this->getMethodParameterNames($method);
            $returnType = $this->getMethodReturnType($method);
            
            // 检查是否有环绕通知
            $hasAround = false;
            foreach ($this->aspects as $aspect) {
                $aspectClass = new \ReflectionClass($aspect);
                $aspectMethods = $aspectClass->getMethods(\ReflectionMethod::IS_PUBLIC);
                
                foreach ($aspectMethods as $aspectMethod) {
                    $arounds = MetadataReader::getArounds($aspectMethod);
                    foreach ($arounds as $around) {
                        $pointcut = $around->value;
                        if ($this->matchesPointcut($className, $methodName, $pointcut)) {
                            $hasAround = true;
                            break 3; // 跳出所有循环
                        }
                    }
                }
            }
            
            // 生成代理方法
            if ($hasAround) {
                // 如果有环绕通知，创建 ProceedingJoinPoint 并执行环绕通知
                $proxyMethods .= "
    public function {$methodName}({$parameters}){$returnType}
    {
        // 创建 ProceedingJoinPoint
        \$args = [{$parameterNames}];
        \$closure = function({$parameters}) {
            return parent::{$methodName}({$parameterNames});
        };
        
        \$proceedingJoinPoint = new \Kode\Aop\Runtime\ProceedingJoinPoint(\$this, new \ReflectionMethod('{$className}', '{$methodName}'), \$args, \$closure);
        return \$this->executeArounds(\$proceedingJoinPoint);
    }
";
            } else {
                // 如果没有环绕通知，执行前置和后置通知
                $proxyMethods .= "
    public function {$methodName}({$parameters}){$returnType}
    {
        // 执行前置通知
        \$args = [{$parameterNames}];
        \$this->executeBefores(\$this, new \ReflectionMethod('{$className}', '{$methodName}'), \$args);
        
        try {
            // 调用原始方法
            \$result = parent::{$methodName}({$parameterNames});
        } finally {
            // 执行后置通知
            \$this->executeAfters(\$this, new \ReflectionMethod('{$className}', '{$methodName}'), \$args, \$result);
        }
        
        return \$result;
    }
";
            }
        }

        $proxyClass = "
  class " . $proxyClassName . " extends " . $className . "
  {
    /**
     * @var \Kode\Aop\Runtime\AspectKernel 当前AOP内核实例
     */
    private static \$aopKernel;

    public function __construct()
    {
        // 初始化AOP内核实例
        self::$aopKernel = \Kode\Aop\Runtime\AspectKernel::getInstance();
    }

    /**
     * 获取已注册的切面列表
     *
     * @return object[] 切面对象数组
     */
    public function getAspects(): array
    {
        return self::\$aopKernel->aspects;
    }

    {\$proxyMethods}

    /**
     * 执行前置通知
     */
    private function executeBefores(object \$object, \ReflectionMethod \$method, array \$args): void
    {
        \$aspects = self::\$aopKernel->aspects;
        \$className = get_class(\$object);
        \$methodName = \$method->getName();
        
        foreach (\$aspects as \$aspect) {
            \$aspectMethods = (new \ReflectionClass(\$aspect))->getMethods(\ReflectionMethod::IS_PUBLIC);
            
            foreach (\$aspectMethods as \$aspectMethod) {
                \$befores = MetadataReader::getBefores(\$aspectMethod);
                
                foreach (\$befores as \$before) {
                    \$pointcut = \$before->value;
                    if (\$this->matchesPointcut(\$className, \$methodName, \$pointcut)) {
                        \$joinPoint = new \\Kode\\Aop\\Runtime\\JoinPoint(\$object, \$method, \$args);
                        \$aspectMethod->invoke(\$aspect, \$joinPoint);
                    }
                }
            }
        }
    }

    /**
     * 执行后置通知
     */
    private function executeAfters(object \$object, \ReflectionMethod \$method, array \$args, mixed \$result): void
    {
        \$aspects = self::\$aopKernel->aspects;
        \$className = get_class(\$object);
        \$methodName = \$method->getName();
        
        foreach (\$aspects as \$aspect) {
            \$aspectMethods = (new \ReflectionClass(\$aspect))->getMethods(\ReflectionMethod::IS_PUBLIC);
            
            foreach (\$aspectMethods as \$aspectMethod) {
                \$afters = MetadataReader::getAfters(\$aspectMethod);
                
                foreach (\$afters as \$after) {
                    \$pointcut = \$after->value;
                    if (\$this->matchesPointcut(\$className, \$methodName, \$pointcut)) {
                        \$joinPoint = new \\Kode\\Aop\\Runtime\\JoinPoint(\$object, \$method, \$args, \$result);
                        \$aspectMethod->invoke(\$aspect, \$joinPoint);
                    }
                }
            }
        }
    }

    /**
     * 执行环绕通知
     */
    private function executeArounds(\\Kode\\Aop\\Runtime\\ProceedingJoinPoint \$proceedingJoinPoint): mixed
    {
        \$aspects = self::\$aopKernel->aspects;
        \$object = \$proceedingJoinPoint->getThis();
        \$method = \$proceedingJoinPoint->getMethod();
        \$className = get_class(\$object);
        \$methodName = \$method->getName();
        
        foreach (\$aspects as \$aspect) {
            \$aspectMethods = (new \ReflectionClass(\$aspect))->getMethods(\ReflectionMethod::IS_PUBLIC);
            
            foreach (\$aspectMethods as \$aspectMethod) {
                \$arounds = MetadataReader::getArounds(\$aspectMethod);
                
                foreach (\$arounds as \$around) {
                    \$pointcut = \$around->value;
                    if (\$this->matchesPointcut(\$className, \$methodName, \$pointcut)) {
                        return \$aspectMethod->invoke(\$aspect, \$proceedingJoinPoint);
                    }
                }
            }
        }
        
        // 如果没有匹配的环绕通知，则继续执行原方法
        return \$proceedingJoinPoint->proceed();
    }

    /**
     * 检查切入点表达式是否匹配
     */
    private function matchesPointcut(string \$className, string \$methodName, string \$pointcut): bool
    {
        // 简化的匹配逻辑，实际实现应该支持更复杂的表达式
        // 例如：execution(* App\Service\*->send*(..))
        
        // 简单实现：检查是否包含类名和方法名
        return (strpos(\$pointcut, \$className) !== false || strpos(\$pointcut, '*') !== false) &&
               (strpos(\$pointcut, \$methodName) !== false || strpos(\$pointcut, '*') !== false);
    }
}
";
        
        // 使用eval生成代理类
        eval($proxyClass);
    }

    /**
     * 获取方法参数声明
     *
     * @param \ReflectionMethod $method
     * @return string
     */
    protected function getMethodParameters(\ReflectionMethod $method): string
    {
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $paramStr = '';
            
            // 参数类型
            if ($parameter->hasType()) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionUnionType) {
                    $types = [];
                    foreach ($type->getTypes() as $t) {
                        $types[] = $t->getName();
                    }
                    $paramStr .= implode('|', $types) . ' ';
                } else {
                    $paramStr .= $type->getName() . ' ';
                }
            }
            
            // 参数名
            $paramStr .= '$' . $parameter->getName();
            
            // 默认值
            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
                if (is_null($default)) {
                    $paramStr .= ' = null';
                } elseif (is_bool($default)) {
                    $paramStr .= ' = ' . ($default ? 'true' : 'false');
                } elseif (is_string($default)) {
                    $paramStr .= ' = \'' . addslashes($default) . '\'';
                } elseif (is_numeric($default)) {
                    $paramStr .= ' = ' . $default;
                } else {
                    $paramStr .= ' = ' . var_export($default, true);
                }
            }
            
            $parameters[] = $paramStr;
        }
        
        return implode(', ', $parameters);
    }

    /**
     * 获取方法返回类型声明
     *
     * @param \ReflectionMethod $method
     * @return string
     */
    protected function getMethodReturnType(\ReflectionMethod $method): string
    {
        if (!$method->hasReturnType()) {
            return '';
        }
        
        $returnType = $method->getReturnType();
        
        if ($returnType instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($returnType->getTypes() as $t) {
                $types[] = $t->getName();
            }
            return ': ' . implode('|', $types);
        } elseif ($returnType instanceof \ReflectionNamedType) {
            $typeName = $returnType->getName();
            // 处理内置类型
            if ($returnType->isBuiltin()) {
                return ': ' . $typeName;
            }
            // 处理类类型
            return ': \\' . $typeName;
        }
        
        return '';
    }

    /**
     * 获取方法参数名称列表
     *
     * @param \ReflectionMethod $method
     * @return string
     */
    protected function getMethodParameterNames(\ReflectionMethod $method): string
    {
        $names = [];
        foreach ($method->getParameters() as $parameter) {
            $names[] = '$' . $parameter->getName();
        }
        
        return implode(', ', $names);
    }

    /**
     * 获取AspectKernel单例实例
     *
     * @return AspectKernel
     */
    public static function getInstance(): AspectKernel
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }
}