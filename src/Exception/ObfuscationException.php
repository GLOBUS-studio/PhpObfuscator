<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator\Exception;

/**
 * Thrown when the obfuscator cannot complete its work, for example because the
 * input cannot be tokenized or a target file cannot be written.
 */
class ObfuscationException extends \RuntimeException
{
}
