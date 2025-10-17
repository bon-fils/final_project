<?php
/**
 * Database Migration: Add Fingerprint Support
 * Adds fingerprint-related columns to students table
 */

require_once "config.php";

try {
    $pdo->beginTransaction();
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'fingerprint_id'");
    if ($stmt->rowCount() == 0) {
        // Add fingerprint_id column after fingerprint_path
        $pdo->exec("ALTER TABLE students ADD COLUMN fingerprint_id INT NULL AFTER fingerprint_path");
        echo "✅ Added fingerprint_id column\n";
    } else {
        echo "ℹ️ fingerprint_id column already exists\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'fingerprint_status'");
    if ($stmt->rowCount() == 0) {
        // Add fingerprint_status column
        $pdo->exec("ALTER TABLE students ADD COLUMN fingerprint_status ENUM('not_enrolled', 'enrolling', 'enrolled', 'failed') DEFAULT 'not_enrolled' AFTER fingerprint_id");
        echo "✅ Added fingerprint_status column\n";
    } else {
        echo "ℹ️ fingerprint_status column already exists\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'fingerprint_enrolled_at'");
    if ($stmt->rowCount() == 0) {
        // Add fingerprint_enrolled_at column
        $pdo->exec("ALTER TABLE students ADD COLUMN fingerprint_enrolled_at TIMESTAMP NULL AFTER fingerprint_status");
        echo "✅ Added fingerprint_enrolled_at column\n";
    } else {
        echo "ℹ️ fingerprint_enrolled_at column already exists\n";
    }
    
    // Add index for fingerprint_id
    $stmt = $pdo->query("SHOW INDEX FROM students WHERE Key_name = 'idx_fingerprint_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD INDEX idx_fingerprint_id (fingerprint_id)");
        echo "✅ Added fingerprint_id index\n";
    } else {
        echo "ℹ️ fingerprint_id index already exists\n";
    }
    
    // Add unique constraint for fingerprint_id
    $stmt = $pdo->query("SHOW INDEX FROM students WHERE Key_name = 'unique_fingerprint_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD UNIQUE KEY unique_fingerprint_id (fingerprint_id)");
        echo "✅ Added unique constraint for fingerprint_id\n";
    } else {
        echo "ℹ️ unique constraint for fingerprint_id already exists\n";
    }
    
    $pdo->commit();
    echo "\n🎉 Database migration completed successfully!\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
