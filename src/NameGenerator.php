<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator;

/**
 * Generates collision-free pseudo-random identifier names.
 *
 * The generator is fully deterministic when constructed with a seed, which
 * makes test output predictable and lets users reproduce builds.
 */
final class NameGenerator
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private int $minLength;
    private int $maxLength;
    private ?int $seedState;

    /** @var array<string, true> */
    private array $used = [];

    public function __construct(int $minLength = 6, int $maxLength = 12, ?int $seed = null)
    {
        if ($minLength < 1) {
            throw new \InvalidArgumentException('minLength must be >= 1.');
        }
        if ($maxLength < $minLength) {
            throw new \InvalidArgumentException('maxLength must be >= minLength.');
        }

        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->seedState = $seed;
    }

    /**
     * Reserve a list of identifiers so the generator never returns them.
     *
     * @param iterable<string> $names
     */
    public function reserve(iterable $names): void
    {
        foreach ($names as $name) {
            $this->used[$name] = true;
            $this->used[strtolower($name)] = true;
        }
    }

    /**
     * Generate a fresh identifier with the given prefix. The result starts
     * with a letter and consists only of ASCII letters and digits, which is
     * guaranteed to be a valid PHP identifier.
     */
    public function generate(string $prefix = ''): string
    {
        if ($prefix !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $prefix)) {
            throw new \InvalidArgumentException('Invalid prefix: ' . $prefix);
        }

        $alphabetLen = strlen(self::ALPHABET);
        $maxAttempts = 10_000;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $length = $this->nextInt($this->minLength, $this->maxLength);
            $name = $prefix;
            for ($i = 0; $i < $length; $i++) {
                $name .= self::ALPHABET[$this->nextInt(0, $alphabetLen - 1)];
            }

            $key = strtolower($name);
            if (!isset($this->used[$key])) {
                $this->used[$key] = true;
                $this->used[$name] = true;
                return $name;
            }
        }

        throw new \RuntimeException('Failed to generate a unique identifier after many attempts.');
    }

    /**
     * Pick a uniformly distributed integer in the inclusive range [$min, $max].
     *
     * Without a seed the method delegates to `random_int()`, which is
     * cryptographically secure and available on every supported PHP version.
     * With a seed it advances a small linear congruential generator so that
     * the output is bit-for-bit reproducible across PHP 8.1 - 8.5.
     */
    private function nextInt(int $min, int $max): int
    {
        if ($this->seedState === null) {
            return random_int($min, $max);
        }

        // Numerical Recipes LCG constants.
        $this->seedState = ($this->seedState * 1664525 + 1013904223) & 0x7FFFFFFF;
        $range = $max - $min + 1;
        return $min + ($this->seedState % $range);
    }
}
