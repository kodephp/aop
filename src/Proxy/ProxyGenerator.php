<?php

declare(strict_types=1);

namespace Kode\Aop\Proxy;

use Kode\Aop\Exception\AopException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * 代理类源码生成器
 *
 * 为目标类生成一个继承自它的子类，把命中通知的方法改写为
 * 「转发给内核 → 内核编排通知 → 回调 parent:: 原方法」。
 *
 * v3 相较 v2 修复/增强：
 * - **命名空间**：v2 直接 `class Ns\Cls__AopProxy extends ...`，
 *   对任何带命名空间的类都会 ParseError；v3 生成 `namespace Ns;` 声明；
 * - **构造函数**：v2 覆盖为空构造函数，导致目标类构造逻辑被吞掉；
 *   v3 完全不声明构造函数，直接继承；
 * - **类型渲染**：完整支持可空、联合、交集、DNF、self/parent/static；
 * - **安全跳过**：final / static / abstract / 引用参数 / 魔术方法；
 * - **void / never**：不会生成非法的 `return` 语句。
 *
 * @package Kode\Aop\Proxy
 * @author Kode Team <382601296@qq.com>
 */
final class ProxyGenerator
{
    /**
     * 生成代理类源码
     *
     * @param ReflectionClass $class 目标类
     * @param string $shortName 代理类短名（不含命名空间）
     * @param array<int, ReflectionMethod> $methods 需要织入的方法
     * @param bool $withOpenTag 是否输出 `<?php` 开头（写文件时需要）
     * @return string 代理类源码
     */
    public function generate(
        ReflectionClass $class,
        string $shortName,
        array $methods,
        bool $withOpenTag = false
    ): string {
        $namespace = $class->getNamespaceName();
        $target = $class->getName();
        $modifier = $class->isReadOnly() ? 'readonly ' : '';

        $body = '';

        foreach ($methods as $method) {
            $body .= $this->generateMethod($class, $method);
        }

        $code = '';

        if ($withOpenTag) {
            $code .= "<?php\n\ndeclare(strict_types=1);\n\n";
        }

        if ($namespace !== '') {
            $code .= "namespace {$namespace};\n\n";
        }

        $escapedTarget = addslashes($target);

        $code .= <<<PHP
{$modifier}class {$shortName} extends \\{$target} implements \\Kode\\Aop\\Contract\\ProxyInterface
{
    public const string __AOP_TARGET_CLASS = '{$escapedTarget}';

    public static ?\\Kode\\Aop\\Contract\\AspectKernelInterface \$__aopKernel = null;

    public static function __aopTargetClass(): string
    {
        return self::__AOP_TARGET_CLASS;
    }
{$body}}

PHP;

        return $code;
    }

    /**
     * 判断方法是否可以被织入
     *
     * @param ReflectionMethod $method 待检查方法
     * @param string|null $reason 输出参数，返回不可织入的原因
     */
    public function isWeavable(ReflectionMethod $method, ?string &$reason = null): bool
    {
        $reason = null;

        if ($method->isConstructor() || $method->isDestructor()) {
            $reason = '构造/析构方法';

            return false;
        }

        if (str_starts_with($method->getName(), '__')) {
            $reason = '魔术方法';

            return false;
        }

        if ($method->isStatic()) {
            $reason = '静态方法（无 $this 上下文）';

            return false;
        }

        if ($method->isFinal()) {
            $reason = 'final 方法无法被子类覆盖';

            return false;
        }

        if ($method->isAbstract()) {
            $reason = '抽象方法没有可回调的实现';

            return false;
        }

        if ($method->isPrivate()) {
            $reason = 'private 方法无法被子类覆盖';

            return false;
        }

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                $reason = '含引用传参，代理会破坏引用语义';

                return false;
            }

            if (!$this->canRenderDefaultValue($parameter)) {
                $reason = sprintf('参数 $%s 的默认值无法静态还原', $parameter->getName());

                return false;
            }
        }

        return true;
    }

    /**
     * 校验目标类是否可被代理
     *
     * @throws AopException 不可代理时抛出
     */
    public function assertProxyable(ReflectionClass $class): void
    {
        $name = $class->getName();

        if ($class->isInterface() || $class->isTrait() || $class->isEnum()) {
            throw AopException::proxyGenerationFailed($name, '接口、Trait 与枚举不支持继承式代理');
        }

        if ($class->isFinal()) {
            throw AopException::proxyGenerationFailed($name, 'final 类无法被继承，请去掉 final 或改用接口代理');
        }

        if ($class->isAbstract()) {
            throw AopException::proxyGenerationFailed($name, '抽象类无法实例化');
        }

        if ($class->isAnonymous()) {
            throw AopException::proxyGenerationFailed($name, '匿名类无法生成具名代理');
        }

        if ($class->isInternal()) {
            throw AopException::proxyGenerationFailed($name, 'PHP 内置类不支持代理');
        }
    }

    /**
     * 生成单个代理方法
     */
    private function generateMethod(ReflectionClass $class, ReflectionMethod $method): string
    {
        $name = $method->getName();
        $visibility = $method->isProtected() ? 'protected' : 'public';
        $signature = $this->renderParameters($class, $method);
        $returnType = $this->renderReturnType($class, $method);
        $argsExpression = $this->renderArgumentsArray($method);

        $returnKeyword = $this->isNonReturning($method) ? '' : 'return ';

        return <<<PHP


    {$visibility} function {$name}({$signature}){$returnType}
    {
        {$returnKeyword}self::\$__aopKernel->invokeAdvice(
            \$this,
            '{$name}',
            {$argsExpression},
            fn(mixed ...\$__aopArgs): mixed => parent::{$name}(...\$__aopArgs)
        );
    }
PHP;
    }

    /**
     * 目标方法是否为 void / never（不可写 return 表达式）
     */
    private function isNonReturning(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return in_array($type->getName(), ['void', 'never'], true);
    }

    /**
     * 渲染参数列表
     */
    private function renderParameters(ReflectionClass $class, ReflectionMethod $method): string
    {
        $parts = [];

        foreach ($method->getParameters() as $parameter) {
            $chunk = '';
            $type = $this->renderType($parameter->getType(), $class);

            if ($type !== '') {
                $chunk .= $type . ' ';
            }

            if ($parameter->isVariadic()) {
                $chunk .= '...';
            }

            $chunk .= '$' . $parameter->getName();

            if (!$parameter->isVariadic() && $parameter->isDefaultValueAvailable()) {
                $chunk .= ' = ' . $this->renderDefaultValue($parameter);
            }

            $parts[] = $chunk;
        }

        return implode(', ', $parts);
    }

    /**
     * 渲染调用参数数组表达式
     */
    private function renderArgumentsArray(ReflectionMethod $method): string
    {
        $parts = [];

        foreach ($method->getParameters() as $parameter) {
            $parts[] = ($parameter->isVariadic() ? '...$' : '$') . $parameter->getName();
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * 渲染返回类型声明
     */
    private function renderReturnType(ReflectionClass $class, ReflectionMethod $method): string
    {
        $rendered = $this->renderType($method->getReturnType(), $class);

        return $rendered === '' ? '' : ': ' . $rendered;
    }

    /**
     * 渲染任意类型（支持可空、联合、交集与 DNF）
     */
    private function renderType(?ReflectionType $type, ReflectionClass $class): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $this->resolveTypeName($type, $class);

            if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null' && strtolower($name) !== 'void') {
                return '?' . $name;
            }

            return $name;
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(
                fn(ReflectionType $inner): string => $this->renderType($inner, $class),
                $type->getTypes()
            ));
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];

            foreach ($type->getTypes() as $inner) {
                if ($inner instanceof ReflectionIntersectionType) {
                    $parts[] = '(' . $this->renderType($inner, $class) . ')';

                    continue;
                }

                // 联合类型只可能由具名类型与交集类型组成，此处必然是具名类型
                $parts[] = $this->resolveTypeName($inner, $class);
            }

            if ($type->allowsNull() && !in_array('null', array_map(strtolower(...), $parts), true)) {
                $parts[] = 'null';
            }

            return implode('|', $parts);
        }

        return '';
    }

    /**
     * 解析单个具名类型，把 self/parent 展开为真实类名
     */
    private function resolveTypeName(ReflectionNamedType $type, ReflectionClass $class): string
    {
        $name = $type->getName();

        if ($type->isBuiltin()) {
            return $name;
        }

        return match (strtolower($name)) {
            'self' => '\\' . $class->getName(),
            'parent' => '\\' . ($class->getParentClass() !== false ? $class->getParentClass()->getName() : $class->getName()),
            'static' => 'static',
            default => '\\' . ltrim($name, '\\'),
        };
    }

    /**
     * 判断参数默认值能否被静态还原为源码
     */
    private function canRenderDefaultValue(ReflectionParameter $parameter): bool
    {
        if ($parameter->isVariadic() || !$parameter->isDefaultValueAvailable()) {
            return true;
        }

        if ($parameter->isDefaultValueConstant()) {
            return $parameter->getDefaultValueConstantName() !== null;
        }

        return !$this->containsObject($parameter->getDefaultValue());
    }

    /**
     * 渲染参数默认值
     */
    private function renderDefaultValue(ReflectionParameter $parameter): string
    {
        if ($parameter->isDefaultValueConstant()) {
            $constant = (string) $parameter->getDefaultValueConstantName();

            if (str_contains($constant, '::')) {
                [$scope, $constantName] = explode('::', $constant, 2);

                $scope = match (strtolower($scope)) {
                    'self', 'static' => $parameter->getDeclaringClass()?->getName() ?? $scope,
                    'parent' => ($declaring = $parameter->getDeclaringClass()) instanceof ReflectionClass
                        ? (($parent = $declaring->getParentClass()) instanceof ReflectionClass ? $parent->getName() : $scope)
                        : $scope,
                    default => $scope,
                };

                return '\\' . ltrim($scope, '\\') . '::' . $constantName;
            }

            return '\\' . ltrim($constant, '\\');
        }

        return var_export($parameter->getDefaultValue(), true);
    }

    /**
     * 递归检测值中是否含有对象
     */
    private function containsObject(mixed $value): bool
    {
        if (is_object($value)) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsObject($item)) {
                return true;
            }
        }

        return false;
    }
}
