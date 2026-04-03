<?php

/**
 * Tests for authentication context in config/auth.php
 */

define('TEST_MODE', true);

// Start a session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any existing session data
$_SESSION = [];

// Include the auth functions
require_once __DIR__ . '/../config/auth.php';

/**
 * Helper to run a test and report result
 */
function it($description, $callback) {
    try {
        $callback();
        echo "✅ PASS: $description\n";
    } catch (Exception $e) {
        echo "❌ FAIL: $description\n";
        echo "   Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Assert helper
 */
function assert_equals($expected, $actual, $message = "") {
    if ($expected !== $actual) {
        $expectedStr = is_array($expected) ? json_encode($expected) : $expected;
        $actualStr = is_array($actual) ? json_encode($actual) : $actual;
        throw new Exception($message ?: "Expected $expectedStr, but got $actualStr");
    }
}

// Test cases

it('returns null when the user is not logged in', function() {
    $_SESSION = [];
    $user = current_user();
    assert_equals(null, $user, "User context should be null when session is empty");
});

it('returns the user context when the user is logged in', function() {
    $_SESSION['user_id'] = 42;
    $_SESSION['user_name'] = 'Jane Doe';
    $_SESSION['user_role'] = 'guardia';

    $expected = [
        'id'   => 42,
        'name' => 'Jane Doe',
        'role' => 'guardia',
    ];

    $user = current_user();
    assert_equals($expected, $user, "User context should match the session data");
});

it('returns null if user_id is missing even if other session data exists', function() {
    $_SESSION = [
        'user_name' => 'John Doe',
        'user_role' => 'admin'
    ];
    $user = current_user();
    assert_equals(null, $user, "User context should be null if user_id is not set");
});

echo "\n--- auth.php:current_user() tests completed successfully ---\n";
