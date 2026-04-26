<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator;

use GLOBUSstudio\PhpObfuscator\Exception\ObfuscationException;
use PhpToken;

/**
 * Token-based PHP source code obfuscator.
 *
 * The obfuscator parses input through PHP's own tokenizer and rewrites
 * identifiers it has previously seen declared in the input. Built-in
 * functions, classes, traits and superglobals are therefore guaranteed to be
 * left untouched, which keeps the obfuscated payload runnable on every
 * supported PHP version (8.1 - 8.5).
 */
final class Obfuscator
{
    /**
     * Variable names that must always remain intact: the magic `$this`
     * pseudo-variable and the full list of PHP superglobals.
     */
    private const RESERVED_VARIABLES = [
        'this' => true,
        'GLOBALS' => true,
        '_SERVER' => true,
        '_GET' => true,
        '_POST' => true,
        '_FILES' => true,
        '_COOKIE' => true,
        '_SESSION' => true,
        '_REQUEST' => true,
        '_ENV' => true,
        'http_response_header' => true,
        'argc' => true,
        'argv' => true,
        'php_errormsg' => true,
    ];

    /**
     * Method names that PHP treats specially. Renaming them would break
     * runtime semantics.
     */
    private const RESERVED_METHODS = [
        '__construct' => true,
        '__destruct' => true,
        '__call' => true,
        '__callStatic' => true,
        '__get' => true,
        '__set' => true,
        '__isset' => true,
        '__unset' => true,
        '__sleep' => true,
        '__wakeup' => true,
        '__serialize' => true,
        '__unserialize' => true,
        '__toString' => true,
        '__invoke' => true,
        '__set_state' => true,
        '__clone' => true,
        '__debugInfo' => true,
    ];

    /**
     * PHP keywords that may legally appear after the `class`/`new`/`extends`
     * keyword as a name and must therefore never be obfuscated as classes.
     */
    private const RESERVED_TYPE_NAMES = [
        'self' => true,
        'static' => true,
        'parent' => true,
        'true' => true,
        'false' => true,
        'null' => true,
    ];

    private Options $options;
    private NameGenerator $names;
    private SymbolTable $symbols;

    public function __construct(?Options $options = null, ?NameGenerator $names = null)
    {
        $this->options = $options ?? new Options();
        $this->names = $names ?? new NameGenerator(
            $this->options->minNameLength,
            $this->options->maxNameLength,
            $this->options->seed,
        );
        $this->symbols = new SymbolTable();
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public function getNameMap(): array
    {
        return $this->symbols->all();
    }

    /**
     * Obfuscate a PHP source string and return the new source.
     */
    public function obfuscate(string $code): string
    {
        if (trim($code) === '') {
            return '';
        }

        $hadOpenTag = (bool) preg_match('/<\?(php|=)?/', $code);
        $sourceForTokens = $hadOpenTag ? $code : "<?php\n" . $code;

        try {
            $tokens = PhpToken::tokenize($sourceForTokens, TOKEN_PARSE);
        } catch (\ParseError $e) {
            throw new ObfuscationException(
                'Failed to parse PHP code: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        // Reset the symbol table for every call so the obfuscator instance is
        // safely reusable.
        $this->symbols = new SymbolTable();

        $pairs       = $this->matchBracketPairs($tokens);
        $braceRoles  = $this->computeBraceRoles($tokens, $pairs);
        $annotations = $this->collectDeclarations($tokens, $braceRoles, $pairs);

        $this->assignNames();

        $output = $this->rewrite($tokens, $annotations);

        if ($this->options->removeComments || $this->options->removeWhitespace) {
            $output = $this->postProcess($output);
        }

        if ($this->options->wrapWithEval) {
            $body = preg_replace('/^\s*<\?(php\s*|=\s*)?/', '', $output, 1) ?? $output;
            $body = preg_replace('/\?>\s*$/', '', $body) ?? $body;
            $output = '<?php eval(base64_decode("' . base64_encode($body) . '"));';
        }

        if (!$hadOpenTag) {
            $output = preg_replace('/^\s*<\?(php\s*|=\s*)?/', '', $output, 1) ?? $output;
        }

        return $output;
    }

    /**
     * Read a file from disk, obfuscate its contents and return the result.
     */
    public function obfuscateFile(string $path): string
    {
        if (!is_file($path)) {
            throw new ObfuscationException("File not found: $path");
        }
        $code = @file_get_contents($path);
        if ($code === false) {
            throw new ObfuscationException("Failed to read file: $path");
        }
        return $this->obfuscate($code);
    }

    /**
     * Obfuscate a file and write the result to a target path. The target
     * directory is created automatically when missing.
     */
    public function obfuscateFileTo(string $source, string $target): void
    {
        $output = $this->obfuscateFile($source);

        $dir = dirname($target);
        if ($dir !== '' && !is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new ObfuscationException("Failed to create directory: $dir");
        }

        if (@file_put_contents($target, $output) === false) {
            throw new ObfuscationException("Failed to write file: $target");
        }
    }

    // ------------------------------------------------------------------
    //  Internal pipeline
    // ------------------------------------------------------------------

    /**
     * Match `()`, `[]` and `{}` pairs while ignoring opening braces that
     * belong to interpolation (`{$var}` and `${var}`) inside strings.
     *
     * @param PhpToken[] $tokens
     * @return array<int, int>  position of opener mapped to position of closer (and vice versa)
     */
    private function matchBracketPairs(array $tokens): array
    {
        $pairs = [];
        $stack = [];
        $interpDepth = 0;

        foreach ($tokens as $i => $t) {
            $id   = $t->id;
            $text = $t->text;

            if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $interpDepth++;
                continue;
            }

            if ($text === '}' && $id === ord('}')) {
                if ($interpDepth > 0) {
                    $interpDepth--;
                    continue;
                }
                if ($stack !== []) {
                    $open = array_pop($stack);
                    $pairs[$open] = $i;
                    $pairs[$i] = $open;
                }
                continue;
            }

            if ($id === ord('{') || $id === ord('(') || $id === ord('[')) {
                $stack[] = $i;
                continue;
            }

            if ($id === ord(')') || $id === ord(']')) {
                if ($stack !== []) {
                    $open = array_pop($stack);
                    $pairs[$open] = $i;
                    $pairs[$i] = $open;
                }
            }
        }

        return $pairs;
    }

    /**
     * For every `{` token decide whether it opens a class body, a function
     * body or a generic block. The result is keyed by token index.
     *
     * @param PhpToken[]        $tokens
     * @param array<int, int>   $pairs
     * @return array<int, string>
     */
    private function computeBraceRoles(array $tokens, array $pairs): array
    {
        $roles = [];
        $count = count($tokens);

        foreach ($tokens as $i => $t) {
            if ($t->id === T_CLASS || $t->id === T_INTERFACE || $t->id === T_TRAIT || $t->id === T_ENUM) {
                $brace = $this->findNextRealBrace($tokens, $i + 1);
                if ($brace !== null) {
                    $roles[$brace] = 'class';
                }
                continue;
            }

            if ($t->id === T_FUNCTION) {
                // Locate `(` at the start of the parameter list.
                $j = $this->skipTrivia($tokens, $i + 1);
                if ($j < $count && $tokens[$j]->text === '&') {
                    $j = $this->skipTrivia($tokens, $j + 1);
                }
                if ($j < $count && $tokens[$j]->id === T_STRING) {
                    $j = $this->skipTrivia($tokens, $j + 1);
                }
                if ($j < $count && $tokens[$j]->id === ord('(') && isset($pairs[$j])) {
                    $closeParen = $pairs[$j];
                    $brace = $this->findNextRealBrace($tokens, $closeParen + 1);
                    if ($brace !== null) {
                        $roles[$brace] = 'function';
                    }
                }
            }
        }

        return $roles;
    }

    /**
     * Walk the token stream and record every user-declared identifier in the
     * symbol table. The returned array maps token index to per-token
     * annotations consumed by the rewrite phase.
     *
     * @param PhpToken[]            $tokens
     * @param array<int, string>    $braceRoles
     * @param array<int, int>       $pairs
     * @return array<int, array{kind:string, name:string}>
     */
    private function collectDeclarations(array $tokens, array $braceRoles, array $pairs): array
    {
        /** @var array<int, array{kind:string, name:string}> $annotations */
        $annotations = [];
        /** @var list<string> $contextStack */
        $contextStack = [];
        /** @var list<string> $parenStack */
        $parenStack   = [];
        $parenDepth   = 0;
        $interpDepth  = 0;
        $nextParenIsFuncParams = false;
        $globalConstExpr       = false;
        $count        = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];

            // Track interpolation so braces inside strings do not mess up the
            // brace stack.
            if ($t->id === T_CURLY_OPEN || $t->id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $interpDepth++;
                continue;
            }
            if ($t->text === '}' && $t->id === ord('}')) {
                if ($interpDepth > 0) {
                    $interpDepth--;
                    continue;
                }
                array_pop($contextStack);
                continue;
            }
            if ($t->id === ord('{')) {
                $contextStack[] = $braceRoles[$i] ?? 'block';
                continue;
            }
            if ($t->id === ord('(')) {
                $parenDepth++;
                $parenStack[] = $nextParenIsFuncParams ? 'funcParams' : 'other';
                $nextParenIsFuncParams = false;
                continue;
            }
            if ($t->id === ord(')')) {
                if ($parenDepth > 0) {
                    $parenDepth--;
                }
                array_pop($parenStack);
                continue;
            }
            if ($t->id === ord(';')) {
                $globalConstExpr = false;
                continue;
            }

            $inClassBody = $contextStack !== [] && end($contextStack) === 'class';

            // Mark constant string literals appearing in constant-expression
            // contexts so the rewriter does not replace them with a runtime
            // function call.
            if ($t->id === T_CONSTANT_ENCAPSED_STRING) {
                $noEncode = false;
                if ($inClassBody && $parenDepth === 0) {
                    $noEncode = true;
                } elseif ($parenStack !== [] && end($parenStack) === 'funcParams') {
                    $noEncode = true;
                } elseif ($globalConstExpr) {
                    $noEncode = true;
                }
                if ($noEncode) {
                    $annotations[$i] = ($annotations[$i] ?? []) + ['kind' => 'literal_no_encode', 'name' => ''];
                }
                // fall through to normal processing below; string literal
                // handling for define() is done when we see the T_STRING.
            }

            if ($t->id === T_FUNCTION || $t->id === T_FN) {
                $nextParenIsFuncParams = true;
            }

            if ($t->id === T_CONST) {
                $globalConstExpr = true;
            }

            if ($t->id === T_VARIABLE) {
                $name = ltrim($t->text, '$');
                if (isset(self::RESERVED_VARIABLES[$name])) {
                    continue;
                }

                // `Foo::$bar` and `$obj::$bar` are static property accesses
                // and must use the property map, not the variable map.
                $prevIdx = $this->prevSignificant($tokens, $i);
                if ($prevIdx !== null && $tokens[$prevIdx]->id === T_DOUBLE_COLON) {
                    $this->symbols->declare(SymbolTable::KIND_PROPERTY, $name);
                    $annotations[$i] = ['kind' => SymbolTable::KIND_PROPERTY, 'name' => $name];
                    continue;
                }

                $isProperty = false;
                if ($inClassBody) {
                    if ($parenDepth === 0) {
                        $isProperty = true;
                    } elseif ($this->isPromotedProperty($tokens, $i)) {
                        $isProperty = true;
                    }
                }

                if ($isProperty) {
                    $this->symbols->declare(SymbolTable::KIND_PROPERTY, $name);
                    $annotations[$i] = ['kind' => SymbolTable::KIND_PROPERTY, 'name' => $name];
                } else {
                    $this->symbols->declare(SymbolTable::KIND_VARIABLE, $name);
                    $annotations[$i] = ['kind' => SymbolTable::KIND_VARIABLE, 'name' => $name];
                }
                continue;
            }

            if ($t->id === T_STRING) {
                // Determine context by looking at the previous and next
                // significant tokens.
                $prevIdx = $this->prevSignificant($tokens, $i);
                $nextIdx = $this->nextSignificant($tokens, $i);
                $prev    = $prevIdx !== null ? $tokens[$prevIdx] : null;
                $next    = $nextIdx !== null ? $tokens[$nextIdx] : null;

                // Member access: do not treat as a declaration.
                if ($prev !== null && (
                    $prev->id === T_OBJECT_OPERATOR
                    || $prev->id === T_NULLSAFE_OBJECT_OPERATOR
                    || $prev->id === T_DOUBLE_COLON
                )) {
                    $annotations[$i] = ['kind' => 'member_access', 'name' => $t->text];
                    continue;
                }

                // `function NAME(`
                if ($prev !== null && $prev->id === T_FUNCTION) {
                    if ($inClassBody) {
                        if (!isset(self::RESERVED_METHODS[$t->text])) {
                            $this->symbols->declare(SymbolTable::KIND_METHOD, $t->text);
                            $annotations[$i] = ['kind' => SymbolTable::KIND_METHOD, 'name' => $t->text];
                        }
                    } else {
                        $this->symbols->declare(SymbolTable::KIND_FUNCTION, $t->text);
                        $annotations[$i] = ['kind' => SymbolTable::KIND_FUNCTION, 'name' => $t->text];
                    }
                    continue;
                }

                // `class NAME` and friends
                if ($prev !== null && (
                    $prev->id === T_CLASS
                    || $prev->id === T_INTERFACE
                    || $prev->id === T_TRAIT
                    || $prev->id === T_ENUM
                )) {
                    if (!isset(self::RESERVED_TYPE_NAMES[strtolower($t->text)])) {
                        $this->symbols->declare(SymbolTable::KIND_CLASS, $t->text);
                        $annotations[$i] = ['kind' => SymbolTable::KIND_CLASS, 'name' => $t->text];
                    }
                    continue;
                }

                // `const NAME =` and `case NAME` (enum)
                if ($prev !== null && ($prev->id === T_CONST || $prev->id === T_CASE)) {
                    $this->symbols->declare(SymbolTable::KIND_CONSTANT, $t->text);
                    $annotations[$i] = ['kind' => SymbolTable::KIND_CONSTANT, 'name' => $t->text];
                    continue;
                }

                // `define('NAME', ...)`
                if (
                    strcasecmp($t->text, 'define') === 0
                    && $next !== null
                    && $next->id === ord('(')
                ) {
                    $argIdx = $this->skipTrivia($tokens, $nextIdx + 1);
                    if (
                        $argIdx < $count
                        && $tokens[$argIdx]->id === T_CONSTANT_ENCAPSED_STRING
                    ) {
                        $name = $this->stringLiteralValue($tokens[$argIdx]->text);
                        if ($name !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1) {
                            $this->symbols->declare(SymbolTable::KIND_CONSTANT, $name);
                            $annotations[$argIdx] = ['kind' => 'define_name', 'name' => $name];
                        }
                    }
                    continue;
                }
            }
        }

        return $annotations;
    }

    /**
     * Generate fresh names for every declared symbol whose category is
     * scheduled for obfuscation.
     */
    private function assignNames(): void
    {
        // Reserve all already-declared identifiers so we never collide with
        // them during generation.
        $allNames = [];
        foreach ($this->symbols->all() as $bucket) {
            foreach (array_keys($bucket) as $name) {
                $allNames[] = $name;
            }
        }
        $this->names->reserve($allNames);
        $this->names->reserve(array_keys(self::RESERVED_VARIABLES));
        $this->names->reserve(array_keys(self::RESERVED_METHODS));
        $this->names->reserve(['self', 'static', 'parent', 'true', 'false', 'null']);

        $kindsToProcess = [
            SymbolTable::KIND_VARIABLE => ['flag' => $this->options->obfuscateVariables, 'prefix' => 'v'],
            SymbolTable::KIND_FUNCTION => ['flag' => $this->options->obfuscateFunctions, 'prefix' => 'f'],
            SymbolTable::KIND_CLASS    => ['flag' => $this->options->obfuscateClasses,   'prefix' => 'C'],
            SymbolTable::KIND_METHOD   => ['flag' => $this->options->obfuscateMethods,   'prefix' => 'm'],
            SymbolTable::KIND_PROPERTY => ['flag' => $this->options->obfuscateProperties,'prefix' => 'p'],
            SymbolTable::KIND_CONSTANT => ['flag' => $this->options->obfuscateConstants, 'prefix' => 'k'],
        ];

        foreach ($kindsToProcess as $kind => $info) {
            if (!$info['flag']) {
                continue;
            }
            foreach ($this->symbols->namesOf($kind) as $name => $existing) {
                if ($existing !== null) {
                    continue;
                }
                $this->symbols->setRename($kind, $name, $this->names->generate($info['prefix']));
            }
        }
    }

    /**
     * Re-emit the token stream with renamed identifiers and (optionally)
     * Base64-encoded string literals.
     *
     * @param PhpToken[]                                                $tokens
     * @param array<int, array{kind:string, name:string}>               $annotations
     */
    private function rewrite(array $tokens, array $annotations): string
    {
        $count       = count($tokens);
        $output      = '';
        $interpDepth = 0;

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];

            if ($t->id === T_CURLY_OPEN || $t->id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $interpDepth++;
            } elseif ($t->text === '}' && $t->id === ord('}') && $interpDepth > 0) {
                $interpDepth--;
                $output .= '}';
                continue;
            }

            // Property and variable tokens
            if ($t->id === T_VARIABLE) {
                $name = ltrim($t->text, '$');

                if (isset(self::RESERVED_VARIABLES[$name])) {
                    $output .= $t->text;
                    continue;
                }

                $kind = $annotations[$i]['kind'] ?? SymbolTable::KIND_VARIABLE;
                $rename = $this->symbols->rename($kind, $name);
                $output .= $rename !== null ? '$' . $rename : $t->text;
                continue;
            }

            // String identifier rewriting
            if ($t->id === T_STRING) {
                $kind = $annotations[$i]['kind'] ?? null;

                if ($kind === 'member_access') {
                    $output .= $this->renameMemberAccess($tokens, $i, $t->text);
                    continue;
                }

                if ($kind !== null && $kind !== 'define_name') {
                    $rename = $this->symbols->rename($kind, $t->text);
                    $output .= $rename ?? $t->text;
                    continue;
                }

                // Bare T_STRING with no annotation: could be a function call,
                // class reference or constant reference.
                $output .= $this->renameBareString($tokens, $i, $t->text);
                continue;
            }

            if ($t->id === T_CONSTANT_ENCAPSED_STRING) {
                $kind = $annotations[$i]['kind'] ?? null;

                if ($kind === 'define_name') {
                    $name = $annotations[$i]['name'];
                    $rename = $this->options->obfuscateConstants
                        ? $this->symbols->rename(SymbolTable::KIND_CONSTANT, $name)
                        : null;
                    $newName = $rename ?? $name;
                    $quote = $t->text[0];
                    $output .= $quote . $newName . $quote;
                    continue;
                }

                if ($kind === 'literal_no_encode') {
                    $output .= $t->text;
                    continue;
                }

                $output .= $this->maybeEncodeStringLiteral($t->text);
                continue;
            }

            $output .= $t->text;
        }

        return $output;
    }

    private function renameMemberAccess(array $tokens, int $i, string $name): string
    {
        $next = $this->nextSignificant($tokens, $i);
        $isCall = $next !== null && $tokens[$next]->id === ord('(');

        $kind = $isCall ? SymbolTable::KIND_METHOD : SymbolTable::KIND_PROPERTY;
        // PHP class constants are accessed via Foo::CONST (no `(`). Only the
        // property/method maps make sense for `->`/`?->`. For `::` we may be
        // accessing a constant or static property; constants live in the
        // constant map.
        $prev = $this->prevSignificant($tokens, $i);
        if ($prev !== null && $tokens[$prev]->id === T_DOUBLE_COLON && !$isCall) {
            // `Foo::BAR` - constant; `Foo::$bar` is a T_VARIABLE which is
            // handled elsewhere.
            $kind = SymbolTable::KIND_CONSTANT;
        }

        $rename = $this->symbols->rename($kind, $name);
        return $rename ?? $name;
    }

    private function renameBareString(array $tokens, int $i, string $name): string
    {
        $next = $this->nextSignificant($tokens, $i);
        $prev = $this->prevSignificant($tokens, $i);
        $isCall = $next !== null && $tokens[$next]->id === ord('(');

        $prevId = $prev !== null ? $tokens[$prev]->id : null;
        $isClassContext = in_array($prevId, [
            T_NEW,
            T_EXTENDS,
            T_IMPLEMENTS,
            T_INSTANCEOF,
            T_USE,
            T_INSTEADOF,
        ], true);
        // `Foo::something` - `Foo` here is a class reference.
        if ($next !== null && $tokens[$next]->id === T_DOUBLE_COLON) {
            $isClassContext = true;
        }
        // `new Foo` - even though `Foo` is followed by `(`, it is a class.
        if ($isCall && $prevId === T_NEW) {
            $isClassContext = true;
            $isCall = false;
        }

        if ($isClassContext) {
            $classRename = $this->symbols->rename(SymbolTable::KIND_CLASS, $name);
            if ($classRename !== null) {
                return $classRename;
            }
            return $name;
        }

        if ($isCall) {
            $rename = $this->symbols->rename(SymbolTable::KIND_FUNCTION, $name);
            return $rename ?? $name;
        }

        $classRename = $this->symbols->rename(SymbolTable::KIND_CLASS, $name);
        if ($classRename !== null) {
            return $classRename;
        }

        $constRename = $this->symbols->rename(SymbolTable::KIND_CONSTANT, $name);
        return $constRename ?? $name;
    }

    private function maybeEncodeStringLiteral(string $literal): string
    {
        if (!$this->options->encodeStrings) {
            return $literal;
        }
        if (strlen($literal) < 4) {
            return $literal;
        }
        $quote = $literal[0];
        if ($quote !== '"' && $quote !== "'") {
            return $literal;
        }

        $value = $this->stringLiteralValue($literal);
        if ($value === '' || $value === null) {
            return $literal;
        }

        return "base64_decode('" . base64_encode($value) . "')";
    }

    /**
     * Re-tokenize the rewritten code and re-emit it with the comment- and
     * whitespace-related options applied.
     */
    private function postProcess(string $code): string
    {
        try {
            $tokens = PhpToken::tokenize($code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            throw new ObfuscationException(
                'Obfuscated code is no longer parseable: ' . $e->getMessage(),
                0,
                $e,
            );
        }
        $output = '';
        $prevSignificant = null;
        $hasPendingWhitespace = false;

        foreach ($tokens as $t) {
            if ($this->options->removeComments && ($t->id === T_COMMENT || $t->id === T_DOC_COMMENT)) {
                $hasPendingWhitespace = true;
                continue;
            }

            if ($t->id === T_WHITESPACE) {
                if ($this->options->removeWhitespace) {
                    $hasPendingWhitespace = true;
                    continue;
                }
                $output .= $t->text;
                continue;
            }

            if ($this->options->removeWhitespace) {
                if ($prevSignificant !== null && $hasPendingWhitespace) {
                    if (
                        $this->endsWithWordChar($prevSignificant->text)
                        && $this->startsWithWordChar($t->text)
                    ) {
                        $output .= ' ';
                    }
                }
            } elseif ($hasPendingWhitespace && $prevSignificant !== null) {
                // Comments were stripped: keep a single space so identifiers
                // do not collide.
                if (
                    $this->endsWithWordChar($prevSignificant->text)
                    && $this->startsWithWordChar($t->text)
                    && substr($output, -1) !== ' '
                    && substr($output, -1) !== "\n"
                    && substr($output, -1) !== "\t"
                ) {
                    $output .= ' ';
                }
            }

            $output .= $t->text;
            $prevSignificant = $t;
            $hasPendingWhitespace = false;
        }

        return $output;
    }

    // ------------------------------------------------------------------
    //  Token helpers
    // ------------------------------------------------------------------

    private function findNextRealBrace(array $tokens, int $from): ?int
    {
        $count = count($tokens);
        for ($i = $from; $i < $count; $i++) {
            $t = $tokens[$i];
            if ($t->id === ord('{')) {
                return $i;
            }
            if ($t->id === ord(';')) {
                return null; // abstract method or interface declaration without body
            }
        }
        return null;
    }

    private function skipTrivia(array $tokens, int $from): int
    {
        $count = count($tokens);
        while ($from < $count) {
            $id = $tokens[$from]->id;
            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                return $from;
            }
            $from++;
        }
        return $count;
    }

    private function prevSignificant(array $tokens, int $from): ?int
    {
        for ($i = $from - 1; $i >= 0; $i--) {
            $id = $tokens[$i]->id;
            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                return $i;
            }
        }
        return null;
    }

    private function nextSignificant(array $tokens, int $from): ?int
    {
        $count = count($tokens);
        for ($i = $from + 1; $i < $count; $i++) {
            $id = $tokens[$i]->id;
            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Is the variable at $i a constructor-promoted property?
     *
     * The token at $i is assumed to be inside a parameter list of a method
     * declared in a class body. A promoted property is preceded by one of
     * `public`, `private`, `protected` or `readonly` somewhere between the
     * previous parameter boundary (`(` or `,`) and the variable itself.
     */
    private function isPromotedProperty(array $tokens, int $i): bool
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $t = $tokens[$j];
            if ($t->id === T_PUBLIC || $t->id === T_PRIVATE || $t->id === T_PROTECTED || $t->id === T_READONLY) {
                return true;
            }
            if ($t->id === ord('(') || $t->id === ord(',')) {
                return false;
            }
        }
        return false;
    }

    /**
     * Decode a single- or double-quoted PHP string literal to its runtime
     * value. Returns null when the literal is not recognised.
     */
    private function stringLiteralValue(string $literal): ?string
    {
        if (strlen($literal) < 2) {
            return null;
        }
        $quote = $literal[0];
        if ($quote !== '"' && $quote !== "'") {
            return null;
        }
        if (substr($literal, -1) !== $quote) {
            return null;
        }
        $inner = substr($literal, 1, -1);

        if ($quote === "'") {
            return strtr($inner, ["\\'" => "'", '\\\\' => '\\']);
        }

        // Use eval-free decoding for double-quoted literals.
        return stripcslashes($inner);
    }

    private function endsWithWordChar(string $s): bool
    {
        if ($s === '') {
            return false;
        }
        $c = $s[strlen($s) - 1];
        return ctype_alnum($c) || $c === '_' || $c === '$';
    }

    private function startsWithWordChar(string $s): bool
    {
        if ($s === '') {
            return false;
        }
        $c = $s[0];
        return ctype_alnum($c) || $c === '_' || $c === '$' || $c === '\\';
    }
}
