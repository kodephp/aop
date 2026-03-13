<?php

declare(strict_types=1);

namespace Kode\Aop\Runtime;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Kode\Aop\Contract\AspectInterface;
use Kode\Aop\Contract\AspectKernelInterface;
use Kode\Aop\Reflection\MetadataReader;
use Kode\Aop\Reflection\Reflector;
use Kode\Aop\Exception\AopException;
use Kode\Aop\Helper\Str;

/**
 * AOP 内核实现类
 *
 * 核心调度器，负责：
 * - 注册和管理切面
 * - 解析切入点表达式
 * - 生成代理对象
 * - 协调通知的执行顺序
 *
 * 支持的通知类型：
 * - Before：方法执行前
 * - After：方法执行后（无论是否异常）
 * - Around：环绕方法执行
 *
 * @package Kode\Aop\Runtime
 * @author Kode Team <382601296@qq.com>
 * @see AspectKernelInterface
 */
class AspectKernel implements AspectKernelInterface
{
    /**
     * 已注册的切面对象列表
     *
     * @var array<int, object>
     */
    protected array $aspects = [];

    /**
     * 是否已初始化
     */
    protected bool $initialized = false;

    /**
     * 代理类缓存
     *
     * @var array<string, class-string>
     */
    protected static array $proxyClassCache = [];

    /**
     * 切面元数据缓存
     *
     * @var array<string, array>
     */
    protected static array $aspectMetadataCache = [];

    /**
     * 单例实例
     */
    protected static ?AspectKernel $instance = null;

    /**
     * 获取 AspectKernel 单例实例
     *
     * @return AspectKernel 单例实例
     */
    public static function getInstance(): AspectKernel
    {
        return self::$instance ??= new self();
    }

    /**
     * 重置单例实例（主要用于测试）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
        self::$proxyClassCache = [];
        self::$aspectMetadataCache = [];
    }

    /**
     * {@inheritDoc}
     */
    public function registerAspect(object $aspect): void
    {
        $this->validateAspect($aspect);
        $this->aspects[] = $aspect;
        $this->cacheAspectMetadata($aspect);
    }

    /**
     * 验证切面对象是否有效
     *
     * @param object $aspect 切面对象
     * @throws AopException 如果切面无效
     */
    protected function validateAspect(object $aspect): void
    {
        if ($aspect instanceof AspectInterface) {
            return;
        }

        if (!MetadataReader::isAspectClass($aspect::class)) {
            throw new AopException(
                sprintf(
                    '切面类 %s 必须实现 AspectInterface 接口或使用 #[Aspect] 注解标记',
                    $aspect::class
                )
            );
        }
    }

    /**
     * 缓存切面元数据
     *
     * @param object $aspect 切面对象
     */
    protected function cacheAspectMetadata(object $aspect): void
    {
        $className = $aspect::class;

        if (isset(self::$aspectMetadataCache[$className])) {
            return;
        }

        $reflection = Reflector::getClass($aspect);
        $methods = MetadataReader::getAspectMethods($className);

        self::$aspectMetadataCache[$className] = [
            'class' => $reflection,
            'methods' => $methods,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getProxy(string $className): object
    {
        $proxyClassName = $this->getOrCreateProxyClass($className);
        return new $proxyClassName();
    }

    /**
     * 获取或创建代理类
     *
     * @param string $className 原始类名
     * @return string 代理类名
     */
    protected function getOrCreateProxyClass(string $className): string
    {
        $cacheKey = $className;

        if (isset(self::$proxyClassCache[$cacheKey])) {
            return self::$proxyClassCache[$cacheKey];
        }

        $proxyClassName = $className . '__AopProxy_' . hash('xxh128', $className);
        $this->generateProxyClass($className, $proxyClassName);

        return self::$proxyClassCache[$cacheKey] = $proxyClassName;
    }

    /**
     * {@inheritDoc}
     */
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        MetadataReader::clearCache();
        $this->initialized = true;
    }

    /**
     * 生成代理类
     *
     * @param string $className 原始类名
     * @param string $proxyClassName 代理类名
     * @throws AopException 如果无法生成代理类
     */
    protected function generateProxyClass(string $className, string $proxyClassName): void
    {
        $reflection = Reflector::getClass($className);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $proxyMethods = '';
        foreach ($methods as $method) {
            if ($this->shouldSkipMethod($method)) {
                continue;
            }

            $proxyMethods .= $this->generateProxyMethod($className, $method);
        }

        $proxyClassCode = $this->buildProxyClassCode($className, $proxyClassName, $proxyMethods);

        eval($proxyClassCode);
    }

    /**
     * 判断是否应该跳过该方法
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return bool 是否跳过
     */
    protected function shouldSkipMethod(ReflectionMethod $method): bool
    {
        return $method->isConstructor()
            || $method->isDestructor()
            || str_starts_with($method->getName(), '__');
    }

    /**
     * 生成代理方法代码
     *
     * @param string $className 原始类名
     * @param ReflectionMethod $method 方法反射对象
     * @return string 代理方法代码
     */
    protected function generateProxyMethod(string $className, ReflectionMethod $method): string
    {
        $methodName = $method->getName();
        $parameters = $this->buildMethodParameters($method);
        $parameterNames = $this->buildMethodParameterNames($method);
        $returnType = $this->buildMethodReturnType($method);

        $hasAround = $this->hasMatchingAdvice($className, $methodName, 'arounds');

        return $hasAround
            ? $this->buildAroundProxyMethod($className, $methodName, $parameters, $parameterNames, $returnType)
            : $this->buildStandardProxyMethod($className, $methodName, $parameters, $parameterNames, $returnType);
    }

    /**
     * 构建环绕通知代理方法
     */
    protected function buildAroundProxyMethod(
        string $className,
        string $methodName,
        string $parameters,
        string $parameterNames,
        string $returnType
    ): string {
        return <<<PHP

    public function {$methodName}({$parameters}){$returnType}
    {
        \$args = [{$parameterNames}];
        \$closure = fn({$parameters}) => parent::{$methodName}({$parameterNames});

        \$proceedingJoinPoint = new \Kode\Aop\Runtime\ProceedingJoinPoint(
            new \ReflectionClass('{$className}'),
            new \ReflectionMethod('{$className}', '{$methodName}'),
            \$this,
            \$args,
            '',
            \$closure
        );

        return \$this->executeAroundAdvices(\$proceedingJoinPoint);
    }
PHP;
    }

    /**
     * 构建标准代理方法（Before + After）
     */
    protected function buildStandardProxyMethod(
        string $className,
        string $methodName,
        string $parameters,
        string $parameterNames,
        string $returnType
    ): string {
        return <<<PHP

    public function {$methodName}({$parameters}){$returnType}
    {
        \$args = [{$parameterNames}];
        \$this->executeBeforeAdvices(\$this, new \ReflectionMethod('{$className}', '{$methodName}'), \$args);

        try {
            \$result = parent::{$methodName}({$parameterNames});
        } finally {
            \$this->executeAfterAdvices(\$this, new \ReflectionMethod('{$className}', '{$methodName}'), \$args, \$result);
        }

        return \$result;
    }
PHP;
    }

    /**
     * 构建代理类完整代码
     */
    protected function buildProxyClassCode(string $className, string $proxyClassName, string $proxyMethods): string
    {
        return <<<PHP
class {$proxyClassName} extends {$className}
{
    private static ?\Kode\Aop\Runtime\AspectKernel \$aopKernel = null;

    public function __construct()
    {
        self::\$aopKernel ??= \Kode\Aop\Runtime\AspectKernel::getInstance();
    }

    public function getAspects(): array
    {
        return self::\$aopKernel->getRegisteredAspects();
    }

    {$proxyMethods}

    private function executeBeforeAdvices(object \$object, \ReflectionMethod \$method, array \$args): void
    {
        \$kernel = self::\$aopKernel;
        \$className = \$object::class;
        \$methodName = \$method->getName();

        \$advices = [];
        foreach (\$kernel->getRegisteredAspects() as \$aspect) {
            \$metadata = \$kernel->getAspectMetadata(\$aspect);

            foreach (\$metadata['methods'] as \$aspectMethodName => \$methodMeta) {
                foreach (\$methodMeta['befores'] as \$before) {
                    if (\$kernel->matchesPointcut(\$className, \$methodName, \$before->pointcut)) {
                        \$advices[] = [
                            'aspect' => \$aspect,
                            'method' => \$aspectMethodName,
                            'priority' => \$methodMeta['priority'],
                        ];
                    }
                }
            }
        }

        usort(\$advices, fn(\$a, \$b) => \$a['priority'] <=> \$b['priority']);

        foreach (\$advices as \$advice) {
            \$joinPoint = new \Kode\Aop\Runtime\JoinPoint(
                new \ReflectionClass(\$className),
                \$method,
                \$object,
                \$args,
                ''
            );
            \$advice['aspect']->{\$advice['method']}(\$joinPoint);
        }
    }

    private function executeAfterAdvices(object \$object, \ReflectionMethod \$method, array \$args, mixed \$result): void
    {
        \$kernel = self::\$aopKernel;
        \$className = \$object::class;
        \$methodName = \$method->getName();

        \$advices = [];
        foreach (\$kernel->getRegisteredAspects() as \$aspect) {
            \$metadata = \$kernel->getAspectMetadata(\$aspect);

            foreach (\$metadata['methods'] as \$aspectMethodName => \$methodMeta) {
                foreach (\$methodMeta['afters'] as \$after) {
                    if (\$kernel->matchesPointcut(\$className, \$methodName, \$after->pointcut)) {
                        \$advices[] = [
                            'aspect' => \$aspect,
                            'method' => \$aspectMethodName,
                            'priority' => \$methodMeta['priority'],
                        ];
                    }
                }
            }
        }

        usort(\$advices, fn(\$a, \$b) => \$b['priority'] <=> \$a['priority']);

        foreach (\$advices as \$advice) {
            \$joinPoint = new \Kode\Aop\Runtime\JoinPoint(
                new \ReflectionClass(\$className),
                \$method,
                \$object,
                \$args,
                '',
                \$result
            );
            \$advice['aspect']->{\$advice['method']}(\$joinPoint);
        }
    }

    private function executeAroundAdvices(\Kode\Aop\Runtime\ProceedingJoinPoint \$proceedingJoinPoint): mixed
    {
        \$kernel = self::\$aopKernel;
        \$object = \$proceedingJoinPoint->getThis();
        \$method = \$proceedingJoinPoint->getMethod();
        \$className = \$object::class;
        \$methodName = \$method->getName();

        \$advices = [];
        foreach (\$kernel->getRegisteredAspects() as \$aspect) {
            \$metadata = \$kernel->getAspectMetadata(\$aspect);

            foreach (\$metadata['methods'] as \$aspectMethodName => \$methodMeta) {
                foreach (\$methodMeta['arounds'] as \$around) {
                    if (\$kernel->matchesPointcut(\$className, \$methodName, \$around->pointcut)) {
                        \$advices[] = [
                            'aspect' => \$aspect,
                            'method' => \$aspectMethodName,
                            'priority' => \$methodMeta['priority'],
                        ];
                    }
                }
            }
        }

        usort(\$advices, fn(\$a, \$b) => \$a['priority'] <=> \$b['priority']);

        if (empty(\$advices)) {
            return \$proceedingJoinPoint->proceed();
        }

        \$advice = \$advices[0];
        return \$advice['aspect']->{\$advice['method']}(\$proceedingJoinPoint);
    }

    private function matchesPointcut(string \$className, string \$methodName, string \$pointcut): bool
    {
        return self::\$aopKernel->matchesPointcut(\$className, \$methodName, \$pointcut);
    }
}
PHP;
    }

    /**
     * 检查是否有匹配的通知
     *
     * @param string $className 类名
     * @param string $methodName 方法名
     * @param string $adviceType 通知类型 (befores|afters|arounds)
     * @return bool 是否有匹配的通知
     */
    public function hasMatchingAdvice(string $className, string $methodName, string $adviceType): bool
    {
        foreach ($this->aspects as $aspect) {
            $metadata = $this->getAspectMetadata($aspect);

            foreach ($metadata['methods'] as $methodMeta) {
                foreach ($methodMeta[$adviceType] as $advice) {
                    $pointcut = $advice->pointcut;
                    if ($this->matchesPointcut($className, $methodName, $pointcut)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * 匹配切入点表达式
     *
     * 支持的表达式语法：
     * - execution(* Class->method(..)) - 执行方法
     * - execution(* Class->*(..)) - 类的所有方法
     * - execution(* Namespace\*->*(..)) - 命名空间下所有类的所有方法
     * - within(Namespace\*) - 命名空间下所有类
     *
     * @param string $className 类名
     * @param string $methodName 方法名
     * @param string $pointcut 切入点表达式
     * @return bool 是否匹配
     */
    public function matchesPointcut(string $className, string $methodName, string $pointcut): bool
    {
        if ($pointcut === '') {
            return false;
        }

        if (str_starts_with($pointcut, 'execution(')) {
            return $this->matchesExecutionPointcut($className, $methodName, $pointcut);
        }

        if (str_starts_with($pointcut, 'within(')) {
            return $this->matchesWithinPointcut($className, $pointcut);
        }

        return Str::matchExpression($pointcut, $className . '->' . $methodName);
    }

    /**
     * 匹配 execution 切入点表达式
     *
     * @param string $className 类名
     * @param string $methodName 方法名
     * @param string $pointcut 切入点表达式
     * @return bool 是否匹配
     */
    protected function matchesExecutionPointcut(string $className, string $methodName, string $pointcut): bool
    {
        $pattern = '/^execution\(\s*(.*?)\s*\)$/';
        if (!preg_match($pattern, $pointcut, $matches)) {
            return false;
        }

        $expression = $matches[1];

        $parts = explode('->', $expression);
        if (count($parts) !== 2) {
            return false;
        }

        [$classPattern, $methodPart] = $parts;

        $methodPattern = $methodPart;
        if (str_ends_with($methodPattern, '(..)')) {
            $methodPattern = substr($methodPattern, 0, -4);
        } elseif (str_ends_with($methodPattern, '()')) {
            $methodPattern = substr($methodPattern, 0, -2);
        }

        $classMatches = Str::matchExpression($classPattern, $className);
        $methodMatches = Str::matchExpression($methodPattern, $methodName);

        return $classMatches && $methodMatches;
    }

    /**
     * 匹配 within 切入点表达式
     *
     * @param string $className 类名
     * @param string $pointcut 切入点表达式
     * @return bool 是否匹配
     */
    protected function matchesWithinPointcut(string $className, string $pointcut): bool
    {
        $pattern = '/^within\(\s*(.*?)\s*\)$/';
        if (!preg_match($pattern, $pointcut, $matches)) {
            return false;
        }

        $namespacePattern = $matches[1];

        return Str::matchExpression($namespacePattern, $className);
    }

    /**
     * 获取已注册的切面列表
     *
     * @return array<int, object> 切面对象数组
     */
    public function getRegisteredAspects(): array
    {
        return $this->aspects;
    }

    /**
     * 获取切面元数据
     *
     * @param object $aspect 切面对象
     * @return array 切面元数据
     */
    public function getAspectMetadata(object $aspect): array
    {
        $className = $aspect::class;
        return self::$aspectMetadataCache[$className] ?? [];
    }

    /**
     * 构建方法参数声明
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return string 参数声明字符串
     */
    protected function buildMethodParameters(ReflectionMethod $method): string
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->buildParameterString($parameter);
        }

        return implode(', ', $parameters);
    }

    /**
     * 构建单个参数字符串
     *
     * @param \ReflectionParameter $parameter 参数反射对象
     * @return string 参数字符串
     */
    protected function buildParameterString(\ReflectionParameter $parameter): string
    {
        $paramStr = '';

        if ($parameter->hasType()) {
            $paramStr .= $this->buildTypeString($parameter->getType()) . ' ';
        }

        $paramStr .= '$' . $parameter->getName();

        if ($parameter->isDefaultValueAvailable()) {
            $paramStr .= ' = ' . $this->formatDefaultValue($parameter->getDefaultValue());
        }

        return $paramStr;
    }

    /**
     * 构建类型字符串
     *
     * @param \ReflectionType $type 类型反射对象
     * @return string 类型字符串
     */
    protected function buildTypeString(\ReflectionType $type): string
    {
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn(\ReflectionNamedType $t) => $this->normalizeTypeName($t),
                $type->getTypes()
            ));
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->normalizeTypeName($type);
        }

        return (string) $type;
    }

    /**
     * 规范化类型名称
     *
     * @param ReflectionNamedType $type 命名类型
     * @return string 规范化后的类型名称
     */
    protected function normalizeTypeName(ReflectionNamedType $type): string
    {
        $name = $type->getName();

        if ($type->isBuiltin()) {
            return $name;
        }

        return '\\' . $name;
    }

    /**
     * 格式化默认值
     *
     * @param mixed $value 默认值
     * @return string 格式化后的字符串
     */
    protected function formatDefaultValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => "'" . addslashes($value) . "'",
            is_numeric($value) => (string) $value,
            default => var_export($value, true),
        };
    }

    /**
     * 构建方法返回类型声明
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return string 返回类型声明字符串
     */
    protected function buildMethodReturnType(ReflectionMethod $method): string
    {
        if (!$method->hasReturnType()) {
            return '';
        }

        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionUnionType) {
            $types = array_map(
                fn(ReflectionNamedType $t) => $this->normalizeTypeName($t),
                $returnType->getTypes()
            );
            return ': ' . implode('|', $types);
        }

        if ($returnType instanceof ReflectionNamedType) {
            return ': ' . $this->normalizeTypeName($returnType);
        }

        return '';
    }

    /**
     * 构建方法参数名称列表
     *
     * @param ReflectionMethod $method 方法反射对象
     * @return string 参数名称列表字符串
     */
    protected function buildMethodParameterNames(ReflectionMethod $method): string
    {
        $names = array_map(
            fn(\ReflectionParameter $p) => '$' . $p->getName(),
            $method->getParameters()
        );

        return implode(', ', $names);
    }
}
