<?php
define('TEST_MODE', true);
require_once __DIR__ . '/../config/config.php';

echo "Testing environment variable loading...\n";

$expected = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'caroli93_residencial_app',
    'DB_USER' => 'caroli93_root',
    'DB_PASS' => 'residencial_app123456'
];

foreach ($expected as $key => $value) {
    $actual = getenv($key);
    assert($actual === $value, "Expected $key to be $value, got $actual");
    echo "✓ $key correctly loaded: $actual\n";
}

echo "All environment variables loaded successfully!\n";
