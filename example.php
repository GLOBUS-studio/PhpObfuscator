<?php
/**
 * Example usage of PhpObfuscator
 * Demonstrates various features and use cases
 */

require_once 'PhpObfuscator.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo "  PHP OBFUSCATOR - DEMONSTRATION EXAMPLES\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Example 1: Basic obfuscation
echo "━━━ Example 1: Basic Obfuscation ━━━\n\n";

$code1 = '<?php
$username = "admin";
$password = "secret123";

function authenticate($user, $pass) {
    global $username, $password;
    return $user === $username && $pass === $password;
}

if (authenticate("admin", "secret123")) {
    echo "Authentication successful!";
}
';

$obfuscator1 = new PhpObfuscator();
$obfuscated1 = $obfuscator1->obfuscate($code1);
echo "Original code length: " . strlen($code1) . " bytes\n";
echo "Obfuscated code length: " . strlen($obfuscated1) . " bytes\n";
echo "Preview: " . substr($obfuscated1, 0, 100) . "...\n\n";

// Example 2: PHP 8.x Features
echo "━━━ Example 2: PHP 8.x Features (Enums, Readonly, Match) ━━━\n\n";

$code2 = '<?php
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

$obfuscator2 = new PhpObfuscator([
    'wrapWithEval' => false, // Keep readable for demonstration
    'removeWhitespace' => false,
]);

$obfuscated2 = $obfuscator2->obfuscate($code2);
echo "Obfuscated PHP 8.x code:\n";
echo substr($obfuscated2, 0, 300) . "...\n\n";

// Example 3: Selective Obfuscation
echo "━━━ Example 3: Selective Obfuscation ━━━\n\n";

$code3 = '<?php
class Calculator {
    private $result = 0;
    
    public function add($number) {
        $this->result += $number;
        return $this;
    }
    
    public function getResult() {
        return $this->result;
    }
}

$calc = new Calculator();
echo $calc->add(5)->add(10)->getResult();
';

// Only obfuscate variables and properties, keep class/method names
$obfuscator3 = new PhpObfuscator([
    'obfuscateVariables' => true,
    'obfuscateClasses' => false,
    'obfuscateMethods' => false,
    'obfuscateProperties' => true,
    'encodeStrings' => false,
    'wrapWithEval' => false,
    'removeWhitespace' => false,
]);

$obfuscated3 = $obfuscator3->obfuscate($code3);
echo "Selectively obfuscated (only variables & properties):\n";
echo $obfuscated3 . "\n\n";

// Example 4: Get Name Mapping
echo "━━━ Example 4: Name Mapping (Debug Info) ━━━\n\n";

$obfuscator4 = new PhpObfuscator([
    'wrapWithEval' => false,
]);

$code4 = '<?php
class Product {
    private $name;
    private $price;
    
    public function setName($name) {
        $this->name = $name;
    }
    
    public function setPrice($price) {
        $this->price = $price;
    }
}
';

$obfuscated4 = $obfuscator4->obfuscate($code4);
$nameMap = $obfuscator4->getNameMap();

echo "Name Mappings:\n";
echo "Classes: " . json_encode($nameMap['classes'], JSON_PRETTY_PRINT) . "\n";
echo "Methods: " . json_encode($nameMap['methods'], JSON_PRETTY_PRINT) . "\n";
echo "Properties: " . json_encode($nameMap['properties'], JSON_PRETTY_PRINT) . "\n";
echo "Variables: " . json_encode($nameMap['variables'], JSON_PRETTY_PRINT) . "\n\n";

// Example 5: File Obfuscation
echo "━━━ Example 5: File Obfuscation ━━━\n\n";

$testCode = '<?php
define("APP_NAME", "MyApp");
define("VERSION", "2.1.0");

class Config {
    const DB_HOST = "localhost";
    const DB_NAME = "mydb";
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getAppName() {
        return APP_NAME . " v" . VERSION;
    }
}

$config = Config::getInstance();
echo $config->getAppName();
';

// Create test input file
$inputFile = 'test_input.php';
$outputFile = 'test_output.php';

file_put_contents($inputFile, $testCode);

$obfuscator5 = new PhpObfuscator();

try {
    $obfuscated5 = $obfuscator5->obfuscate($inputFile, true);
    $obfuscator5->saveToFile($obfuscated5, $outputFile);
    
    echo "✓ File obfuscation successful!\n";
    echo "  Input:  {$inputFile} (" . filesize($inputFile) . " bytes)\n";
    echo "  Output: {$outputFile} (" . filesize($outputFile) . " bytes)\n";
    
    // Show first 150 characters of obfuscated output
    $outputContent = file_get_contents($outputFile);
    echo "  Preview: " . substr($outputContent, 0, 150) . "...\n\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Example 6: Maximum Obfuscation
echo "━━━ Example 6: Maximum Obfuscation ━━━\n\n";

$code6 = '<?php
$data = ["apple", "banana", "cherry"];
$count = count($data);

for ($i = 0; $i < $count; $i++) {
    echo $data[$i] . "\n";
}
';

$obfuscatorMax = new PhpObfuscator([
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
]);

$obfuscatedMax = $obfuscatorMax->obfuscate($code6);
echo "Maximum obfuscation applied:\n";
echo "Original: " . strlen($code6) . " bytes\n";
echo "Obfuscated: " . strlen($obfuscatedMax) . " bytes\n";
echo "Preview: " . substr($obfuscatedMax, 0, 150) . "...\n\n";

// Example 7: Testing Obfuscated Code
echo "━━━ Example 7: Testing Obfuscated Code Execution ━━━\n\n";

$simpleCode = '<?php
function greet($name) {
    return "Hello, " . $name . "!";
}
echo greet("World");
';

$obfuscatorTest = new PhpObfuscator([
    'wrapWithEval' => false, // We'll execute it manually
    'removeWhitespace' => false,
]);

$obfuscatedTest = $obfuscatorTest->obfuscate($simpleCode);
echo "Testing execution of obfuscated code:\n";
echo "Original output: ";
eval('?>' . $simpleCode);
echo "\nObfuscated output: ";
eval('?>' . $obfuscatedTest);
echo "\n\n";

// Clean up
if (file_exists($inputFile)) {
    unlink($inputFile);
    echo "✓ Cleaned up: {$inputFile}\n";
}

// Keep output file for inspection
echo "✓ Output file kept for inspection: {$outputFile}\n";
echo "\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "  All examples completed successfully!\n";
echo "═══════════════════════════════════════════════════════════════\n";
