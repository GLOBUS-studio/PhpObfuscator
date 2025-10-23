# PHP Obfuscator

A comprehensive and powerful PHP code obfuscator that provides multiple layers of obfuscation to protect your source code.

**✨ Fully compatible with PHP 8.0, 8.1, 8.2, 8.3, and 8.4**

## Features

- **Variable Obfuscation**: Replace variable names with random names
- **Function Obfuscation**: Obfuscate user-defined function names
- **Class Obfuscation**: Obfuscate class names and their members
- **Method & Property Obfuscation**: Rename class methods and properties
- **Constant Obfuscation**: Obfuscate defined constants
- **String Encoding**: Encode string literals using Base64
- **Comment Removal**: Remove all comments from code
- **Whitespace Removal**: Minify code by removing unnecessary whitespace
- **Base64 Wrapping**: Wrap entire code in Base64 + eval() for additional layer
- **Configurable Options**: Fine-tune obfuscation behavior
- **Collision Prevention**: Smart name generation to avoid conflicts
- **Error Handling**: Comprehensive exception handling
- **PHP 8.x Support**: Full support for enums, readonly properties, named arguments, attributes, union/intersection types, and more

## PHP 8.x Compatibility

This obfuscator fully supports all PHP 8.x features:

### PHP 8.0+
- ✅ Named arguments
- ✅ Attributes (annotations)
- ✅ Constructor property promotion
- ✅ Union types
- ✅ Match expressions
- ✅ Nullsafe operator
- ✅ Arrow functions (short closures)

### PHP 8.1+
- ✅ Enums
- ✅ Readonly properties
- ✅ First-class callable syntax
- ✅ Intersection types
- ✅ Never return type
- ✅ Final class constants

### PHP 8.2+
- ✅ Readonly classes
- ✅ Disjunctive Normal Form (DNF) types
- ✅ True/false standalone types
- ✅ Constants in traits

### PHP 8.3+
- ✅ Typed class constants
- ✅ Dynamic class constant fetch
- ✅ Override attribute

### PHP 8.4+
- ✅ Property hooks
- ✅ Asymmetric visibility
- ✅ New array functions

## Installation

Clone this repository or download the `PhpObfuscator.php` and include it in your project:

```bash
git clone https://github.com/GLOBUS-studio/PhpObfuscator.git
```

Or download directly:

```bash
wget https://raw.githubusercontent.com/GLOBUS-studio/PhpObfuscator/main/PhpObfuscator.php
```

## Usage

### Quick Start

```php
<?php
require_once 'PhpObfuscator.php';

$obfuscator = new PhpObfuscator();
$obfuscated = $obfuscator->obfuscate('path/to/your/file.php', true);
$obfuscator->saveToFile($obfuscated, 'path/to/output.php');
```

### PHP 8.x Examples

```php
<?php
require_once 'PhpObfuscator.php';

// Example with PHP 8.x features
$code = '
<?php
enum Status: string {
    case PENDING = "pending";
    case APPROVED = "approved";
    case REJECTED = "rejected";
}

readonly class User {
    public function __construct(
        private string $name,
        private string $email,
        private Status $status = Status::PENDING
    ) {}
    
    public function getInfo(): string {
        return match($this->status) {
            Status::PENDING => "User {$this->name} is pending",
            Status::APPROVED => "User {$this->name} is approved",
            Status::REJECTED => "User {$this->name} is rejected",
        };
    }
}

$user = new User("John Doe", "john@example.com");
echo $user->getInfo();
';

$obfuscator = new PhpObfuscator();
$obfuscated = $obfuscator->obfuscate($code);
```

### Basic Usage

```php
<?php
require_once 'PhpObfuscator.php';

$obfuscator = new PhpObfuscator();
$code = '
<?php
$username = "admin";
$password = "secret123";

function authenticate($user, $pass) {
    global $username, $password;
    return $user === $username && $pass === $password;
}

class User {
    private $name;
    
    public function __construct($name) {
        $this->name = $name;
    }
    
    public function getName() {
        return $this->name;
    }
}

$result = authenticate("admin", "secret123");
$user = new User("John");
echo $user->getName();
';

$obfuscatedCode = $obfuscator->obfuscate($code);
echo $obfuscatedCode;
```

### Advanced Usage with Options

```php
<?php
$options = [
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
];

$obfuscator = new PhpObfuscator($options);

// Obfuscate from string
$obfuscatedCode = $obfuscator->obfuscate($code);

// Or obfuscate from file
$obfuscatedCode = $obfuscator->obfuscate('input.php', true);

// Save to file
$obfuscator->saveToFile($obfuscatedCode, 'output.php');
```

### Selective Obfuscation

```php
<?php
// Only obfuscate variables and strings, keep everything else readable
$obfuscator = new PhpObfuscator([
    'obfuscateVariables' => true,
    'obfuscateFunctions' => false,
    'obfuscateClasses' => false,
    'encodeStrings' => true,
    'wrapWithEval' => false,
]);
```

### Get Name Mapping (for debugging)

```php
<?php
$obfuscator = new PhpObfuscator();
$obfuscatedCode = $obfuscator->obfuscate($code);

// Get the mapping of original to obfuscated names
$nameMap = $obfuscator->getNameMap();
print_r($nameMap);
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `obfuscateVariables` | bool | `true` | Obfuscate variable names |
| `obfuscateFunctions` | bool | `true` | Obfuscate function names |
| `obfuscateClasses` | bool | `true` | Obfuscate class names |
| `obfuscateMethods` | bool | `true` | Obfuscate method names |
| `obfuscateProperties` | bool | `true` | Obfuscate property names |
| `obfuscateConstants` | bool | `true` | Obfuscate constant names |
| `encodeStrings` | bool | `true` | Encode string literals with Base64 |
| `removeComments` | bool | `true` | Remove all comments |
| `removeWhitespace` | bool | `true` | Remove unnecessary whitespace |
| `wrapWithEval` | bool | `true` | Wrap code with Base64 + eval() |
| `preserveLineNumbers` | bool | `false` | Preserve original line numbers (future feature) |

## Protected Names

The obfuscator automatically protects:
- **PHP reserved keywords** (including all PHP 8.x keywords: `enum`, `readonly`, `match`, `never`, `from`, etc.)
- **Magic methods** (`__construct`, `__destruct`, `__serialize`, `__unserialize`, etc.)
- **Superglobals** (`$_GET`, `$_POST`, `$_SERVER`, etc.)
- **Built-in classes** (`stdClass`, `Exception`, `DateTime`, `PDO`, `Closure`, etc.)
- **Magic constants** (`__CLASS__`, `__DIR__`, `__FILE__`, `__FUNCTION__`, etc.)

## Examples

### PHP 8.1+ Enum Example

```php
<?php
enum Color {
    case Red;
    case Green;
    case Blue;
    
    public function label(): string {
        return match($this) {
            Color::Red => 'Red Color',
            Color::Green => 'Green Color',
            Color::Blue => 'Blue Color',
        };
    }
}
```

After obfuscation, the enum structure is preserved while names are obfuscated.

### PHP 8.0+ Constructor Property Promotion

```php
<?php
class Product {
    public function __construct(
        private string $name,
        private float $price,
        private readonly int $id
    ) {}
}
```

The obfuscator correctly handles promoted properties.

## Security Notes

⚠️ **Important Security Considerations:**

- This obfuscator provides **protection against casual inspection**, not cryptographic security
- **Obfuscation is NOT encryption** - determined attackers can reverse it with tools
- **Always keep backups** of your original source code
- **Test thoroughly** after obfuscation to ensure functionality
- **Do not rely solely on obfuscation** for protecting sensitive data
- Use proper security measures like:
  - Server-side validation and sanitization
  - Encryption for sensitive data (passwords, API keys, etc.)
  - Secure authentication and authorization mechanisms
  - Regular security audits and penetration testing
  - Keep PHP and dependencies updated

## Best Practices

1. **Always backup original code** before obfuscation
2. **Test obfuscated code** in a staging environment first
3. **Version control** your original code (never commit obfuscated code to VCS)
4. **Use selective obfuscation** for better performance and debugging
5. **Combine with other security measures** (encryption, access control, etc.)
6. **Document your obfuscation strategy** for your team
7. **Monitor obfuscated code** for any runtime errors
8. **Keep obfuscated files separate** from source files

## Limitations

- May not work correctly with:
  - Code using variable variables (e.g., `$$varname`)
  - Dynamic function/method calls (`call_user_func`, etc.)
  - Reflection API intensive code
  - Code that reads its own source (`__FILE__`, `get_defined_functions()`, etc.)
  - Heredoc/Nowdoc strings (limited support)
  - Complex namespace aliases
- Performance overhead due to Base64 decoding when `wrapWithEval` is enabled
- Slightly increased file size
- Debugging obfuscated code is extremely difficult

## Advanced Features

### Custom Obfuscation Patterns

```php
<?php
// Obfuscate everything except specific classes
$obfuscator = new PhpObfuscator([
    'obfuscateClasses' => true,
    'wrapWithEval' => false,
]);

// You can manually exclude classes by pre-processing
$code = str_replace('class MyImportantClass', '/*KEEP*/class MyImportantClass', $code);
$obfuscated = $obfuscator->obfuscate($code);
$obfuscated = str_replace('/*KEEP*/', '', $obfuscated);
```

### Performance Optimization

For better performance, disable features you don't need:

```php
<?php
$obfuscator = new PhpObfuscator([
    'encodeStrings' => false,      // Faster execution
    'removeWhitespace' => false,   // Better debugging
    'wrapWithEval' => false,       // No eval() overhead
]);
```

## Troubleshooting

**Code not working after obfuscation?**
- Disable `wrapWithEval` option and check for syntax errors
- Try selective obfuscation (disable specific options one by one)
- Check for dynamic code patterns (variable variables, `eval()`, etc.)
- Verify that you're not using Reflection API
- Check error logs for specific issues

**Fatal errors after obfuscation?**
- Ensure you're not using reserved PHP keywords as identifiers
- Check for variable variables or dynamic function calls
- Look for namespace conflicts
- Verify class autoloading still works

**Strings not encoded correctly?**
- URLs and file paths are automatically excluded from encoding
- Check for escaped quotes in strings
- Try disabling `encodeStrings` for debugging

**Performance issues?**
- Disable `wrapWithEval` (biggest performance impact)
- Consider selective obfuscation
- Cache obfuscated files (don't obfuscate on every request)

## Examples Repository

Check out the `example.php` file for comprehensive examples:

```bash
php example.php
```

This will demonstrate:
- Basic obfuscation
- PHP 8.x features support
- Selective obfuscation
- Name mapping
- File obfuscation
- Maximum obfuscation
- Testing obfuscated code

## Requirements

- **PHP 8.0 or higher** (recommended: PHP 8.2+)
- **Tokenizer extension** (usually enabled by default)
- No other external dependencies
- Works with all PHP 8.x versions (8.0, 8.1, 8.2, 8.3, 8.4)

## Known Limitations with PHP 8.x

- **Attributes**: Attribute class names are preserved to maintain functionality
- **Enums**: Enum names and case names may need to be preserved for external APIs
- **Named Arguments**: Will not work correctly after parameter obfuscation
- **Reflection**: Code using `ReflectionClass`, `ReflectionMethod`, etc. will break
- **Fibers**: Not tested with PHP 8.1+ Fibers
- **Property Hooks** (PHP 8.4): Limited support, may need manual adjustment

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure your code:
- Follows PSR-12 coding standards
- Includes PHPDoc comments
- Has test cases for new features
- Doesn't break existing functionality

## License

This project is licensed under the MIT License. See the LICENSE file for more details.

## Support

- **Issues**: [GitHub Issues](https://github.com/GLOBUS-studio/PhpObfuscator/issues)
- **Discussions**: [GitHub Discussions](https://github.com/GLOBUS-studio/PhpObfuscator/discussions)
- **Email**: support@globus-studio.com

## Changelog

### Version 2.2 (Current)
- 🐛 Fixed duplicate entries in `$magicNames`
- 🐛 Fixed `yield from` in reserved words (split into `yield` and `from`)
- 🐛 Fixed `readonly` modifier incorrectly applied to methods
- ✨ Improved string encoding with escape handling
- ✨ Better comment removal using PHP tokenizer
- ✨ Enhanced constant obfuscation to avoid over-replacement
- ✨ Added built-in PHP classes to protected names
- ✨ Improved regex patterns for better accuracy
- ✨ Added comprehensive `example.php` with 7 use cases
- 📝 Updated documentation with troubleshooting and best practices
- 🔒 Better handling of URLs and file paths in strings

### Version 2.1
- Full compatibility with PHP 8.0-8.4
- Support for enums, readonly properties, match expressions
- Complete list of PHP 8.x reserved words
- Improved handling of typed properties and promoted constructors
- Better support for union and intersection types
- Protection of magic methods including `__serialize` and `__unserialize`

### Version 2.0
- Added comprehensive obfuscation for functions, classes, methods, properties, and constants
- Improved string encoding
- Added configuration options
- Enhanced error handling
- Better collision prevention
- Support for superglobals and magic methods

### Version 1.0
- Initial release
- Basic variable and string obfuscation


