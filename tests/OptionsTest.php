<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator\Tests;

use GLOBUSstudio\PhpObfuscator\Options;
use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase
{
    public function testDefaultsAreFullObfuscation(): void
    {
        $opts = new Options();
        $this->assertTrue($opts->obfuscateVariables);
        $this->assertTrue($opts->obfuscateFunctions);
        $this->assertTrue($opts->obfuscateClasses);
        $this->assertTrue($opts->obfuscateMethods);
        $this->assertTrue($opts->obfuscateProperties);
        $this->assertTrue($opts->obfuscateConstants);
        $this->assertTrue($opts->encodeStrings);
        $this->assertTrue($opts->removeComments);
        $this->assertTrue($opts->removeWhitespace);
        $this->assertTrue($opts->wrapWithEval);
        $this->assertSame(6, $opts->minNameLength);
        $this->assertSame(12, $opts->maxNameLength);
        $this->assertNull($opts->seed);
    }

    public function testFromArrayAcceptsOverrides(): void
    {
        $opts = Options::fromArray([
            'obfuscateVariables' => false,
            'minNameLength' => 3,
            'maxNameLength' => 4,
            'seed' => 11,
        ]);
        $this->assertFalse($opts->obfuscateVariables);
        $this->assertTrue($opts->obfuscateFunctions);
        $this->assertSame(3, $opts->minNameLength);
        $this->assertSame(4, $opts->maxNameLength);
        $this->assertSame(11, $opts->seed);
    }

    public function testFromArrayRejectsUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option(s): nope');
        Options::fromArray(['nope' => true]);
    }

    public function testWithProducesNewInstance(): void
    {
        $a = new Options(seed: 1);
        $b = $a->with(['seed' => 2, 'wrapWithEval' => false]);
        $this->assertNotSame($a, $b);
        $this->assertSame(1, $a->seed);
        $this->assertSame(2, $b->seed);
        $this->assertFalse($b->wrapWithEval);
        $this->assertTrue($a->wrapWithEval);
    }

    public function testInvalidLengthsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Options(minNameLength: 0);
    }

    public function testMaxLessThanMinIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Options(minNameLength: 5, maxNameLength: 4);
    }
}
