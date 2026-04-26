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
    private \Random\Randomizer $random;

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
        $engine = $seed === null
            ? new \Random\Engine\Secure()
            : new \Random\Engine\Mt19937($seed);
        $this->random = new \Random\Randomizer($engine);
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
            $length = $this->random->getInt($this->minLength, $this->maxLength);
            $name = $prefix;
            for ($i = 0; $i < $length; $i++) {
                $name .= self::ALPHABET[$this->random->getInt(0, $alphabetLen - 1)];
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
}
