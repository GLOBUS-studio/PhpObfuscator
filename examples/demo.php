<?php

/**
 * Minimal end-to-end demonstration of the PhpObfuscator library.
 *
 * Run from the project root:
 *
 *     composer install
 *     php examples/demo.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use GLOBUSstudio\PhpObfuscator\Obfuscator;
use GLOBUSstudio\PhpObfuscator\Options;

$source = <<<'PHP'
<?php
final class Greeter {
    public function __construct(private readonly string $prefix) {}
    public function greet(string $name): string {
        return $this->prefix . ', ' . $name . '!';
    }
}

$g = new Greeter('Hello');
echo $g->greet('Ada');
PHP;

echo "----- ORIGINAL -----\n";
echo $source, "\n\n";

echo "----- OBFUSCATED (default options) -----\n";
echo (new Obfuscator(new Options(seed: 1)))->obfuscate($source), "\n\n";

echo "----- OBFUSCATED (no eval wrap, no minify, no string encoding) -----\n";
echo (new Obfuscator(new Options(
    encodeStrings: false,
    removeWhitespace: false,
    wrapWithEval: false,
    seed: 1,
)))->obfuscate($source), "\n";
