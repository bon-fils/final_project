<?php
require_once 'config.php';

echo "Checking students in the UNION query...\n";

$sql = "
    SELECT 'regular' as type, id, reg_no, student_photos as face_data
    FROM students
    WHERE status = 'active' AND student_photos IS NOT NULL

    UNION ALL

    SELECT 'test' as type, id, reg_no,
           CONCAT('{\"face_images\":[\"', face_image_1, '\",\"', face_image_2, '\",\"', face_image_3, '\",\"', face_image_4, '\"]}') as face_data
    FROM test_students
    WHERE status = 'active'
";

$result = $pdo->query($sql);

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['type'] . ' - ID: ' . $row['id'] . ', Reg: ' . $row['reg_no'] . "\n";
}

echo "\nChecking test_students table directly...\n";
$stmt = $pdo->query('SELECT id, reg_no FROM test_students');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo 'Test Student - ID: ' . $row['id'] . ', Reg: ' . $row['reg_no'] . "\n";
}
?>