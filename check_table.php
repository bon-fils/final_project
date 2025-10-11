<?php
require_once 'config.php';

echo "Face recognition logs table structure:\n";
$stmt = $pdo->query('DESCRIBE face_recognition_logs');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>