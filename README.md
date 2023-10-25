# PHP Obfuscator

A simple and lightweight PHP code obfuscator to provide a basic layer of obfuscation.

## Features

- Replace variable names with random names.
- Encode string literals.
- Encodes the entire code using Base64 for an additional layer of obfuscation.
## Installation

Clone this repository or download the PhpObfuscator.php and include it in your project.

## Usage

Create an instance of the PhpObfuscator class and call the obfuscate method:

```php
$obfuscator = new PhpObfuscator();
$code = '
$variable = "Hello, World!";
$arrayVar = ["Hello", "World"];
function sayHello() {
global $variable, $arrayVar;
echo $variable;
print_r($arrayVar);
}
sayHello();
';
$obfuscatedCode = $obfuscator->obfuscate($code);
echo $obfuscatedCode;
```
To obfuscate a file:

```php
$obfuscatedCodeFromFile = $obfuscator->obfuscate('path_to_input_file.php', true);
$obfuscator->saveToFile($obfuscatedCodeFromFile, 'path_to_output_file.php');
```
## Notes

- This obfuscator provides a basic layer of security. Do not rely on it as your only security measure.
- Make sure to keep a backup of your original code as the obfuscation is not reversible.
- Test your obfuscated code to ensure it still functions as expected.

## License

This project is licensed under the MIT License. See the LICENSE file for more details.


