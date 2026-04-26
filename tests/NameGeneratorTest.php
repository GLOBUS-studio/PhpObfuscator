<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator\Tests;

use GLOBUSstudio\PhpObfuscator\NameGenerator;
use PHPUnit\Framework\TestCase;

final class NameGeneratorTest extends TestCase
{
    public function testGeneratesUniqueValidIdentifiers(): void
    {
        $gen = new NameGenerator(6, 8, 1);
        $names = [];
        for ($i = 0; $i < 100; $i++) {
            $name = $gen->generate('v');
            $this->assertMatchesRegularExpression('/^v[A-Za-z]{6,8}$/', $name);
            $this->assertNotContains($name, $names);
            $names[] = $name;
        }
    }

    public function testReservedNamesAreNeverProduced(): void
    {
        $gen = new NameGenerator(2, 3, 1);
        $gen->reserve(['ab', 'CD', 'ef']);
        for ($i = 0; $i < 30; $i++) {
            $name = $gen->generate();
            $this->assertNotContains(strtolower($name), ['ab', 'cd', 'ef']);
        }
    }

    public function testSeedMakesGeneratorDeterministic(): void
    {
        $a = new NameGenerator(6, 6, 99);
        $b = new NameGenerator(6, 6, 99);
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame($a->generate('v'), $b->generate('v'));
        }
    }

    public function testWithoutSeedUsesSecureEngine(): void
    {
        $gen = new NameGenerator(6, 6);
        $name = $gen->generate('v');
        $this->assertMatchesRegularExpression('/^v[A-Za-z]{6}$/', $name);
    }

    public function testInvalidPrefixIsRejected(): void
    {
        $gen = new NameGenerator(6, 6, 1);
        $this->expectException(\InvalidArgumentException::class);
        $gen->generate('1bad');
    }

    public function testInvalidLengthsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NameGenerator(0, 5);
    }

    public function testMaxBelowMinIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NameGenerator(5, 4);
    }
}
