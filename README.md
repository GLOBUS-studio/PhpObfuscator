# PhpObfuscator

[![CI](https://github.com/GLOBUS-studio/PhpObfuscator/actions/workflows/ci.yml/badge.svg)](https://github.com/GLOBUS-studio/PhpObfuscator/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/globus-studio/php-obfuscator.svg?label=version)](https://packagist.org/packages/globus-studio/php-obfuscator)
[![PHP Version](https://img.shields.io/packagist/php-v/globus-studio/php-obfuscator.svg)](https://www.php.net/)
[![License](https://img.shields.io/github/license/GLOBUS-studio/PhpObfuscator.svg)](LICENSE)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-passing-brightgreen.svg)](https://phpunit.de/)
[![Tested on PHP 8.1 - 8.5](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-blue.svg)](.github/workflows/ci.yml)

A token-based PHP source code obfuscator. Renames identifiers, encodes string
literals and minifies whitespace, all driven by PHP's own tokenizer rather than
brittle regular expressions, so the transformed code keeps its original
runtime semantics on every supported PHP version.

The obfuscator is fully tested on PHP 8.1, 8.2, 8.3, 8.4 and 8.5 in CI.

## Why another obfuscator?

Most PHP obfuscators on the open web rely on regular expressions. They break
on namespaced code, on complex string interpolation, on constructor property
promotion and on anything that does not fit a simple textual pattern. This
library walks the official `PhpToken` stream and rewrites identifiers using
the same lexical context the engine itself uses, which makes the result
predictable and safe to ship.

## Features

- Token-based identifier rewriting (variables, functions, classes,
  interfaces, traits, enums, methods, properties, class and global
  constants).
- Constructor property promotion, `readonly`, `enum`, `match`, arrow
  functions, nullsafe operator, named arguments and attributes are all
  recognised.
- Optional Base64 encoding of string literals (skipped automatically inside
  constant expressions where PHP requires literal values).
- Optional comment stripping and whitespace minification driven by a second
  tokenizer pass, so the output is guaranteed to remain parseable.
- Optional `eval(base64_decode(...))` wrapping for an extra layer of
  protection.
- Deterministic PRNG seed for reproducible builds.
- Inspection of the rename table via `getNameMap()`.
- Bundled `php-obfuscate` CLI binary.

## Installation

```bash
composer require globus-studio/php-obfuscator
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use GLOBUSstudio\PhpObfuscator\Obfuscator;
use GLOBUSstudio\PhpObfuscator\Options;

$obfuscator = new Obfuscator();

echo $obfuscator->obfuscate(<<<'PHP'
<?php
function greet(string $name): string {
    return "Hello, $name";
}
echo greet('Ada');
PHP);
```

The output is a self-contained `<?php eval(base64_decode("..."));` payload that
prints `Hello, Ada` when executed.

## Library API

### `Obfuscator`

```php
$obfuscator = new Obfuscator(new Options(seed: 42));

$code = $obfuscator->obfuscate('<?php echo 1 + 1;');
$code = $obfuscator->obfuscateFile(__DIR__ . '/src/in.php');
$obfuscator->obfuscateFileTo(__DIR__ . '/src/in.php', __DIR__ . '/build/out.php');

$map = $obfuscator->getNameMap();
// [
//     'variables'  => ['userName' => 'vAbCdEfG', ...],
//     'functions'  => [...],
//     'classes'    => [...],
//     'methods'    => [...],
//     'properties' => [...],
//     'constants'  => [...],
// ]
```

### `Options`

`Options` is an immutable value object. All flags default to `true`.

| Option                | Default | Description                                                                       |
|-----------------------|--------:|-----------------------------------------------------------------------------------|
| `obfuscateVariables`  |  `true` | Rename local variables (superglobals and `$this` are always preserved).           |
| `obfuscateFunctions`  |  `true` | Rename user-defined function declarations and their call sites.                   |
| `obfuscateClasses`    |  `true` | Rename classes, interfaces, traits and enums and every reference to them.        |
| `obfuscateMethods`    |  `true` | Rename methods (magic methods such as `__construct` are kept untouched).         |
| `obfuscateProperties` |  `true` | Rename properties, including promoted constructor parameters and static props.   |
| `obfuscateConstants`  |  `true` | Rename `const`, class constants, enum cases and `define()` constants.            |
| `encodeStrings`       |  `true` | Replace string literals with `base64_decode('...')` calls.                       |
| `removeComments`      |  `true` | Strip `//`, `#` and `/* ... */` comments.                                        |
| `removeWhitespace`    |  `true` | Minify whitespace.                                                                |
| `wrapWithEval`        |  `true` | Wrap the final payload in `<?php eval(base64_decode('...'));`.                   |
| `minNameLength`       |     `6` | Minimum length of generated identifiers.                                          |
| `maxNameLength`       |    `12` | Maximum length of generated identifiers.                                          |
| `seed`                |  `null` | Optional PRNG seed for deterministic, reproducible output.                        |

```php
use GLOBUSstudio\PhpObfuscator\Options;

$options = new Options(
    encodeStrings: false,
    wrapWithEval: false,
    seed: 12345,
);

$options = Options::fromArray(['wrapWithEval' => false]);
$options = $options->with(['seed' => 7]);
```

### Selective obfuscation

```php
use GLOBUSstudio\PhpObfuscator\Obfuscator;
use GLOBUSstudio\PhpObfuscator\Options;

// Public API stays callable from the outside while the internals are scrambled.
$opts = new Options(
    obfuscateClasses:  false,
    obfuscateMethods:  false,
    obfuscateFunctions: false,
);

echo (new Obfuscator($opts))->obfuscate(file_get_contents('Library.php'));
```

## CLI

```text
Usage: php-obfuscate [options] <input.php> [output.php]

Reads PHP source from <input.php> (or `-` for STDIN), obfuscates it and writes
the result to <output.php> (or STDOUT when omitted).

Options:
  --no-eval-wrap         Do not wrap output in eval(base64_decode(...))
  --no-strings           Skip string literal Base64 encoding
  --no-minify            Preserve original whitespace
  --no-comments          Strip comments only (default removes them)
  --keep-variables       Do not rename variables
  --keep-functions       Do not rename functions
  --keep-classes         Do not rename classes
  --keep-methods         Do not rename methods
  --keep-properties      Do not rename properties
  --keep-constants       Do not rename constants
  --seed=<int>           Use a deterministic PRNG seed
  -h, --help             Show this help text
  -V, --version          Print library version
```

Examples:

```bash
vendor/bin/php-obfuscate src/Acme/Service.php build/Service.php
cat src/Acme/Service.php | vendor/bin/php-obfuscate - > build/Service.php
vendor/bin/php-obfuscate --keep-classes --seed=1 src/Service.php
```

## What is preserved by design

- `$this` and every PHP superglobal (`$_SERVER`, `$_GET`, ...).
- Magic methods (`__construct`, `__toString`, `__invoke`, ...).
- Built-in PHP functions, classes, traits and interfaces. The obfuscator only
  renames identifiers it has previously seen *declared* in the input, so
  references to anything from the standard library or third-party packages
  remain untouched.
- String literals appearing in constant-expression positions (class
  constants, enum case values, default parameter values, top-level `const`
  initializers) are never wrapped in `base64_decode(...)`, because PHP
  requires those positions to be literal expressions.

## Limitations

- Namespaced symbol names that appear as `T_NAME_QUALIFIED` or
  `T_NAME_FULLY_QUALIFIED` tokens are not rewritten. If you need to obfuscate
  namespaced classes, the obfuscator must be applied to a single-namespace
  file at a time.
- The obfuscator works at the source level. It is a deterrent that raises
  the cost of casual reverse engineering, not a cryptographic protection.
  Anyone able to run your code can trivially recover the runtime behaviour.

## Development

```bash
composer install
vendor/bin/phpunit
```

The bundled CI workflow runs the test suite on Ubuntu against PHP 8.1 - 8.5.

## License

[MIT](LICENSE).
