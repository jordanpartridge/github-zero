<?php

/**
 * GitHub Zero Package Test Script
 * Tests various components without running the full CLI
 */

// Try to load autoloader
$autoloadPaths = [
    __DIR__.'/vendor/autoload.php',     // Package development
    __DIR__.'/../../../autoload.php',  // Composer dependency
];

$autoloaderFound = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require $autoloadPath;
        $autoloaderFound = true;
        echo "✅ Autoloader found: {$autoloadPath}\n";
        break;
    }
}

if (! $autoloaderFound) {
    echo "❌ No autoloader found. Run 'composer install' first.\n";
    exit(1);
}

echo "\n🧪 Testing GitHub Zero Components...\n\n";

// Test 1: Check if classes can be loaded
try {
    echo "1️⃣ Testing class autoloading...\n";

    $classes = [
        'JordanPartridge\GitHubZero\Application',
        'JordanPartridge\GitHubZero\GitHubZeroServiceProvider',
        'JordanPartridge\GitHubZero\ConduitExtension',
        'JordanPartridge\GitHubZero\Commands\ReposCommand',
        'JordanPartridge\GitHubZero\Commands\CloneCommand',
    ];

    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "   ✅ {$class}\n";
        } else {
            echo "   ❌ {$class} - NOT FOUND\n";
        }
    }

} catch (Exception $e) {
    echo '   ❌ Autoloading failed: '.$e->getMessage()."\n";
}

// Test 2: Check dependencies
echo "\n2️⃣ Testing required dependencies...\n";

$dependencies = [
    'Carbon\Carbon' => 'nesbot/carbon',
    'Illuminate\Support\Collection' => 'illuminate/collections',
    'Illuminate\Console\Command' => 'illuminate/console',
    'Laravel\Prompts\select' => 'laravel/prompts',
    'JordanPartridge\GithubClient\Github' => 'jordanpartridge/github-client',
];

foreach ($dependencies as $class => $package) {
    if (class_exists($class) || function_exists($class)) {
        echo "   ✅ {$package}\n";
    } else {
        echo "   ❌ {$package} - {$class} not available\n";
    }
}

// Test 3: Try to instantiate main classes
echo "\n3️⃣ Testing class instantiation...\n";

try {
    $app = new JordanPartridge\GitHubZero\Application;
    echo "   ✅ Application can be instantiated\n";
    echo '   📝 App name: '.$app->getName()."\n";
    echo '   📝 App version: '.$app->getVersion()."\n";
} catch (Exception $e) {
    echo '   ❌ Application instantiation failed: '.$e->getMessage()."\n";
}

try {
    $provider = new JordanPartridge\GitHubZero\GitHubZeroServiceProvider(null);
    echo "   ✅ ServiceProvider can be instantiated\n";
} catch (Exception $e) {
    echo '   ❌ ServiceProvider instantiation failed: '.$e->getMessage()."\n";
}

try {
    $extension = new JordanPartridge\GitHubZero\ConduitExtension;
    echo "   ✅ ConduitExtension can be instantiated\n";
    echo '   📝 Extension name: '.$extension->name()."\n";
    echo '   📝 Extension commands: '.implode(', ', array_keys($extension->commands()))."\n";
} catch (Exception $e) {
    echo '   ❌ ConduitExtension instantiation failed: '.$e->getMessage()."\n";
}

// Test 4: Check binary
echo "\n4️⃣ Testing binary executable...\n";

$binaryPath = __DIR__.'/bin/github';
if (file_exists($binaryPath)) {
    echo "   ✅ Binary exists: {$binaryPath}\n";

    if (is_executable($binaryPath)) {
        echo "   ✅ Binary is executable\n";
    } else {
        echo "   ⚠️  Binary exists but not executable (run: chmod +x bin/github)\n";
    }
} else {
    echo "   ❌ Binary not found\n";
}

// Test 5: Environment checks
echo "\n5️⃣ Testing environment...\n";

if (getenv('GITHUB_TOKEN')) {
    echo "   ✅ GITHUB_TOKEN is set\n";
} else {
    echo "   ⚠️  GITHUB_TOKEN not set (commands will fail)\n";
}

echo "\n🏁 Test complete!\n";
echo "\n💡 To test the CLI:\n";
echo "   export GITHUB_TOKEN=your_token_here\n";
echo "   ./bin/github repos --help\n";
echo "   ./bin/github repos --limit=5\n";
