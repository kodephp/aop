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
 * 为目标类生成代理类。根据目标类是否 `final` 自动选择两种策略：
 *
 * 1. **继承式（非 final 类）** —— `class X__AopProxy extends X implements ProxyInterface`，
 *    把命中通知的方法改写为「转发给内核 → 内核编排通知 → 回调 parent:: 原方法」。
 *
 * 2. **组合式（final 类）** —— `class X__AopProxy implements <接口...>, ProxyInterface`，
 *    内部持有一个被包装的真实实例（`$__subject`），所有公开方法 / 接口方法转发到该实例。
 *    由于不再继承目标类，final 类也能被代理；final 方法、protected 之外的场景同样适用。
 *    属性访问通过 `__get`/`__set` 等魔术方法委派给被包装实例，方法调用通过 `__call` 兜底。
 *
 * v3.2 相较 v3.1：新增组合式代理，使 `final` 类（无论是否实现接口）均可被代理。
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
     * @param array<int, ReflectionMethod> $methods 需要生成的方法（含 adviced 与接口方法）
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
        $composition = $class->isFinal();

        $body = '';

        foreach ($methods as $method) {
            $body .= $this->generateMethod($class, $method, $composition);
        }

        $code = '';

        if ($withOpenTag) {
            $code .= "<?php\n\ndeclare(strict_types=1);\n\n";
        }

        if ($namespace !== '') {
            $code .= "namespace {$namespace};\n\n";
        }

        $escapedTarget = addslashes($target);

        if ($composition) {
            $interfaces = $this->renderInterfaces($class);
            $header = "class {$shortName} implements {$interfaces}\\Kode\\Aop\\Contract\\ProxyInterface";
            $members = $this->compositionMembers($escapedTarget, $target);
        } else {
            $modifier = $class->isReadOnly() ? 'readonly ' : '';
            $header = "{$modifier}class {$shortName} extends \\{$target} implements \\Kode\\Aop\\Contract\\ProxyInterface";
            $members = '';
        }

        $code .= <<<PHP
{$header}
{
    public const string __AOP_TARGET_CLASS = '{$escapedTarget}';

    public static ?\\Kode\\Aop\\Contract\\AspectKernelInterface \$__aopKernel = null;

    public static function __aopTargetClass(): string
    {
        return self::__AOP_TARGET_CLASS;
    }
{$members}{$body}}

PHP;

        return $code;
    }

    /**
     * 渲染目标类实现的全部接口列表（含 ProxyInterface 之前的前缀逗号处理）
     *
     * @return string 例如 `\\Foo\\Bar, ` 或空串
     */
    private function renderInterfaces(ReflectionClass $class): string
    {
        $names = [];

        foreach ($class->getInterfaces() as $interface) {
            $names[] = '\\' . $interface->getName();
        }

        return $names === [] ? '' : implode(', ', $names) . ', ';
    }

    /**
     * 组合式代理的固定成员：被包装实例、绑定方法、构造函数与魔术委派
     *
     * @param string $escapedTarget addslashes 后的目标类名（用于常量字符串字面量）
     * @param string $target 原始目标类全限定名（用于 new 实例化）
     */
    private function compositionMembers(string $escapedTarget, string $target): string
    {
        return <<<PHP

    private object \$__subject;

    /**
     * 把一个已构造好的实例绑定为被包装目标（wrap 场景使用）
     */
    public function __aopBind(object \$subject): void
    {
        \$this->__subject = \$subject;
    }

    /**
     * 组合式代理构造函数：用传入的构造参数实例化被包装目标
     *
     * @param array<int|string, mixed> \$__aopArgs 透传给目标类构造函数的参数
     */
    public function __construct(array \$__aopArgs = [])
    {
        \$this->__subject = new \\{$target}(...\$__aopArgs);
    }

    public function __get(string \$__aopName): mixed
    {
        return \$this->__subject->{\$__aopName};
    }

    public function __set(string \$__aopName, mixed \$__aopValue): void
    {
        \$this->__subject->{\$__aopName} = \$__aopValue;
    }

    public function __isset(string \$__aopName): bool
    {
        return isset(\$this->__subject->{\$__aopName});
    }

    public function __unset(string \$__aopName): void
    {
        unset(\$this->__subject->{\$__aopName});
    }

    /**
     * 未显式声明方法的兜底转发：仍然经过内核（命中通知则织入，否则直接透传）
     */
    public function __call(string \$__aopMethod, array \$__aopArgs): mixed
    {
        return self::\$__aopKernel->invokeAdvice(
            \$this,
            \$__aopMethod,
            \$__aopArgs,
            fn(mixed ...\$__aopForward): mixed => \$this->__subject->{\$__aopMethod}(...\$__aopForward)
        );
    }

PHP;
    }

    /**
     * 判断方法是否可以被织入
     *
     * @param ReflectionMethod $method 待检查方法
     * @param string|null $reason 输出参数，返回不可织入的原因
     * @param bool $composition 是否为组合式代理（final 类）；组合模式放宽 final/引用/默认值的限制
     */
    public function isWeavable(ReflectionMethod $method, ?string &$reason = null, bool $composition = false): bool
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

        if ($method->isPrivate()) {
            $reason = 'private 方法无法被代理访问';

            return false;
        }

        if (!$composition) {
            if ($method->isAbstract()) {
                $reason = '抽象方法没有可回调的实现';

                return false;
            }

            if ($method->isFinal()) {
                $reason = 'final 方法无法被子类覆盖';

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
            throw AopException::proxyGenerationFailed($name, '接口、Trait 与枚举不支持代理');
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

        // final 类不再抛错：改用组合式代理（实现接口 + 包装），仍可正常织入。
    }

    /**
     * 生成单个代理方法
     *
     * @param bool $composition 组合式代理时，回调目标的包装实例而非 parent::
     */
    private function generateMethod(ReflectionClass $class, ReflectionMethod $method, bool $composition): string
    {
        $name = $method->getName();
        $visibility = $method->isProtected() ? 'protected' : 'public';
        $signature = $this->renderParameters($class, $method);
        $returnType = $this->renderReturnType($class, $method);
        $argsExpression = $this->renderArgumentsArray($method);

        $returnKeyword = $this->isNonReturning($method) ? '' : 'return ';

        $callback = $composition
            ? "\$this->__subject->{$name}(...\$__aopArgs)"
            : "parent::{$name}(...\$__aopArgs)";

        return <<<PHP


    {$visibility} function {$name}({$signature}){$returnType}
    {
        {$returnKeyword}self::\$__aopKernel->invokeAdvice(
            \$this,
            '{$name}',
            {$argsExpression},
            fn(mixed ...\$__aopArgs): mixed => {$callback}
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
     * 渲染参数列表（组合/继承通用；引用参数渲染 &，不可静态还原的默认值则省略）
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

            if ($parameter->isPassedByReference()) {
                $chunk .= '&';
            }

            $chunk .= '$' . $parameter->getName();

            if (!$parameter->isVariadic() && $parameter->isDefaultValueAvailable() && $this->canRenderDefaultValue($parameter)) {
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
