<?php

declare(strict_types=1);

namespace Kode\Aop\Pointcut;

use Closure;
use Kode\Aop\Exception\AopException;
use Kode\Aop\Helper\Str;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * 切入点表达式解析器
 *
 * 采用递归下降算法，将切入点表达式编译为一个匹配闭包，
 * 编译结果会被缓存，运行期只做闭包调用，几乎零开销。
 *
 * 支持的语法：
 *
 * 指示器（designator）：
 * - `execution(<修饰符> <返回类型> <类模式>-><方法模式>(<参数模式>))`
 * - `within(<类模式>)`                   —— 匹配类
 * - `@annotation(<注解类名>)`             —— 目标方法带有指定注解
 * - `@within(<注解类名>)` / `@target(...)` —— 目标类带有指定注解
 * - `method(<方法模式>)`                  —— 仅按方法名匹配
 * - `<命名切点>()`                        —— 引用 #[Pointcut] 定义的表达式
 * - `<类模式>-><方法模式>`                 —— 兼容旧版裸签名写法
 *
 * 逻辑运算：`&&`（与）、`||`（或）、`!`（非）、`()`（分组）
 *
 * 通配符：`*` 任意长度字符、`?` 单个字符、类模式后缀 `+` 表示包含子类型
 *
 * 参数模式：
 * - `(..)` 任意参数
 * - `()`   无参数
 * - `(int, string)` 精确类型
 * - `(int, ..)` 前缀类型匹配
 *
 * 示例：
 * ```php
 * PointcutParser::compile('execution(* App\Service\*->save*(..)) && !@annotation(App\Attr\NoLog)');
 * ```
 *
 * @package Kode\Aop\Pointcut
 * @author Kode Team <382601296@qq.com>
 */
final class PointcutParser
{
    /**
     * 编译结果缓存
     *
     * @var array<string, Closure(MatchContext): bool>
     */
    private static array $cache = [];

    /**
     * 待解析的表达式
     */
    private string $input = '';

    /**
     * 当前扫描位置
     */
    private int $pos = 0;

    /**
     * 表达式长度
     */
    private int $len = 0;

    /**
     * 命名切点解析回调
     *
     * @var (callable(string): ?string)|null
     */
    private $resolver;

    /**
     * 命名切点递归展开栈，用于检测循环引用
     *
     * @var array<string, true>
     */
    private array $expanding = [];

    private function __construct()
    {
    }

    /**
     * 编译切入点表达式
     *
     * @param string $expression 切入点表达式
     * @param (callable(string): ?string)|null $namedResolver 命名切点解析器，入参为切点名，返回其表达式
     * @return Closure(MatchContext): bool 匹配闭包
     * @throws AopException 表达式非法时抛出
     */
    public static function compile(string $expression, ?callable $namedResolver = null): Closure
    {
        $expression = trim($expression);

        if ($expression === '') {
            return static fn(MatchContext $context): bool => false;
        }

        $cacheable = $namedResolver === null;

        if ($cacheable && isset(self::$cache[$expression])) {
            return self::$cache[$expression];
        }

        $parser = new self();
        $parser->input = $expression;
        $parser->len = strlen($expression);
        $parser->resolver = $namedResolver;

        $matcher = $parser->parseOr();
        $parser->skipWhitespace();

        if (!$parser->eof()) {
            throw AopException::invalidPointcutExpression($expression);
        }

        if ($cacheable) {
            self::$cache[$expression] = $matcher;
        }

        return $matcher;
    }

    /**
     * 清空编译缓存
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * 解析或表达式
     *
     * @return Closure(MatchContext): bool
     */
    private function parseOr(): Closure
    {
        $left = $this->parseAnd();

        while (true) {
            $this->skipWhitespace();

            if (!$this->consumeIf('||') && !$this->consumeKeyword('or')) {
                break;
            }

            $right = $this->parseAnd();
            $prev = $left;
            $left = static fn(MatchContext $context): bool => $prev($context) || $right($context);
        }

        return $left;
    }

    /**
     * 解析与表达式
     *
     * @return Closure(MatchContext): bool
     */
    private function parseAnd(): Closure
    {
        $left = $this->parseUnary();

        while (true) {
            $this->skipWhitespace();

            if (!$this->consumeIf('&&') && !$this->consumeKeyword('and')) {
                break;
            }

            $right = $this->parseUnary();
            $prev = $left;
            $left = static fn(MatchContext $context): bool => $prev($context) && $right($context);
        }

        return $left;
    }

    /**
     * 解析一元表达式
     *
     * @return Closure(MatchContext): bool
     */
    private function parseUnary(): Closure
    {
        $this->skipWhitespace();

        if ($this->consumeIf('!')) {
            $inner = $this->parseUnary();

            return static fn(MatchContext $context): bool => !$inner($context);
        }

        return $this->parsePrimary();
    }

    /**
     * 解析基础表达式
     *
     * @return Closure(MatchContext): bool
     */
    private function parsePrimary(): Closure
    {
        $this->skipWhitespace();

        if ($this->peek() === '(') {
            $this->pos++;
            $inner = $this->parseOr();
            $this->skipWhitespace();

            if (!$this->consumeIf(')')) {
                throw AopException::invalidPointcutExpression($this->input);
            }

            return $inner;
        }

        return $this->parseTerm();
    }

    /**
     * 解析终结符
     *
     * @return Closure(MatchContext): bool
     */
    private function parseTerm(): Closure
    {
        $name = $this->readName();

        if ($name === '') {
            throw AopException::invalidPointcutExpression($this->input);
        }

        $hasArgs = false;
        $body = '';

        if ($this->peek() === '(') {
            $hasArgs = true;
            $body = $this->readBalanced();
        }

        return match (strtolower($name)) {
            'execution' => $this->buildExecutionMatcher($body),
            'within' => $this->buildWithinMatcher($body),
            '@annotation' => $this->buildMethodAnnotationMatcher($body),
            '@within', '@target' => $this->buildClassAnnotationMatcher($body),
            'method' => $this->buildMethodNameMatcher($body),
            default => $this->buildFallbackMatcher($name, $body, $hasArgs),
        };
    }

    /**
     * 构建 execution 匹配器
     *
     * @return Closure(MatchContext): bool
     */
    private function buildExecutionMatcher(string $signature): Closure
    {
        $signature = trim($signature);

        if ($signature === '') {
            throw AopException::invalidPointcutExpression($this->input);
        }

        // 先剥离末尾的参数签名，避免 `(string, int)` 中的空格干扰后续切分
        $argumentSpec = null;
        $head = $signature;

        if (str_ends_with($signature, ')')) {
            $open = $this->findMatchingOpenParen($signature);

            if ($open === null) {
                throw AopException::invalidPointcutExpression($this->input);
            }

            $argumentSpec = substr($signature, $open + 1, strlen($signature) - $open - 2);
            $head = rtrim(substr($signature, 0, $open));
        }

        // 再拆出前置的修饰符与返回类型，最后一段才是 类->方法
        $modifiers = [];
        $chunks = preg_split('/\s+/', trim($head)) ?: [];
        $target = (string) array_pop($chunks);

        foreach ($chunks as $chunk) {
            $lower = strtolower($chunk);

            if (in_array($lower, ['public', 'protected', 'private', 'static', 'final'], true)) {
                $modifiers[] = $lower;
            }
        }

        $requireStatic = in_array('static', $modifiers, true);
        $separator = str_contains($target, '->') ? '->' : (str_contains($target, '::') ? '::' : null);

        if ($separator === null) {
            throw AopException::invalidPointcutExpression($this->input);
        }

        if ($separator === '::') {
            $requireStatic = true;
        }

        [$classPattern, $methodPart] = explode($separator, $target, 2);

        $classPattern = trim($classPattern);
        $methodPattern = trim($methodPart);
        $argumentTypes = $this->parseArgumentSpec($argumentSpec);

        if ($methodPattern === '') {
            throw AopException::invalidPointcutExpression($this->input);
        }

        return function (MatchContext $context) use (
            $classPattern,
            $methodPattern,
            $argumentTypes,
            $modifiers,
            $requireStatic
        ): bool {
            if (!Str::matchExpression($methodPattern, $context->methodName)) {
                return false;
            }

            if (!self::matchClassPattern($classPattern, $context)) {
                return false;
            }

            $method = $context->method();

            if ($method !== null && !self::matchModifiers($method, $modifiers, $requireStatic)) {
                return false;
            }

            return self::matchArguments($method, $argumentTypes);
        };
    }

    /**
     * 构建 within 匹配器
     *
     * @return Closure(MatchContext): bool
     */
    private function buildWithinMatcher(string $classPattern): Closure
    {
        $classPattern = trim($classPattern);

        if ($classPattern === '') {
            throw AopException::invalidPointcutExpression($this->input);
        }

        return static fn(MatchContext $context): bool => self::matchClassPattern($classPattern, $context);
    }

    /**
     * 构建方法注解匹配器
     *
     * @return Closure(MatchContext): bool
     */
    private function buildMethodAnnotationMatcher(string $attribute): Closure
    {
        $attribute = ltrim(trim($attribute), '\\');

        if ($attribute === '') {
            throw AopException::invalidPointcutExpression($this->input);
        }

        return static function (MatchContext $context) use ($attribute): bool {
            $method = $context->method();

            if ($method === null) {
                return false;
            }

            return $method->getAttributes($attribute, \ReflectionAttribute::IS_INSTANCEOF) !== [];
        };
    }

    /**
     * 构建类注解匹配器
     *
     * @return Closure(MatchContext): bool
     */
    private function buildClassAnnotationMatcher(string $attribute): Closure
    {
        $attribute = ltrim(trim($attribute), '\\');

        if ($attribute === '') {
            throw AopException::invalidPointcutExpression($this->input);
        }

        return static function (MatchContext $context) use ($attribute): bool {
            $class = $context->class();

            while ($class !== null) {
                if ($class->getAttributes($attribute, \ReflectionAttribute::IS_INSTANCEOF) !== []) {
                    return true;
                }

                $class = $class->getParentClass() ?: null;
            }

            return false;
        };
    }

    /**
     * 构建方法名匹配器
     *
     * @return Closure(MatchContext): bool
     */
    private function buildMethodNameMatcher(string $methodPattern): Closure
    {
        $methodPattern = trim($methodPattern);

        if ($methodPattern === '') {
            throw AopException::invalidPointcutExpression($this->input);
        }

        return static fn(MatchContext $context): bool => Str::matchExpression($methodPattern, $context->methodName);
    }

    /**
     * 构建兜底匹配器：命名切点引用或旧版裸签名
     *
     * @return Closure(MatchContext): bool
     */
    private function buildFallbackMatcher(string $name, string $body, bool $hasArgs): Closure
    {
        $isSignature = str_contains($name, '->')
            || str_contains($name, '::')
            || str_contains($name, '\\')
            || str_contains($name, '*')
            || str_contains($name, '?');

        if ($hasArgs && !$isSignature) {
            return $this->buildNamedPointcutMatcher($name);
        }

        if ($isSignature) {
            return $this->buildExecutionMatcher($hasArgs ? $name . '(' . $body . ')' : $name);
        }

        return $this->buildNamedPointcutMatcher($name);
    }

    /**
     * 构建命名切点引用匹配器
     *
     * @return Closure(MatchContext): bool
     */
    private function buildNamedPointcutMatcher(string $name): Closure
    {
        if ($this->resolver === null) {
            throw new AopException(sprintf('切入点表达式引用了命名切点 "%s"，但当前上下文未提供解析器', $name));
        }

        if (isset($this->expanding[$name])) {
            throw new AopException(sprintf('命名切点 "%s" 存在循环引用', $name));
        }

        $expression = ($this->resolver)($name);

        if ($expression === null || trim($expression) === '') {
            throw new AopException(sprintf('命名切点 "%s" 未定义，请检查 #[Pointcut] 注解', $name));
        }

        $this->expanding[$name] = true;

        try {
            $nested = new self();
            $nested->input = trim($expression);
            $nested->len = strlen($nested->input);
            $nested->resolver = $this->resolver;
            $nested->expanding = $this->expanding;

            $matcher = $nested->parseOr();
            $nested->skipWhitespace();

            if (!$nested->eof()) {
                throw AopException::invalidPointcutExpression($expression);
            }

            return $matcher;
        } finally {
            unset($this->expanding[$name]);
        }
    }

    /**
     * 从末尾的 `)` 反向查找与之配对的 `(`
     *
     * @return int|null 开括号下标，未找到返回 null
     */
    private function findMatchingOpenParen(string $signature): ?int
    {
        $depth = 0;

        for ($index = strlen($signature) - 1; $index >= 0; $index--) {
            $char = $signature[$index];

            if ($char === ')') {
                $depth++;
            } elseif ($char === '(') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * 解析参数模式
     *
     * @return array{types: array<int, string>, variadic: bool}|null null 表示任意参数
     */
    private function parseArgumentSpec(?string $spec): ?array
    {
        if ($spec === null) {
            return null;
        }

        $spec = trim($spec);

        if ($spec === '..' || $spec === '*') {
            return null;
        }

        if ($spec === '') {
            return ['types' => [], 'variadic' => false];
        }

        $parts = array_map(trim(...), explode(',', $spec));
        $variadic = false;

        if (end($parts) === '..') {
            array_pop($parts);
            $variadic = true;
        }

        return ['types' => $parts, 'variadic' => $variadic];
    }

    /**
     * 匹配类模式
     *
     * 支持 `+` 后缀表示包含子类与实现类。
     */
    private static function matchClassPattern(string $pattern, MatchContext $context): bool
    {
        $includeSubtypes = str_ends_with($pattern, '+');

        if ($includeSubtypes) {
            $pattern = substr($pattern, 0, -1);
        }

        $pattern = ltrim($pattern, '\\');
        $className = ltrim($context->className, '\\');

        if (Str::matchExpression($pattern, $className)) {
            return true;
        }

        if (!$includeSubtypes) {
            return false;
        }

        $class = $context->class();

        if ($class === null) {
            return false;
        }

        foreach ([...array_values(class_parents($className) ?: []), ...array_values(class_implements($className) ?: [])] as $ancestor) {
            if (Str::matchExpression($pattern, ltrim((string) $ancestor, '\\'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * 匹配方法修饰符
     *
     * @param array<int, string> $modifiers
     */
    private static function matchModifiers(ReflectionMethod $method, array $modifiers, bool $requireStatic): bool
    {
        if ($requireStatic && !$method->isStatic()) {
            return false;
        }

        foreach ($modifiers as $modifier) {
            $ok = match ($modifier) {
                'public' => $method->isPublic(),
                'protected' => $method->isProtected(),
                'private' => $method->isPrivate(),
                'final' => $method->isFinal(),
                'static' => $method->isStatic(),
                default => true,
            };

            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * 匹配参数签名
     *
     * @param array{types: array<int, string>, variadic: bool}|null $spec
     */
    private static function matchArguments(?ReflectionMethod $method, ?array $spec): bool
    {
        if ($spec === null) {
            return true;
        }

        if ($method === null) {
            return true;
        }

        $parameters = $method->getParameters();
        $expected = $spec['types'];

        if ($spec['variadic']) {
            if (count($parameters) < count($expected)) {
                return false;
            }
        } elseif (count($parameters) !== count($expected)) {
            return false;
        }

        foreach ($expected as $index => $typePattern) {
            if ($typePattern === '*') {
                continue;
            }

            $type = $parameters[$index]->getType();
            $actual = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

            if (!Str::matchExpression(ltrim($typePattern, '\\'), ltrim($actual, '\\'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * 读取标识符（可能是指示器名或裸签名）
     */
    private function readName(): string
    {
        $this->skipWhitespace();
        $start = $this->pos;

        while (!$this->eof()) {
            $char = $this->input[$this->pos];

            if ($char === '(' || $char === ')' || $char === '&' || $char === '|' || $char === '!') {
                break;
            }

            if (ctype_space($char)) {
                break;
            }

            $this->pos++;
        }

        return substr($this->input, $start, $this->pos - $start);
    }

    /**
     * 读取一段括号包裹的内容（支持嵌套）
     */
    private function readBalanced(): string
    {
        // 当前字符必定为 '('
        $this->pos++;
        $start = $this->pos;
        $depth = 1;

        while (!$this->eof()) {
            $char = $this->input[$this->pos];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    $content = substr($this->input, $start, $this->pos - $start);
                    $this->pos++;

                    return $content;
                }
            }

            $this->pos++;
        }

        throw AopException::invalidPointcutExpression($this->input);
    }

    /**
     * 跳过空白字符
     */
    private function skipWhitespace(): void
    {
        while (!$this->eof() && ctype_space($this->input[$this->pos])) {
            $this->pos++;
        }
    }

    /**
     * 是否已扫描到末尾
     */
    private function eof(): bool
    {
        return $this->pos >= $this->len;
    }

    /**
     * 预看一个字符
     */
    private function peek(): string
    {
        return $this->input[$this->pos] ?? '';
    }

    /**
     * 若当前位置匹配给定字面量则消费之
     */
    private function consumeIf(string $literal): bool
    {
        if (substr($this->input, $this->pos, strlen($literal)) !== $literal) {
            return false;
        }

        $this->pos += strlen($literal);

        return true;
    }

    /**
     * 若当前位置匹配给定关键字（需要词边界）则消费之
     */
    private function consumeKeyword(string $keyword): bool
    {
        $length = strlen($keyword);

        if (strtolower(substr($this->input, $this->pos, $length)) !== $keyword) {
            return false;
        }

        $next = $this->input[$this->pos + $length] ?? ' ';

        if (!ctype_space($next) && $next !== '(' && $next !== '!') {
            return false;
        }

        $this->pos += $length;

        return true;
    }
}
