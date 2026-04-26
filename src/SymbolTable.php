<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator;

/**
 * Holds the mapping from original identifiers to obfuscated ones, partitioned
 * by the kind of symbol (variable, function, class, method, property, constant).
 */
final class SymbolTable
{
    public const KIND_VARIABLE = 'variables';
    public const KIND_FUNCTION = 'functions';
    public const KIND_CLASS    = 'classes';
    public const KIND_METHOD   = 'methods';
    public const KIND_PROPERTY = 'properties';
    public const KIND_CONSTANT = 'constants';

    private const KINDS = [
        self::KIND_VARIABLE,
        self::KIND_FUNCTION,
        self::KIND_CLASS,
        self::KIND_METHOD,
        self::KIND_PROPERTY,
        self::KIND_CONSTANT,
    ];

    /** @var array<string, array<string, string|null>> */
    private array $map;

    public function __construct()
    {
        $this->map = array_fill_keys(self::KINDS, []);
    }

    public function declare(string $kind, string $name): void
    {
        $this->assertKind($kind);
        if (!array_key_exists($name, $this->map[$kind])) {
            $this->map[$kind][$name] = null;
        }
    }

    public function setRename(string $kind, string $name, string $newName): void
    {
        $this->assertKind($kind);
        $this->map[$kind][$name] = $newName;
    }

    public function rename(string $kind, string $name): ?string
    {
        $this->assertKind($kind);
        return $this->map[$kind][$name] ?? null;
    }

    public function isDeclared(string $kind, string $name): bool
    {
        $this->assertKind($kind);
        return array_key_exists($name, $this->map[$kind]);
    }

    /**
     * @return array<string, string|null>
     */
    public function namesOf(string $kind): array
    {
        $this->assertKind($kind);
        return $this->map[$kind];
    }

    /**
     * Return the full mapping. Each value is `null` if no rename has been
     * assigned yet (the symbol was declared but obfuscation for its kind was
     * disabled).
     *
     * @return array<string, array<string, string|null>>
     */
    public function all(): array
    {
        return $this->map;
    }

    private function assertKind(string $kind): void
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException("Unknown symbol kind: $kind");
        }
    }
}
