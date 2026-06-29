# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-06-29

### Changed
- Reduced CI test matrix to PHP 8.3–8.5 (matching the `^8.3` requirement).

### Fixed
- Aligned documentation, badge, and inline docblocks to the `^8.3` minimum PHP requirement.

## [1.0.1] - 2026-06-29

### Changed
- Raised minimum PHP requirement to `^8.3` and added the `ext-mbstring` dependency.
- Corrected the `--no-comments` CLI help text.

### Fixed
- Preserve leading inline HTML and support `<?=` short echo tags when eval-wrapping mixed HTML/PHP templates.
- Robust parsing fallback when the source already contains an opening tag.
- Custom double-quoted escape decoding (`\e`, `\u{...}`, `\x`, octal, unknown escapes) matching PHP runtime semantics instead of `stripcslashes()`.
- Handle attributes (`#[...]`) in bracket matching and skip string encoding inside them.
- Distinguish enum bodies from classes: only rename real enum cases, not `switch` labels.
- Support multi-declarator and typed class/global constants (`const A = 1, B = 2;`, `const int FOO = 1;`).
- Skip `use function` / `use const` imports instead of treating them as declarations.
- Leave variable-variables (`$$var`) and the implicit `$value` in property set-hooks untouched.
- Correct constructor promoted-property detection with nested parentheses.
- Do not rename bare identifiers used as literal keys inside string interpolation.
- Insert a separating space to avoid fusing adjacent `--` / `++` operators during minification.

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
