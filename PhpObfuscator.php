<?php
/**
 * Class PhpObfuscator
 *
 * Provides methods to obfuscate PHP code.
 */
class PhpObfuscator
{
    /**
     * A list of PHP reserved words.
     *
     * @var array
     */
    private $reservedWords = [
        'if', 'else', 'while', 'for', 'function', 'return', 'class', 'public',
        'private', 'protected', 'switch', 'case', 'default', 'break', 'do',
        'continue', 'echo', 'print', 'new', 'die', 'exit', 'require', 'include',
        'require_once', 'include_once', 'global', 'static', 'const', 'final',
        'use', 'namespace', 'goto', 'throw', 'try', 'catch', 'finally',
        'instanceof', 'insteadof', 'yield', 'clone', 'abstract', 'interface', 'trait'
    ];

    /**
     * Generate a random variable name.
     *
     * @return string
     */
    private function generateRandomVarName()
    {
        $length = mt_rand(5, 10);
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        return $randomString;
    }

    /**
     * Encode string literals to Base64.
     *
     * @param array $match
     * @return string
     */
    private function encodeStringLiterals($match)
    {
        $str = $match[1];
        return 'base64_decode("' . base64_encode($str) . '")';
    }

    /**
     * Obfuscate the given PHP code.
     *
     * @param string $input   Input code or path to the file with code.
     * @param bool   $isFile  Flag to indicate if $input is a file path.
     * @return string
     */
    public function obfuscate($input, $isFile = false)
    {
        if ($isFile && file_exists($input)) {
            $code = file_get_contents($input);
        } else {
            $code = $input;
        }

        // Remove comments
        $code = preg_replace('!/\*.*?\*/!s', '', $code);
        $code = preg_replace('/\n\s*\n/', "\n", $code);
        $code = preg_replace('![ \t]*//.*[ \t]*[\r\n]!', '', $code);

        // Replace variable names
        preg_match_all('/\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/', $code, $matches);
        foreach ($matches[1] as $var) {
            if (!in_array($var, $this->reservedWords)) {
                $newVarName = $this->generateRandomVarName();
                $code = str_replace('$' . $var, '$' . $newVarName, $code);
            }
        }

        // Encode string literals
        $code = preg_replace_callback('/"([^"]+)"/', [$this, 'encodeStringLiterals'], $code);

        // Obfuscation with eval()
        $code = 'eval(base64_decode("' . base64_encode($code) . '"));';

        return $code;
    }

    /**
     * Save the given code to a file.
     *
     * @param string $code     Code to save.
     * @param string $filename Path to the output file.
     * @return void
     */
    public function saveToFile($code, $filename)
    {
        file_put_contents($filename, $code);
    }
}
