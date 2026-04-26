<?php

declare(strict_types=1);

namespace GLOBUSstudio\PhpObfuscator\Tests\Support;

/**
 * Tiny helper that executes a snippet of PHP through the same interpreter the
 * test suite is currently running under and returns whatever was written to
 * STDOUT.
 */
final class PhpRunner
{
    public static function run(string $code): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'php-obf-');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temporary file.');
        }
        try {
            file_put_contents($tmp, $code);

            $descriptors = [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $cmd = escapeshellarg(PHP_BINARY) . ' -d error_reporting=E_ALL -d display_errors=stderr ' . escapeshellarg($tmp);
            $process = proc_open($cmd, $descriptors, $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Failed to start PHP child process.');
            }
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    "Child PHP process exited with code $exitCode.\nSTDERR:\n$stderr\nSTDOUT:\n$stdout"
                );
            }
            return (string) $stdout;
        } finally {
            @unlink($tmp);
        }
    }
}
