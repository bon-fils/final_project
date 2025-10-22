<?php
/**
 * Emergency tool to close all active sessions
 * USE ONLY FOR DEVELOPMENT/TESTING
 */

require_once "config.php";

try {
    $stmt = $pdo->prepare("
        UPDATE attendance_sessions 
        SET status = 'completed', 
            end_time = NOW() 
        WHERE status = 'active'
    ");
    
    $stmt->execute();
    $count = $stmt->rowCount();
    
    echo "✅ Closed $count active session(s).<br>";
    echo "<a href='attendance-session.php'>Go to Attendance Session</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
