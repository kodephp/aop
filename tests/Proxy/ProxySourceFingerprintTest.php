<?php

declare(strict_types=1);

namespace Kode\Aop\Tests\Proxy;

use Kode\Aop\Attribute\Aspect;
use Kode\Aop\Attribute\Before;
use Kode\Aop\Runtime\AspectKernel;
use Kode\Aop\Runtime\JoinPoint;
use PHPUnit\Framework\TestCase;

/**
 * 源码指纹回归用切面（切入点匹配临时生成的 *Service 类）
 */
#[Aspect]
class FingerprintAspect
{
    #[Before('execution(* *Service->create(..))')]
    public function before(JoinPoint $joinPoint): void
    {
    }
}

/**
 * 代理类文件缓存的源码指纹回归
 *
 * 代理类名此前只包含「切面指纹」：目标类源码变化（方法原型增删改）后仍会复用上一次落盘的
 * 代理文件，require 时与新父类原型不匹配，触发不可捕获的 E_COMPILE_ERROR。
 */
final class ProxySourceFingerprintTest extends TestCase
{
    private const string NS = 'Kode\\Aop\\Tests\\Fixture\\Generated';

    private string $root;

    private string $srcDir;

    private string $cacheDir;

    protected function setUp(): void
    {
        AspectKernel::resetInstance();

        $this->root = sys_get_temp_dir() . '/kode_aop_fp_' . bin2hex(random_bytes(6));
        $this->srcDir = $this->root . '/src';
        $this->cacheDir = $this->root . '/proxy';

        mkdir($this->srcDir, 0o775, true);
        mkdir($this->cacheDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->cacheDir, $this->srcDir, $this->root] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*') ?: [] as $entry) {
                if (is_file($entry)) {
                    @unlink($entry);
                }
            }

            @rmdir($dir);
        }
    }

    public function testSourceChangeProducesNewProxyClassAndPrunesStaleFile(): void
    {
        $short = 'FpSignatureService';
        $file = $this->writeClass($short, "public function create(string \$input): string\n    {\n        return \$input;\n    }");

        require_once $file;

        $kernel = $this->kernel();
        $first = $kernel->getProxyClass(self::NS . '\\' . $short);
        $this->assertNotNull($first, '切入点命中时应生成代理类');
        $this->assertSame([$this->fileName($first)], $this->proxyFiles());

        $this->putClass($file, $short, "public function create(string \$input, int \$extra = 0): string\n    {\n        return \$input;\n    }");
        clearstatcache();

        $second = $kernel->getProxyClass(self::NS . '\\' . $short);

        $this->assertNotNull($second);
        $this->assertNotSame($first, $second, '目标类源码指纹必须进入代理类名');
        $this->assertTrue(class_exists($second, false), '新代理类应已加载');
        $this->assertSame([$this->fileName($second)], $this->proxyFiles(), '同名旧代理文件应被清理，不无限堆积');
    }

    public function testUnchangedSourceKeepsSameProxyClass(): void
    {
        $short = 'FpStableService';
        $file = $this->writeClass($short, "public function create(string \$input): string\n    {\n        return \$input;\n    }");

        require_once $file;

        $kernel = $this->kernel();
        $class = self::NS . '\\' . $short;

        $first = $kernel->getProxyClass($class);
        $this->assertNotNull($first);
        $this->assertSame($first, $kernel->getProxyClass($class), '源码未变时不应换名重生成');
        $this->assertCount(1, $this->proxyFiles());
    }

    public function testTraitSourceChangeAlsoInvalidatesProxyClass(): void
    {
        $traitFile = $this->srcDir . '/FpWeaveTrait.php';
        $this->put($traitFile, "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . self::NS . ";\n\ntrait FpWeaveTrait\n{\n"
            . "    public function create(string \$input): string\n    {\n        return \$input;\n    }\n}\n");

        $short = 'FpTraitService';
        $file = $this->writeClass($short, "use \\Kode\\Aop\\Tests\\Fixture\\Generated\\FpWeaveTrait;\n");

        require_once $traitFile;
        require_once $file;

        $class = self::NS . '\\' . $short;
        $kernel = $this->kernel();
        $first = $kernel->getProxyClass($class);
        $this->assertNotNull($first, '由特征提供的方法同样应被织入');

        $this->put($traitFile, "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . self::NS . ";\n\ntrait FpWeaveTrait\n{\n"
            . "    public function create(string \$input, bool \$flag = false): string\n    {\n        return \$input;\n    }\n}\n");
        clearstatcache();

        $this->assertNotSame($first, $kernel->getProxyClass($class), '特征源码变化同样要作废代理');
    }

    public function testParentClassChangeAlsoInvalidatesProxyClass(): void
    {
        $parentFile = $this->srcDir . '/FpBaseService.php';
        $this->put($parentFile, "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . self::NS . ";\n\nclass FpBaseService\n{\n"
            . "    public function helper(string \$a): string\n    {\n        return \$a;\n    }\n}\n");

        $short = 'FpChildService';
        $file = $this->writeClass($short, "public function create(string \$input): string\n    {\n        return \$input;\n    }", 'extends FpBaseService');

        require_once $parentFile;
        require_once $file;

        $class = self::NS . '\\' . $short;
        $kernel = $this->kernel();
        $first = $kernel->getProxyClass($class);
        $this->assertNotNull($first);

        $this->put($parentFile, "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . self::NS . ";\n\nclass FpBaseService\n{\n"
            . "    public function helper(string \$a, int \$b = 1): string\n    {\n        return \$a;\n    }\n}\n");
        clearstatcache();

        $this->assertNotSame($first, $kernel->getProxyClass($class), '父类源码变化同样要作废代理');
    }

    private function kernel(): AspectKernel
    {
        $kernel = AspectKernel::getInstance();
        $kernel->setCacheDir($this->cacheDir);
        $kernel->registerAspect(new FingerprintAspect());
        $kernel->init();

        return $kernel;
    }

    private function writeClass(string $short, string $body, string $extends = ''): string
    {
        $file = $this->srcDir . '/' . $short . '.php';
        $this->putClass($file, $short, $body, $extends);

        return $file;
    }

    private function putClass(string $file, string $short, string $body, string $extends = ''): void
    {
        $head = $extends === '' ? '' : ' ' . $extends;

        $this->put(
            $file,
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . self::NS . ";\n\nclass {$short}{$head}\n{\n"
                . $body . "\n}\n"
        );
    }

    private function put(string $file, string $contents): void
    {
        file_put_contents($file, $contents);
    }

    private function fileName(string $proxyClass): string
    {
        return str_replace('\\', '_', ltrim($proxyClass, '\\')) . '.php';
    }

    /**
     * 缓存目录内的代理文件名列表（基名，排序）
     *
     * @return array<int, string>
     */
    private function proxyFiles(): array
    {
        $files = array_map(
            static fn (string $path): string => basename($path),
            glob($this->cacheDir . '/*.php') ?: []
        );

        sort($files);

        return $files;
    }
}
