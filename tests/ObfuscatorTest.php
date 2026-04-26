<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator\Tests;

use GLOBUSstudio\PhpObfuscator\Exception\ObfuscationException;
use GLOBUSstudio\PhpObfuscator\NameGenerator;
use GLOBUSstudio\PhpObfuscator\Obfuscator;
use GLOBUSstudio\PhpObfuscator\Options;
use GLOBUSstudio\PhpObfuscator\SymbolTable;
use GLOBUSstudio\PhpObfuscator\Tests\Support\PhpRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ObfuscatorTest extends TestCase
{
    /**
     * @dataProvider snippetsProvider
     */
    #[DataProvider('snippetsProvider')]
    public function testObfuscatedCodeBehavesIdenticallyToOriginal(string $code, string $expectedOutput): void
    {
        $this->assertSame($expectedOutput, PhpRunner::run($code));

        // Default options - eval wrap, all renames, base64 strings, minified.
        $obfuscator = new Obfuscator(new Options(seed: 1));
        $obf = $obfuscator->obfuscate($code);
        $this->assertSame($expectedOutput, PhpRunner::run($obf), 'Default obfuscation broke runtime behaviour.');

        // Plain rewrite, no eval wrap, no string encoding, no minify.
        $obfuscator = new Obfuscator(new Options(
            encodeStrings: false,
            removeWhitespace: false,
            removeComments: false,
            wrapWithEval: false,
            seed: 2,
        ));
        $obf = $obfuscator->obfuscate($code);
        $this->assertSame($expectedOutput, PhpRunner::run($obf), 'Plain rewrite broke runtime behaviour.');

        // Minified but no eval wrap.
        $obfuscator = new Obfuscator(new Options(wrapWithEval: false, seed: 3));
        $obf = $obfuscator->obfuscate($code);
        $this->assertSame($expectedOutput, PhpRunner::run($obf), 'Minified rewrite broke runtime behaviour.');
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function snippetsProvider(): array
    {
        return [
            'plain variables and echo' => [
                <<<'PHP'
                <?php
                $first = "hello";
                $second = "world";
                echo $first . " " . $second;
                PHP,
                'hello world',
            ],

            'function declaration and call' => [
                <<<'PHP'
                <?php
                function greet($name) {
                    return "Hello, $name";
                }
                echo greet("Ada");
                PHP,
                'Hello, Ada',
            ],

            'recursive function with built-ins' => [
                <<<'PHP'
                <?php
                function factorial($n) {
                    if ($n <= 1) return 1;
                    return $n * factorial($n - 1);
                }
                echo factorial(6) . "/" . strlen("abcdef");
                PHP,
                '720/6',
            ],

            'class with method and property' => [
                <<<'PHP'
                <?php
                class Counter {
                    private int $value = 0;
                    public function inc(int $by = 1): self {
                        $this->value += $by;
                        return $this;
                    }
                    public function get(): int {
                        return $this->value;
                    }
                }
                $c = new Counter();
                echo $c->inc()->inc(4)->get();
                PHP,
                '5',
            ],

            'static method and class constant' => [
                <<<'PHP'
                <?php
                class Math {
                    const PI = 3;
                    public static function square(int $n): int {
                        return $n * $n;
                    }
                }
                echo Math::PI . ":" . Math::square(7);
                PHP,
                '3:49',
            ],

            'enum with cases and match' => [
                <<<'PHP'
                <?php
                enum Status: string {
                    case Active = "A";
                    case Inactive = "I";
                }
                function describe(Status $s): string {
                    return match($s) {
                        Status::Active => "on",
                        Status::Inactive => "off",
                    };
                }
                echo describe(Status::Active) . "/" . describe(Status::Inactive);
                PHP,
                'on/off',
            ],

            'constructor property promotion with readonly' => [
                <<<'PHP'
                <?php
                final class Point {
                    public function __construct(
                        public readonly int $x,
                        public readonly int $y,
                    ) {}
                    public function sum(): int { return $this->x + $this->y; }
                }
                $p = new Point(3, 4);
                echo $p->sum();
                PHP,
                '7',
            ],

            'global constants via define and const' => [
                <<<'PHP'
                <?php
                define('GREETING', 'hi');
                const SUFFIX = '!';
                echo GREETING . SUFFIX;
                PHP,
                'hi!',
            ],

            'arrow function and array_map' => [
                <<<'PHP'
                <?php
                $square = fn($n) => $n * $n;
                echo implode(',', array_map($square, [1, 2, 3]));
                PHP,
                '1,4,9',
            ],

            'nullsafe operator and string with quotes' => [
                <<<'PHP'
                <?php
                class Holder {
                    public ?string $value = null;
                }
                $h = new Holder();
                echo ($h->value ?? "fallback") . "/" . 'O\'Reilly';
                PHP,
                'fallback/O\'Reilly',
            ],

            'inheritance and interface' => [
                <<<'PHP'
                <?php
                interface Speaker {
                    public function speak(): string;
                }
                abstract class Animal implements Speaker {
                    public function __construct(protected string $name) {}
                }
                class Dog extends Animal {
                    public function speak(): string {
                        return $this->name . " says woof";
                    }
                }
                echo (new Dog("Rex"))->speak();
                PHP,
                'Rex says woof',
            ],

            'comments and docblocks are ignored' => [
                <<<'PHP'
                <?php
                /**
                 * Sums two ints.
                 */
                function add(int $a, int $b): int {
                    // simple addition
                    return $a + $b; # done
                }
                echo add(2, 3);
                PHP,
                '5',
            ],

            'closure with use clause' => [
                <<<'PHP'
                <?php
                $multiplier = 3;
                $scale = function (int $n) use ($multiplier): int {
                    return $n * $multiplier;
                };
                echo $scale(7);
                PHP,
                '21',
            ],

            'static property and class constant access' => [
                <<<'PHP'
                <?php
                class Counter {
                    public static int $value = 0;
                    public const STEP = 2;
                    public static function bump(): void {
                        self::$value += self::STEP;
                    }
                }
                Counter::bump();
                Counter::bump();
                echo Counter::$value . ":" . Counter::STEP;
                PHP,
                '4:2',
            ],

            'instanceof check and abstract base class' => [
                <<<'PHP'
                <?php
                abstract class Shape {
                    abstract public function area(): float;
                }
                class Square extends Shape {
                    public function __construct(private float $side) {}
                    public function area(): float { return $this->side * $this->side; }
                }
                $s = new Square(4);
                echo ($s instanceof Shape ? 'yes' : 'no') . ":" . $s->area();
                PHP,
                'yes:16',
            ],

            'function default parameter is constant expression' => [
                <<<'PHP'
                <?php
                function greet(string $name = 'world') {
                    return "Hello, $name";
                }
                echo greet();
                PHP,
                'Hello, world',
            ],

            'top level const followed by usage' => [
                <<<'PHP'
                <?php
                const MAX_LEN = 'limited';
                echo MAX_LEN;
                PHP,
                'limited',
            ],
        ];
    }

    public function testReservedVariablesAreNotRenamed(): void
    {
        $code = <<<'PHP'
        <?php
        $_SERVER['demo'] = 'ok';
        echo $_SERVER['demo'];
        PHP;
        $output = (new Obfuscator(new Options(wrapWithEval: false, seed: 1)))->obfuscate($code);
        $this->assertStringContainsString('$_SERVER', $output);
        $this->assertSame('ok', PhpRunner::run($output));
    }

    public function testNameGeneratorExhaustionIsReported(): void
    {
        $gen = new NameGenerator(1, 1, 1);
        // Reserve every possible 1-character ASCII letter (case-insensitive).
        $letters = str_split('abcdefghijklmnopqrstuvwxyz');
        $gen->reserve($letters);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate a unique identifier');
        $gen->generate();
    }

    public function testKeepingVariablesLeavesThemUntouched(): void
    {
        $code = '<?php $value = 1; echo $value;';
        $output = (new Obfuscator(new Options(
            obfuscateVariables: false,
            wrapWithEval: false,
            removeWhitespace: false,
            seed: 1,
        )))->obfuscate($code);
        $this->assertStringContainsString('$value', $output);
        $this->assertSame('1', PhpRunner::run($output));
    }

    public function testFunctionWithReferenceReturnIsHandled(): void
    {
        $code = <<<'PHP'
        <?php
        function &counter() {
            static $value = 0;
            $value++;
            return $value;
        }
        $a = counter();
        $a = counter();
        echo counter();
        PHP;
        $output = (new Obfuscator(new Options(seed: 1)))->obfuscate($code);
        $this->assertSame('3', PhpRunner::run($output));
    }

    public function testCloseTagIsStrippedBeforeEvalWrap(): void
    {
        $code = "<?php echo 'inline'; ?>";
        $output = (new Obfuscator(new Options(seed: 1)))->obfuscate($code);
        $this->assertStringContainsString('eval(base64_decode(', $output);
        $this->assertSame('inline', PhpRunner::run($output));
    }

    public function testMaybeEncodeStringSkipsEmptyStrings(): void
    {
        // The empty string literal (length 2: opening + closing quote) is too
        // short to encode, so it must be left untouched.
        $code = "<?php \$x = ''; echo strlen(\$x);";
        $output = (new Obfuscator(new Options(wrapWithEval: false, seed: 1)))->obfuscate($code);
        $this->assertStringNotContainsString('base64_decode', $output);
        $this->assertSame('0', PhpRunner::run($output));
    }

    public function testHelpersHandleEmptyTokens(): void
    {
        // Heredoc with embedded variable - exercises the interpolation
        // tracking and word-boundary helpers in the rewriter.
        $code = <<<'PHP'
        <?php
        $name = "Ada";
        $greeting = <<<EOT
        Hello, {$name}!
        EOT;
        echo $greeting;
        PHP;
        $output = (new Obfuscator(new Options(seed: 1)))->obfuscate($code);
        $this->assertSame('Hello, Ada!', PhpRunner::run($output));
    }

    public function testAbstractMethodWithoutBodyIsSafe(): void
    {
        $code = <<<'PHP'
        <?php
        interface Greeter {
            public function hello(): string;
        }
        class Greeting implements Greeter {
            public function hello(): string { return 'hi'; }
        }
        echo (new Greeting())->hello();
        PHP;
        $output = (new Obfuscator(new Options(seed: 1)))->obfuscate($code);
        $this->assertSame('hi', PhpRunner::run($output));
    }

    public function testEncodeStringsDisabledLeavesLiteralsUntouched(): void
    {
        $code = '<?php echo "hello world";';
        $output = (new Obfuscator(new Options(encodeStrings: false, wrapWithEval: false, seed: 1)))->obfuscate($code);
        $this->assertStringContainsString('"hello world"', $output);
        $this->assertStringNotContainsString('base64_decode', $output);
        $this->assertSame('hello world', PhpRunner::run($output));
    }

    public function testKeepConstantsLeavesDefineNamesUntouched(): void
    {
        $code = "<?php define('TAG', 'on'); echo TAG;";
        $output = (new Obfuscator(new Options(
            obfuscateConstants: false,
            wrapWithEval: false,
            removeWhitespace: false,
            seed: 1,
        )))->obfuscate($code);
        $this->assertStringContainsString("'TAG'", $output);
        $this->assertSame('on', PhpRunner::run($output));
    }

    public function testWriteFailureOnInvalidPathThrows(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'pob-in-');
        file_put_contents($src, '<?php echo 1;');
        try {
            // A file path nested under an existing regular file cannot be
            // created on any sane OS.
            $this->expectException(ObfuscationException::class);
            (new Obfuscator(new Options(seed: 1)))->obfuscateFileTo($src, $src . DIRECTORY_SEPARATOR . 'nope.php');
        } finally {
            @unlink($src);
        }
    }

    public function testEmptyInputReturnsEmptyString(): void
    {
        $this->assertSame('', (new Obfuscator())->obfuscate(''));
        $this->assertSame('', (new Obfuscator())->obfuscate("   \n\t"));
    }

    public function testInvalidPhpThrows(): void
    {
        $this->expectException(ObfuscationException::class);
        $this->expectExceptionMessage('Failed to parse PHP code');
        (new Obfuscator())->obfuscate('<?php class { ');
    }

    public function testCodeWithoutOpenTagIsHandled(): void
    {
        $code = '$x = 1; echo $x + 1;';
        $output = (new Obfuscator(new Options(wrapWithEval: false, seed: 1)))->obfuscate($code);
        // The leading <?php we injected for tokenisation must be stripped.
        $this->assertStringStartsNotWith('<?php', $output);
        // Wrap manually for execution.
        $this->assertSame('2', PhpRunner::run("<?php $output"));
    }

    public function testWrapWithEvalProducesEvalCall(): void
    {
        $code = '<?php echo 42;';
        $output = (new Obfuscator(new Options(seed: 7)))->obfuscate($code);
        $this->assertStringContainsString('eval(base64_decode(', $output);
        $this->assertSame('42', PhpRunner::run($output));
    }

    public function testGetNameMapReturnsAllKinds(): void
    {
        $code = <<<'PHP'
        <?php
        const GLOBAL_K = 1;
        function helper($x) { return $x; }
        class Box {
            public int $value = 0;
            public function set(int $v): void { $this->value = $v; }
        }
        $b = new Box();
        helper($b);
        PHP;

        $obfuscator = new Obfuscator(new Options(seed: 12));
        $obfuscator->obfuscate($code);
        $map = $obfuscator->getNameMap();

        $this->assertArrayHasKey(SymbolTable::KIND_VARIABLE, $map);
        $this->assertArrayHasKey('helper', $map[SymbolTable::KIND_FUNCTION]);
        $this->assertArrayHasKey('Box', $map[SymbolTable::KIND_CLASS]);
        $this->assertArrayHasKey('set', $map[SymbolTable::KIND_METHOD]);
        $this->assertArrayHasKey('value', $map[SymbolTable::KIND_PROPERTY]);
        $this->assertArrayHasKey('GLOBAL_K', $map[SymbolTable::KIND_CONSTANT]);
        $this->assertNotNull($map[SymbolTable::KIND_FUNCTION]['helper']);
    }

    public function testSelectiveObfuscationKeepsDisabledKindsIntact(): void
    {
        $code = <<<'PHP'
        <?php
        function publicApi($x) { return $x * 2; }
        echo publicApi(21);
        PHP;

        $options = new Options(
            obfuscateFunctions: false,
            wrapWithEval: false,
            removeWhitespace: false,
            seed: 99,
        );
        $output = (new Obfuscator($options))->obfuscate($code);

        $this->assertStringContainsString('function publicApi(', $output);
        $this->assertStringContainsString('publicApi(21)', $output);
        $this->assertSame('42', PhpRunner::run($output));
    }

    public function testStringEncodingDoesNotTouchVeryShortLiterals(): void
    {
        $code = '<?php $a = ""; $b = "a"; $c = "ab"; echo $a . $b . $c;';
        $output = (new Obfuscator(new Options(wrapWithEval: false, removeWhitespace: false, seed: 1)))->obfuscate($code);
        $this->assertSame('aab', PhpRunner::run($output));
        // Empty and 1-char strings are not worth encoding; the literal "ab"
        // should still be base64-encoded.
        $this->assertStringContainsString('base64_decode(', $output);
    }

    public function testSeedMakesOutputDeterministic(): void
    {
        $code = '<?php $foo = 1; $bar = 2; echo $foo + $bar;';
        $a = (new Obfuscator(new Options(seed: 42)))->obfuscate($code);
        $b = (new Obfuscator(new Options(seed: 42)))->obfuscate($code);
        $this->assertSame($a, $b);

        $c = (new Obfuscator(new Options(seed: 43)))->obfuscate($code);
        $this->assertNotSame($a, $c);
    }

    public function testCommentsArePreservedWhenAsked(): void
    {
        $code = "<?php\n// keep me\nfunction f() { return 1; }\necho f();";
        $options = new Options(
            removeComments: false,
            removeWhitespace: false,
            wrapWithEval: false,
            seed: 1,
        );
        $output = (new Obfuscator($options))->obfuscate($code);
        $this->assertStringContainsString('// keep me', $output);
        $this->assertSame('1', PhpRunner::run($output));
    }

    public function testCommentsRemovedWithoutMinification(): void
    {
        $code = "<?php\n/** doc */\nfunction f() { /* inner */ return 1; }\necho f();";
        $options = new Options(
            removeComments: true,
            removeWhitespace: false,
            wrapWithEval: false,
            seed: 1,
        );
        $output = (new Obfuscator($options))->obfuscate($code);
        $this->assertStringNotContainsString('/** doc */', $output);
        $this->assertStringNotContainsString('/* inner */', $output);
        $this->assertSame('1', PhpRunner::run($output));
    }

    public function testObfuscateFileReadsFromDisk(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pob-in-');
        file_put_contents($tmp, "<?php echo 'disk';");
        try {
            $obfuscator = new Obfuscator(new Options(wrapWithEval: false, seed: 1));
            $output = $obfuscator->obfuscateFile($tmp);
            $this->assertSame('disk', PhpRunner::run($output));
        } finally {
            @unlink($tmp);
        }
    }

    public function testObfuscateFileThrowsWhenMissing(): void
    {
        $this->expectException(ObfuscationException::class);
        $this->expectExceptionMessage('File not found');
        (new Obfuscator())->obfuscateFile(__DIR__ . '/__nope__.php');
    }

    public function testObfuscateFileToWritesTargetIncludingMissingDirectory(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'pob-in-');
        $dstDir = sys_get_temp_dir() . '/pob-' . uniqid('', true) . '/nested';
        $dst = $dstDir . '/out.php';
        file_put_contents($src, '<?php echo 1+2;');

        try {
            $obfuscator = new Obfuscator(new Options(seed: 1));
            $obfuscator->obfuscateFileTo($src, $dst);
            $this->assertFileExists($dst);
            $this->assertSame('3', PhpRunner::run(file_get_contents($dst)));
        } finally {
            @unlink($src);
            @unlink($dst);
            @rmdir($dstDir);
            @rmdir(dirname($dstDir));
        }
    }

    public function testCustomNameGeneratorIsRespected(): void
    {
        $generator = new NameGenerator(8, 8, 1234);
        $obfuscator = new Obfuscator(new Options(wrapWithEval: false, removeWhitespace: false), $generator);
        $output = $obfuscator->obfuscate('<?php $alpha = 10; echo $alpha;');
        $this->assertMatchesRegularExpression('/\$v[A-Za-z]{8}\s*=\s*10/', $output);
    }

    public function testOptionsAccessor(): void
    {
        $opts = new Options(seed: 5);
        $obfuscator = new Obfuscator($opts);
        $this->assertSame($opts, $obfuscator->getOptions());
    }

    public function testInterpolatedStringPreservesBraces(): void
    {
        $code = '<?php $name = "Ada"; echo "Hi {$name}!";';
        $options = new Options(encodeStrings: false, wrapWithEval: false, seed: 1);
        $output = (new Obfuscator($options))->obfuscate($code);
        $this->assertSame('Hi Ada!', PhpRunner::run($output));
    }

    public function testTraitMembersAreRenamedConsistently(): void
    {
        $code = <<<'PHP'
        <?php
        trait Greeter {
            public function hello(): string { return "hi"; }
        }
        class Thing {
            use Greeter;
        }
        echo (new Thing())->hello();
        PHP;

        $output = (new Obfuscator(new Options(seed: 5)))->obfuscate($code);
        $this->assertSame('hi', PhpRunner::run($output));
    }
}
