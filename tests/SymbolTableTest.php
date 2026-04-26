<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator\Tests;

use GLOBUSstudio\PhpObfuscator\SymbolTable;
use PHPUnit\Framework\TestCase;

final class SymbolTableTest extends TestCase
{
    public function testDeclareAndRename(): void
    {
        $table = new SymbolTable();
        $table->declare(SymbolTable::KIND_VARIABLE, 'foo');
        $this->assertTrue($table->isDeclared(SymbolTable::KIND_VARIABLE, 'foo'));
        $this->assertFalse($table->isDeclared(SymbolTable::KIND_VARIABLE, 'bar'));
        $this->assertNull($table->rename(SymbolTable::KIND_VARIABLE, 'foo'));

        $table->setRename(SymbolTable::KIND_VARIABLE, 'foo', 'aaa');
        $this->assertSame('aaa', $table->rename(SymbolTable::KIND_VARIABLE, 'foo'));
        $this->assertSame(['foo' => 'aaa'], $table->namesOf(SymbolTable::KIND_VARIABLE));
    }

    public function testAllReturnsAllKinds(): void
    {
        $table = new SymbolTable();
        $all = $table->all();
        foreach ([
            SymbolTable::KIND_VARIABLE,
            SymbolTable::KIND_FUNCTION,
            SymbolTable::KIND_CLASS,
            SymbolTable::KIND_METHOD,
            SymbolTable::KIND_PROPERTY,
            SymbolTable::KIND_CONSTANT,
        ] as $kind) {
            $this->assertSame([], $all[$kind]);
        }
    }

    public function testInvalidKindThrows(): void
    {
        $table = new SymbolTable();
        $this->expectException(\InvalidArgumentException::class);
        $table->declare('nope', 'x');
    }

    public function testRedeclareKeepsExistingRename(): void
    {
        $table = new SymbolTable();
        $table->declare(SymbolTable::KIND_VARIABLE, 'foo');
        $table->setRename(SymbolTable::KIND_VARIABLE, 'foo', 'aaa');
        $table->declare(SymbolTable::KIND_VARIABLE, 'foo');
        $this->assertSame('aaa', $table->rename(SymbolTable::KIND_VARIABLE, 'foo'));
    }
}
