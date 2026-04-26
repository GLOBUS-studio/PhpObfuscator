<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator\Tests;

use GLOBUSstudio\PhpObfuscator\Exception\ObfuscationException;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    public function testIsRuntimeException(): void
    {
        $e = new ObfuscationException('boom');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertSame('boom', $e->getMessage());
    }
}
