# AGENTS.md

## Project overview

`globus-studio/php-obfuscator` — a **token-based** PHP source code obfuscator that rewrites identifiers, encodes string literals, and minifies whitespace. Powered entirely by `PhpToken::tokenize()` — no regex-based parsing. The transformed code preserves original runtime semantics across PHP 8.1–8.5.

**Zero production dependencies** beyond `php ^8.1` + `ext-tokenizer`. PHPUnit is the sole dev dependency.

## Repository structure

```
bin/php-obfuscate          CLI entry point
src/
  Obfuscator.php            Core engine (899 lines, single-class pipeline)
  Options.php               Immutable config value object (13 props, all default true)
  NameGenerator.php         PRNG identifier generator (crypto or seeded LCG)
  SymbolTable.php           6-kind bidirectional name map
  Exception/
    ObfuscationException.php  extends RuntimeException
tests/
  ObfuscatorTest.php        Main test suite (16-snippet data provider, 3 modes each)
  OptionsTest.php
  NameGeneratorTest.php
  SymbolTableTest.php
  ExceptionTest.php
  Support/PhpRunner.php     Spawns child PHP process for runtime-output verification
examples/demo.php           End-to-end usage demo
.github/workflows/ci.yml    PHP 8.1–8.5 matrix, Ubuntu, phpunit
```

## Commands

```bash
composer test           # PHPUnit (random order, strict mode)
composer test-coverage  # PHPUnit + HTML coverage → build/coverage/
composer validate       # composer.json validation (also run in CI)
vendor/bin/phpunit --colors=always --no-coverage   # CI variant
```

The CLI binary: `vendor/bin/php-obfuscate [options] <input.php> [output.php]`.

There is **no lint/static-analysis step** configured. If you add one, document it here.

## Architecture

### The pipeline (private methods in `Obfuscator::obfuscate()`)

```
1. PhpToken::tokenize()           ← TOKEN_PARSE flag, full parse validation
2. matchBracketPairs()            ← `()`, `[]`, `{}`; ignores interpolation braces
3. computeBraceRoles()            ← labels each `{` as class/function/block body
4. collectDeclarations()          ← walks tokens, populates SymbolTable, annotates each token index with {kind, name}
5. assignNames()                  ← NameGenerator produces new names → SymbolTable.setRename()
6. rewrite()                      ← re-emits token stream with renamed IDs + encoded strings
7. postProcess()                  ← re-tokenizes + re-emits with comment/WS options applied
8. wrapWithEval (optional)        ← `<?php eval(base64_decode("..."));`
```

### SymbolTable — 6 kinds

`KIND_VARIABLE`, `KIND_FUNCTION`, `KIND_CLASS`, `KIND_METHOD`, `KIND_PROPERTY`, `KIND_CONSTANT`. Each holds `array<originalName, obfuscatedName|null>`. Null means "declared but obfuscation disabled for this kind."

### NameGenerator — PRNG

- **No seed**: delegates to `random_int()` (cryptographically secure).
- **With seed**: Numerical Recipes LCG (`seed * 1664525 + 1013904223 & 0x7FFFFFFF`) — deterministic, bit-for-bit reproducible across PHP versions.
- Alphabet: `a-z A-Z` only (valid PHP identifier guarantees).
- Prefix per kind: `v`, `f`, `C`, `m`, `p`, `k`.
- Falls back up to 10,000 attempts on collision.

### What is NEVER renamed

- Superglobals + `$this` (`RESERVED_VARIABLES`)
- Magic methods (`__construct`, `__toString`, etc. — `RESERVED_METHODS`)
- Keywords in type positions: `self`, `static`, `parent`, `true`, `false`, `null` (`RESERVED_TYPE_NAMES`)
- Built-in PHP functions/classes — only identifiers **seen declared in the input** are renamed

### String encoding rules

`maybeEncodeStringLiteral()` wraps string literals in `base64_decode('...')`. Skips:
- Literals shorter than 4 characters
- Literals in constant-expression positions: class property defaults, enum case values, default parameter values, top-level `const` initializers, `define()` arg
- Literals inside `define()` calls where the string IS the constant name (rewritten instead)

### Member access: method vs property disambiguation

`renameMemberAccess()` checks whether the next significant token is `(` to decide method vs property. For `::` without `(`, treats as constant.

### Bare T_STRING (unannotated) disambiguation

`renameBareString()` uses surrounding context:
- `new Foo(...)`, `extends Foo`, `implements Foo`, `instanceof Foo`, `Foo::...` → class
- `Foo(...)` but NOT after `new` → function
- Otherwise → tries class first, then constant

### Constructor property promotion

`isPromotedProperty()` scans backward from a variable inside a method param list for `public`/`private`/`protected`/`readonly` keywords, bounded by `(` or `,`.

### Comment stripping + whitespace minification

`postProcess()` re-tokenizes output and:
1. Strips `T_COMMENT` + `T_DOC_COMMENT` (controlled by `removeComments`)
2. Strips `T_WHITESPACE` (controlled by `removeWhitespace`)
3. Inserts a single space when stripping would fuse two word-char tokens (e.g. `functionfoo` → `function foo`)

### Options — immutable value object

All 13 constructor properties are `public readonly` with defaults true. Factory methods:
- `Options::fromArray(array)` — builds from associative array, rejects unknown keys
- `$options->with(array)` — returns new instance with overrides

### CLI binary (`bin/php-obfuscate`)

Parses flags → builds Options → calls Obfuscator. Input from file or STDIN (`-`). Output to file or STDOUT. Auto-creates output directories.

### Tests — key patterns

- **`ObfuscatorTest::snippetsProvider()`**: 16 PHP snippets exercising all language features (variables, functions, recursion, classes/properties/methods, statics, enums+match, promoted properties, `define()`/`const`, arrow functions, nullsafe, inheritance, docblocks, closures+`use`, instanceof, default params, top-level const).
- **Three obfuscation modes per snippet**: default (full eval-wrap+minify), plain (no eval, no strings, no minify), minified-no-eval.
- **Correctness guarantee**: each obfuscated snippet is executed via `PhpRunner` (spawns `PHP_BINARY` child process) and output is compared to the original — must match exactly.
- **Determinism test**: same seed → identical output; different seed → different output.

## Code conventions

- `declare(strict_types=1)` in every file
- PHP 8.1 minimum — uses named arguments, readonly properties, match, enums, nullsafe
- Class layout: public API first, then private internal pipeline, then token helper methods
- No docblocks on private methods unless behavior is non-obvious
- Use `self::` for class constants within the same class
- Array shapes in `@var` docblocks for complex arrays (e.g. `@var array<int, array{kind:string, name:string}>`)
- Throw `ObfuscationException` for operational failures, `\InvalidArgumentException` for invalid arguments, `\RuntimeException` for unrecoverable internal state

## CI

GitHub Actions, triggers on push/PR to `main`. PHP matrix 8.1–8.5 on `ubuntu-latest`. Installs tokenizer+mbstring+xdebug, `composer validate --strict`, `vendor/bin/phpunit --no-coverage`.
