<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator;

/**
 * Immutable value object describing what the obfuscator should rewrite.
 *
 * Every flag is opt-in/opt-out; the defaults produce a fully obfuscated and
 * minified payload wrapped in `eval(base64_decode(...))`.
 */
final class Options
{
    public function __construct(
        public readonly bool $obfuscateVariables = true,
        public readonly bool $obfuscateFunctions = true,
        public readonly bool $obfuscateClasses = true,
        public readonly bool $obfuscateMethods = true,
        public readonly bool $obfuscateProperties = true,
        public readonly bool $obfuscateConstants = true,
        public readonly bool $encodeStrings = true,
        public readonly bool $removeComments = true,
        public readonly bool $removeWhitespace = true,
        public readonly bool $wrapWithEval = true,
        public readonly int $minNameLength = 6,
        public readonly int $maxNameLength = 12,
        public readonly ?int $seed = null,
    ) {
        if ($minNameLength < 1) {
            throw new \InvalidArgumentException('minNameLength must be >= 1.');
        }
        if ($maxNameLength < $minNameLength) {
            throw new \InvalidArgumentException('maxNameLength must be >= minNameLength.');
        }
    }

    /**
     * Build an Options object from an associative array. Unknown keys are
     * rejected to make typos visible to the caller.
     *
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $defaults = get_class_vars(self::class);
        unset($defaults['__construct']);

        $unknown = array_diff(array_keys($values), array_keys($defaults));
        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                'Unknown option(s): ' . implode(', ', $unknown)
            );
        }

        return new self(
            obfuscateVariables:  $values['obfuscateVariables']  ?? true,
            obfuscateFunctions:  $values['obfuscateFunctions']  ?? true,
            obfuscateClasses:    $values['obfuscateClasses']    ?? true,
            obfuscateMethods:    $values['obfuscateMethods']    ?? true,
            obfuscateProperties: $values['obfuscateProperties'] ?? true,
            obfuscateConstants:  $values['obfuscateConstants']  ?? true,
            encodeStrings:       $values['encodeStrings']       ?? true,
            removeComments:      $values['removeComments']      ?? true,
            removeWhitespace:    $values['removeWhitespace']    ?? true,
            wrapWithEval:        $values['wrapWithEval']        ?? true,
            minNameLength:       $values['minNameLength']       ?? 6,
            maxNameLength:       $values['maxNameLength']       ?? 12,
            seed:                $values['seed']                ?? null,
        );
    }

    /**
     * Return a new Options instance with the supplied overrides applied.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        $current = [
            'obfuscateVariables'  => $this->obfuscateVariables,
            'obfuscateFunctions'  => $this->obfuscateFunctions,
            'obfuscateClasses'    => $this->obfuscateClasses,
            'obfuscateMethods'    => $this->obfuscateMethods,
            'obfuscateProperties' => $this->obfuscateProperties,
            'obfuscateConstants'  => $this->obfuscateConstants,
            'encodeStrings'       => $this->encodeStrings,
            'removeComments'      => $this->removeComments,
            'removeWhitespace'    => $this->removeWhitespace,
            'wrapWithEval'        => $this->wrapWithEval,
            'minNameLength'       => $this->minNameLength,
            'maxNameLength'       => $this->maxNameLength,
            'seed'                => $this->seed,
        ];

        return self::fromArray(array_merge($current, $overrides));
    }
}
