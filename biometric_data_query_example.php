<?php
/**
 * Example PHP script demonstrating how to query biometric data
 * from the enhanced students table with embedded JSON
 */

// Database configuration
$config = [
    'host' => 'localhost',
    'dbname' => 'rp_attendance_system',
    'user' => 'root',
    'password' => ''
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>RP Attendance System - Biometric Data Query Examples</h1>";

    // Example 1: Get all students with biometric data
    echo "<h2>1. Students with Biometric Data</h2>";
    $stmt = $pdo->query("
        SELECT id, reg_no, student_photos
        FROM students
        WHERE student_photos IS NOT NULL
        AND JSON_EXTRACT(student_photos, '$.biometric_data.has_biometric_data') = true
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $biometricData = json_decode($row['student_photos'], true);
        $bio = $biometricData['biometric_data'];

        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Student ID:</strong> {$row['id']} | <strong>Reg No:</strong> {$row['reg_no']}<br>";
        echo "<strong>Biometric Types:</strong> " . implode(', ', $bio['biometric_types']) . "<br>";
        echo "<strong>Face Images:</strong> {$bio['face_templates_count']} | <strong>Fingerprint Quality:</strong> {$bio['fingerprint_quality']}<br>";
        echo "</div>";
    }

    // Example 2: Get face images for a specific student
    echo "<h2>2. Face Images for Student 16</h2>";
    $stmt = $pdo->prepare("
        SELECT
            reg_no,
            JSON_EXTRACT(student_photos, '$.biometric_data.face_images') as face_images,
            JSON_EXTRACT(student_photos, '$.biometric_data.face_quality_average') as avg_quality
        FROM students
        WHERE id = ?
    ");
    $stmt->execute([16]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $faceImages = json_decode($student['face_images'], true);
        echo "<strong>Student:</strong> {$student['reg_no']}<br>";
        echo "<strong>Average Quality:</strong> {$student['avg_quality']}<br>";
        echo "<strong>Face Images:</strong><br>";

        foreach ($faceImages as $image) {
            echo "- {$image['template_id']}: {$image['image_path']} (Quality: {$image['quality_score']})<br>";
        }
    }

    // Example 3: Get fingerprint data
    echo "<h2>3. Fingerprint Data</h2>";
    $stmt = $pdo->query("
        SELECT
            id,
            reg_no,
            JSON_EXTRACT(student_photos, '$.biometric_data.fingerprint') as fingerprint_data,
            JSON_EXTRACT(student_photos, '$.biometric_data.fingerprint_quality') as quality
        FROM students
        WHERE JSON_EXTRACT(student_photos, '$.biometric_data.fingerprint') IS NOT NULL
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fingerprint = json_decode($row['fingerprint_data'], true);
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Student:</strong> {$row['reg_no']} (ID: {$row['id']})<br>";
        echo "<strong>Fingerprint Path:</strong> {$fingerprint['path']}<br>";
        echo "<strong>Quality Score:</strong> {$fingerprint['quality_score']}<br>";
        echo "<strong>Finger Type:</strong> {$fingerprint['finger_type']}<br>";
        echo "<strong>Capture Device:</strong> {$fingerprint['capture_device']}<br>";
        echo "</div>";
    }

    // Example 4: Count biometric data statistics
    echo "<h2>4. Biometric Data Statistics</h2>";
    $stats = $pdo->query("
        SELECT
            COUNT(*) as total_students,
            SUM(CASE WHEN student_photos IS NOT NULL AND JSON_EXTRACT(student_photos, '$.biometric_data.has_biometric_data') = true THEN 1 ELSE 0 END) as with_biometric,
            SUM(JSON_EXTRACT(student_photos, '$.biometric_data.face_templates_count')) as total_face_images,
            SUM(CASE WHEN JSON_EXTRACT(student_photos, '$.biometric_data.fingerprint') IS NOT NULL THEN 1 ELSE 0 END) as with_fingerprints
        FROM students
    ")->fetch(PDO::FETCH_ASSOC);

    echo "<strong>Total Students:</strong> {$stats['total_students']}<br>";
    echo "<strong>With Biometric Data:</strong> {$stats['with_biometric']} (" . round(($stats['with_biometric']/$stats['total_students'])*100, 1) . "%)<br>";
    echo "<strong>Total Face Images:</strong> {$stats['total_face_images']}<br>";
    echo "<strong>With Fingerprints:</strong> {$stats['with_fingerprints']}<br>";

    // Example 5: Search students by biometric criteria
    echo "<h2>5. Advanced Biometric Queries</h2>";

    // Find students with high-quality face images
    echo "<h3>Students with High-Quality Face Images (>0.8)</h3>";
    $stmt = $pdo->query("
        SELECT id, reg_no,
               JSON_EXTRACT(student_photos, '$.biometric_data.face_quality_average') as avg_quality
        FROM students
        WHERE JSON_EXTRACT(student_photos, '$.biometric_data.face_quality_average') > 0.8
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Student {$row['reg_no']}: Quality {$row['avg_quality']}<br>";
    }

    // Find students with fingerprints
    echo "<h3>Students with Fingerprint Data</h3>";
    $stmt = $pdo->query("
        SELECT id, reg_no,
               JSON_EXTRACT(student_photos, '$.biometric_data.fingerprint.quality_score') as fp_quality
        FROM students
        WHERE JSON_EXTRACT(student_photos, '$.biometric_data.fingerprint') IS NOT NULL
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Student {$row['reg_no']}: Fingerprint Quality {$row['fp_quality']}<br>";
    }

} catch (PDOException $e) {
    echo "<h2>Error</h2>";
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>