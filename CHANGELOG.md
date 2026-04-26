# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-04-27

### Added
- Initial public release.
- Token-based obfuscation engine using `PhpToken` (no fragile regex passes).
- Configurable rename of variables, functions, classes, interfaces, traits, enums,
  methods, properties, class and global constants.
- Optional Base64 encoding of constant string literals.
- Optional comment stripping and whitespace minification.
- Optional eval/Base64 wrapping of the final payload.
- Deterministic mode through a configurable PRNG seed.
- Public `getNameMap()` API for inspecting the rename table.
- CLI entry point `bin/php-obfuscate`.
- Test suite covering 100% of the source code.
- GitHub Actions CI matrix for PHP 8.1, 8.2, 8.3, 8.4 and 8.5.
