<?php
/**
 * Class PhpObfuscator
 *
 * Provides methods to obfuscate PHP code.
 * Compatible with PHP 8.0+
 */
class PhpObfuscator
{
    /**
     * A complete list of PHP reserved words (including PHP 8.x keywords)
     *
     * @var array
     */
    private $reservedWords = [
        // Control structures
        'if', 'else', 'elseif', 'endif', 'while', 'endwhile', 'do', 'for', 'endfor',
        'foreach', 'endforeach', 'switch', 'endswitch', 'case', 'default', 'break',
        'continue', 'goto', 'declare', 'enddeclare',
        
        // Functions and classes
        'function', 'return', 'class', 'interface', 'trait', 'extends', 'implements',
        'abstract', 'final', 'static', 'const', 'var', 'enum', // enum added in PHP 8.1
        
        // Visibility modifiers
        'public', 'private', 'protected', 'readonly', // readonly added in PHP 8.1
        
        // Exception handling
        'try', 'catch', 'finally', 'throw',
        
        // Operators and keywords
        'new', 'clone', 'instanceof', 'yield', 'from', 'match', // match added in PHP 8.0
        
        // Type declarations (PHP 7.x - 8.x)
        'array', 'callable', 'bool', 'boolean', 'int', 'integer', 'float', 'double',
        'string', 'object', 'resource', 'null', 'void', 'never', 'mixed', 'iterable',
        'false', 'true', // true/false as standalone types in PHP 8.2
        
        // Special keywords
        'this', 'self', 'parent', 'echo', 'print', 'exit', 'die', 'eval',
        'include', 'include_once', 'require', 'require_once',
        'isset', 'unset', 'empty', 'list',
        
        // Namespace keywords
        'namespace', 'use', 'as', 'insteadof',
        
        // Logical operators
        'and', 'or', 'xor',
        
        // Global scope
        'global',
        
        // Special compiler
        '__halt_compiler', '__autoload',
        
        // PHP 8.0+ attributes and features
        'fn', // arrow functions (short closures)
        
        // Reserved class names
        '__class__', '__trait__', '__function__', '__method__', '__line__', '__file__',
        '__dir__', '__namespace__',
        
        // Built-in classes that should not be obfuscated
        'stdClass', 'Exception', 'Error', 'ErrorException', 'Throwable',
        'DateTime', 'DateTimeImmutable', 'DateInterval', 'DateTimeZone',
        'PDO', 'PDOStatement', 'PDOException',
        'Closure', 'Generator', 'WeakReference', 'WeakMap',
        'ArrayObject', 'ArrayIterator', 'Iterator', 'IteratorAggregate',
        'Traversable', 'Countable', 'Serializable', 'JsonSerializable',
    ];

    /**
     * PHP superglobals that should not be obfuscated
     *
     * @var array
     */
    private $superGlobals = [
        'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION',
        '_REQUEST', '_ENV', 'argc', 'argv', 'php_errormsg', 'http_response_header'
    ];

    /**
     * Magic methods and constants that should not be obfuscated
     *
     * @var array
     */
    private $magicNames = [
        // Magic methods
        '__construct', '__destruct', '__call', '__callStatic', '__get', '__set',
        '__isset', '__unset', '__sleep', '__wakeup', '__serialize', '__unserialize',
        '__toString', '__invoke', '__set_state', '__clone', '__debugInfo',
        
        // Magic constants
        '__CLASS__', '__DIR__', '__FILE__', '__FUNCTION__', '__LINE__',
        '__METHOD__', '__NAMESPACE__', '__TRAIT__',
    ];

    /**
     * Configuration options
     *
     * @var array
     */
    private $options = [
        'obfuscateVariables' => true,
        'obfuscateFunctions' => true,
        'obfuscateClasses' => true,
        'obfuscateMethods' => true,
        'obfuscateProperties' => true,
        'obfuscateConstants' => true,
        'encodeStrings' => true,
        'removeComments' => true,
        'removeWhitespace' => true,
        'wrapWithEval' => true,
        'preserveLineNumbers' => false,
    ];

    /**
     * Mapping of original names to obfuscated names
     *
     * @var array
     */
    private $nameMap = [
        'variables' => [],
        'functions' => [],
        'classes' => [],
        'methods' => [],
        'properties' => [],
        'constants' => [],
    ];

    /**
     * Used names to prevent collisions
     *
     * @var array
     */
    private $usedNames = [];

    /**
     * Constructor
     *
     * @param array $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Generate a random name.
     *
     * @param string $prefix Optional prefix
     * @return string
     */
    private function generateRandomName($prefix = '')
    {
        do {
            $length = mt_rand(6, 12);
            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $randomString = $prefix;

            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
            }
            
            // Add random numbers in the middle
            if (mt_rand(0, 1)) {
                $pos = mt_rand(1, max(1, strlen($randomString) - 1));
                $randomString = substr($randomString, 0, $pos) . mt_rand(0, 9) . substr($randomString, $pos);
            }
        } while (
            in_array($randomString, $this->usedNames) || 
            in_array(strtolower($randomString), array_map('strtolower', $this->reservedWords)) ||
            in_array($randomString, $this->magicNames)
        );

        $this->usedNames[] = $randomString;
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
        $quote = $match[1];
        $str = $match[2];
        
        // Don't encode empty strings or very short strings
        if (strlen($str) < 2) {
            return $match[0];
        }
        
        // Don't encode strings that look like they might be important literals
        // (URLs, file paths, etc.)
        if (preg_match('#^(https?://|/|[a-zA-Z]:\\\\)#', $str)) {
            return $match[0];
        }
        
        return 'base64_decode(' . $quote . base64_encode($str) . $quote . ')';
    }

    /**
     * Obfuscate variable names
     *
     * @param string $code
     * @return string
     */
    private function obfuscateVariables($code)
    {
        // Match variables including those with property promotion (PHP 8.0+)
        preg_match_all('/\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/', $code, $matches);
        $variables = array_unique($matches[1]);
        
        foreach ($variables as $var) {
            if (!in_array($var, $this->reservedWords) && 
                !in_array($var, $this->superGlobals) && 
                !isset($this->nameMap['variables'][$var])) {
                $this->nameMap['variables'][$var] = $this->generateRandomName();
            }
        }

        foreach ($this->nameMap['variables'] as $original => $obfuscated) {
            // Handle regular variables
            $code = preg_replace('/\$' . preg_quote($original) . '\b/', '$' . $obfuscated, $code);
        }

        return $code;
    }

    /**
     * Obfuscate function names
     *
     * @param string $code
     * @return string
     */
    private function obfuscateFunctions($code)
    {
        // Match regular functions
        preg_match_all('/function\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/', $code, $matches);
        $functions = array_unique($matches[1]);
        
        foreach ($functions as $func) {
            $lowerFunc = strtolower($func);
            // Check if it's a reserved word or built-in function
            if (!in_array($lowerFunc, array_map('strtolower', $this->reservedWords)) && 
                !in_array($func, $this->reservedWords) &&
                !isset($this->nameMap['functions'][$func])) {
                $this->nameMap['functions'][$func] = $this->generateRandomName('f');
            }
        }

        foreach ($this->nameMap['functions'] as $original => $obfuscated) {
            // Replace function definition
            $code = preg_replace('/function\s+' . preg_quote($original, '/') . '\s*\(/', 'function ' . $obfuscated . '(', $code);
            // Replace function calls (not preceded by -> or ::)
            $code = preg_replace('/(?<!->|::)\b' . preg_quote($original, '/') . '\s*\(/', $obfuscated . '(', $code);
        }

        return $code;
    }

    /**
     * Obfuscate class names (including enums in PHP 8.1+)
     *
     * @param string $code
     * @return string
     */
    private function obfuscateClasses($code)
    {
        // Match classes, interfaces, traits, and enums
        preg_match_all('/(?:class|interface|trait|enum)\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/', $code, $matches);
        $classes = array_unique($matches[1]);
        
        foreach ($classes as $class) {
            // Don't obfuscate built-in classes
            if (!in_array($class, $this->reservedWords) &&
                !in_array(strtolower($class), array_map('strtolower', $this->reservedWords)) &&
                !isset($this->nameMap['classes'][$class])) {
                $this->nameMap['classes'][$class] = $this->generateRandomName('C');
            }
        }

        foreach ($this->nameMap['classes'] as $original => $obfuscated) {
            $code = preg_replace('/\b(?:class|interface|trait|enum)\s+' . preg_quote($original, '/') . '\b/', 
                '$0', $code);
            $code = str_replace('class ' . $original, 'class ' . $obfuscated, $code);
            $code = str_replace('interface ' . $original, 'interface ' . $obfuscated, $code);
            $code = str_replace('trait ' . $original, 'trait ' . $obfuscated, $code);
            $code = str_replace('enum ' . $original, 'enum ' . $obfuscated, $code);
            
            $code = preg_replace('/\bnew\s+' . preg_quote($original, '/') . '\b/', 'new ' . $obfuscated, $code);
            $code = preg_replace('/\bextends\s+' . preg_quote($original, '/') . '\b/', 'extends ' . $obfuscated, $code);
            $code = preg_replace('/\bimplements\s+' . preg_quote($original, '/') . '\b/', 'implements ' . $obfuscated, $code);
            $code = preg_replace('/\b' . preg_quote($original, '/') . '\s*::/', $obfuscated . '::', $code);
            
            // Handle use statements and namespaced class references
            $code = preg_replace('/\buse\s+.*?\b' . preg_quote($original, '/') . '\b/', 
                str_replace($original, $obfuscated, '$0'), $code);
        }

        return $code;
    }

    /**
     * Obfuscate method and property names (including PHP 8.x features)
     *
     * @param string $code
     * @return string
     */
    private function obfuscateClassMembers($code)
    {
        // Obfuscate methods (excluding readonly from pattern as it's for properties only)
        if ($this->options['obfuscateMethods']) {
            // Match methods with proper modifiers (public|private|protected|static|final|abstract)
            preg_match_all('/(?:public|private|protected|static|final|abstract|\s)+\s*function\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/', $code, $matches);
            $methods = array_unique($matches[1]);
            
            foreach ($methods as $method) {
                if (!in_array($method, $this->magicNames) && 
                    !isset($this->nameMap['methods'][$method])) {
                    $this->nameMap['methods'][$method] = $this->generateRandomName('m');
                }
            }

            foreach ($this->nameMap['methods'] as $original => $obfuscated) {
                $code = preg_replace('/(?:public|private|protected|static|final|abstract|\s)+\s*function\s+' . preg_quote($original, '/') . '\s*\(/', 
                    '$0', $code);
                $code = preg_replace('/(\s+)function\s+' . preg_quote($original, '/') . '\s*\(/', 
                    '$1function ' . $obfuscated . '(', $code);
                $code = preg_replace('/->' . preg_quote($original, '/') . '\s*\(/', '->' . $obfuscated . '(', $code);
                $code = preg_replace('/::' . preg_quote($original, '/') . '\s*\(/', '::' . $obfuscated . '(', $code);
            }
        }

        // Obfuscate properties (including readonly properties in PHP 8.1+)
        if ($this->options['obfuscateProperties']) {
            // Match property declarations with proper handling of readonly and types
            preg_match_all('/(?:public|private|protected|readonly|static|\s)+\s*(?:readonly\s+)?(?:[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff|\\\|]*\s+)?\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/', $code, $matches);
            $properties = array_unique($matches[1]);
            
            foreach ($properties as $prop) {
                if (!isset($this->nameMap['properties'][$prop])) {
                    $this->nameMap['properties'][$prop] = $this->generateRandomName('p');
                }
            }

            foreach ($this->nameMap['properties'] as $original => $obfuscated) {
                // Handle property declarations with types
                $code = preg_replace('/(?:public|private|protected|readonly|static|\s)+\s*(?:readonly\s+)?(?:[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff|\\\|]*\s+)?\$' . preg_quote($original, '/') . '\b/', 
                    '$0', $code);
                $code = preg_replace('/\$' . preg_quote($original, '/') . '(?=\s*[;,=)])/', '$' . $obfuscated, $code);
                $code = preg_replace('/->' . preg_quote($original, '/') . '\b(?!\s*\()/', '->' . $obfuscated, $code);
            }
        }

        return $code;
    }

    /**
     * Obfuscate constants
     *
     * @param string $code
     * @return string
     */
    private function obfuscateConstants($code)
    {
        preg_match_all('/\bdefine\s*\(\s*["\']([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)["\']/', $code, $matches);
        preg_match_all('/\bconst\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*=/', $code, $matches2);
        
        $constants = array_unique(array_merge($matches[1], $matches2[1]));
        
        foreach ($constants as $const) {
            // Don't obfuscate PHP predefined constants
            if (!defined($const) && !isset($this->nameMap['constants'][$const])) {
                $this->nameMap['constants'][$const] = $this->generateRandomName('K');
            }
        }

        foreach ($this->nameMap['constants'] as $original => $obfuscated) {
            // Replace constant definition
            $code = preg_replace('/\bdefine\s*\(\s*(["\'])' . preg_quote($original, '/') . '\1/', 
                'define($1' . $obfuscated . '$1', $code);
            $code = preg_replace('/\bconst\s+' . preg_quote($original, '/') . '\b/', 
                'const ' . $obfuscated, $code);
            
            // Replace constant usage (more carefully to avoid replacing in strings)
            // Only replace if it's a standalone word (not part of another identifier)
            $code = preg_replace('/\b' . preg_quote($original, '/') . '\b(?!["\'])/', $obfuscated, $code);
        }

        return $code;
    }

    /**
     * Remove comments from code while preserving strings
     *
     * @param string $code
     * @return string
     */
    private function removeComments($code)
    {
        $tokens = token_get_all($code);
        $result = '';
        
        foreach ($tokens as $token) {
            if (is_array($token)) {
                // Token is [token_id, text, line_number]
                if ($token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT) {
                    // Replace comment with space to avoid concatenating tokens
                    $result .= ' ';
                } else {
                    $result .= $token[1];
                }
            } else {
                // Token is a simple character like ; or {
                $result .= $token;
            }
        }
        
        return $result;
    }

    /**
     * Obfuscate the given PHP code.
     *
     * @param string $input   Input code or path to the file with code.
     * @param bool   $isFile  Flag to indicate if $input is a file path.
     * @return string
     * @throws Exception
     */
    public function obfuscate($input, $isFile = false)
    {
        if ($isFile) {
            if (!file_exists($input)) {
                throw new Exception("File not found: $input");
            }
            $code = file_get_contents($input);
            if ($code === false) {
                throw new Exception("Failed to read file: $input");
            }
        } else {
            $code = $input;
        }

        // Reset mappings for each obfuscation
        $this->nameMap = [
            'variables' => [],
            'functions' => [],
            'classes' => [],
            'methods' => [],
            'properties' => [],
            'constants' => [],
        ];
        $this->usedNames = array_merge($this->reservedWords, $this->magicNames);

        // Remove PHP tags for processing, will add them back later
        $hasOpeningTag = strpos($code, '<?php') !== false;
        $hasShortTag = strpos($code, '<?=') !== false;
        $code = preg_replace('/<\?php\s*/', '', $code);
        $code = preg_replace('/<\?=\s*/', '', $code);
        $code = preg_replace('/\?>\s*$/', '', $code);

        // Remove comments using tokenizer (safer method)
        if ($this->options['removeComments']) {
            $code = $this->removeComments('<?php ' . $code);
            $code = preg_replace('/<\?php\s*/', '', $code);
        }

        // Obfuscate in order of dependencies
        if ($this->options['obfuscateConstants']) {
            $code = $this->obfuscateConstants($code);
        }

        if ($this->options['obfuscateClasses']) {
            $code = $this->obfuscateClasses($code);
        }

        if ($this->options['obfuscateMethods'] || $this->options['obfuscateProperties']) {
            $code = $this->obfuscateClassMembers($code);
        }

        if ($this->options['obfuscateFunctions']) {
            $code = $this->obfuscateFunctions($code);
        }

        if ($this->options['obfuscateVariables']) {
            $code = $this->obfuscateVariables($code);
        }

        // Encode string literals
        if ($this->options['encodeStrings']) {
            // Handle double-quoted strings with proper escaping
            $code = preg_replace_callback('/"((?:[^"\\\\]|\\\\.)*)"/s', [$this, 'encodeStringLiterals'], $code);
            // Handle single-quoted strings
            $code = preg_replace_callback("/'((?:[^'\\\\]|\\\\.)*)'/s", [$this, 'encodeStringLiterals'], $code);
        }

        // Remove whitespace (but preserve necessary spaces)
        if ($this->options['removeWhitespace']) {
            $code = preg_replace('/\s+/', ' ', $code);
            $code = preg_replace('/\s*([{}();,=<>!&|+\-*\/\[\].:])\s*/', '$1', $code);
            // Preserve space after keywords
            $code = preg_replace('/\b(if|else|elseif|while|for|foreach|function|return|class|interface|trait|enum|public|private|protected|static|const|new|extends|implements|use|namespace|case|default|throw|try|catch|finally)\b/', '$1 ', $code);
        }

        $code = trim($code);

        // Wrap with eval if enabled
        if ($this->options['wrapWithEval']) {
            $code = '<?php eval(base64_decode("' . base64_encode($code) . '"));';
        } elseif ($hasOpeningTag) {
            $code = '<?php ' . $code;
        } elseif ($hasShortTag) {
            $code = '<?= ' . $code;
        }

        return $code;
    }

    /**
     * Save the given code to a file.
     *
     * @param string $code     Code to save.
     * @param string $filename Path to the output file.
     * @return bool
     * @throws Exception
     */
    public function saveToFile($code, $filename)
    {
        $directory = dirname($filename);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception("Failed to create directory: $directory");
            }
        }

        $result = file_put_contents($filename, $code);
        if ($result === false) {
            throw new Exception("Failed to write to file: $filename");
        }

        return true;
    }

    /**
     * Get the name mapping for debugging purposes
     *
     * @return array
     */
    public function getNameMap()
    {
        return $this->nameMap;
    }

    /**
     * Set configuration options
     *
     * @param array $options
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Get current configuration options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
}
