<?php
/**
 * Debug script to check current session
 */

session_start();
require_once 'config.php';
require_once 'session_check.php';

echo "=== SESSION DEBUG ===\n\n";

echo "Session ID: " . session_id() . "\n";
echo "Session data:\n";
print_r($_SESSION);

echo "\n=== USER CHECK ===\n";

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    echo "User ID: $user_id\n";

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo "User found: " . print_r($user, true) . "\n";
        } else {
            echo "User NOT found in database!\n";
        }

        // Check if lecturer record exists
        if ($user && $user['role'] !== 'admin') {
            echo "\n=== LECTURER CHECK ===\n";
            $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lecturer) {
                echo "Lecturer record found: " . print_r($lecturer, true) . "\n";
            } else {
                echo "Lecturer record NOT found! This is the problem.\n";
            }
        }

    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "No user_id in session!\n";
}

echo "\n=== END DEBUG ===\n";
?>