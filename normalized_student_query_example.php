<?php
/**
 * Example PHP script demonstrating normalized queries
 * after removing duplicate fields from students table
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
        $config['user'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>RP Attendance System - Normalized Student Queries</h1>";

    // Example 1: Get complete student information with user data
    echo "<h2>1. Complete Student Information (Normalized)</h2>";
    $stmt = $pdo->prepare("
        SELECT
            s.id as student_id,
            s.reg_no,
            s.year_level,
            s.cell,
            s.sector,
            s.district,
            s.province,
            s.parent_first_name,
            s.parent_last_name,
            s.parent_contact,
            s.status as student_status,
            s.fingerprint_quality,
            s.student_photos,
            u.first_name,
            u.last_name,
            u.phone,
            u.sex,
            u.dob,
            u.photo as user_photo,
            u.email,
            u.role
        FROM students s
        JOIN users u ON s.user_id = u.id
        ORDER BY s.reg_no
    ");

    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $student) {
        echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
        echo "<h3>Student: {$student['reg_no']} - {$student['first_name']} {$student['last_name']}</h3>";

        echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 10px;'>";
        echo "<div><strong>Academic Info:</strong><br>";
        echo "Year Level: {$student['year_level']}<br>";
        echo "Status: {$student['student_status']}<br>";
        echo "Registration: {$student['reg_no']}</div>";

        echo "<div><strong>Personal Info:</strong><br>";
        echo "Phone: " . ($student['phone'] ?: 'N/A') . "<br>";
        echo "Sex: " . ($student['sex'] ?: 'N/A') . "<br>";
        echo "DOB: " . ($student['dob'] ?: 'N/A') . "</div>";

        echo "<div><strong>Location:</strong><br>";
        echo "Cell: " . ($student['cell'] ?: 'N/A') . "<br>";
        echo "Sector: " . ($student['sector'] ?: 'N/A') . "<br>";
        echo "District: {$student['district']} Province: {$student['province']}</div>";

        echo "<div><strong>Parent Contact:</strong><br>";
        echo "Name: " . ($student['parent_first_name'] ?: 'N/A') . " " . ($student['parent_last_name'] ?: 'N/A') . "<br>";
        echo "Contact: " . ($student['parent_contact'] ?: 'N/A') . "</div>";
        echo "</div>";

        // Check for biometric data
        if ($student['student_photos']) {
            $biometricData = json_decode($student['student_photos'], true);
            if ($biometricData && isset($biometricData['biometric_data'])) {
                $bio = $biometricData['biometric_data'];
                echo "<div style='margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;'>";
                echo "<strong>Biometric Data:</strong> " . implode(', ', $bio['biometric_types']) . "<br>";
                if ($bio['face_templates_count'] > 0) {
                    echo "Face Images: {$bio['face_templates_count']} (Quality: {$bio['face_quality_average']})<br>";
                }
                if ($bio['fingerprint_quality'] > 0) {
                    echo "Fingerprint Quality: {$bio['fingerprint_quality']}<br>";
                }
                echo "</div>";
            }
        } else {
            echo "<div style='margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 5px;'>";
            echo "<strong>No Biometric Data</strong>";
            echo "</div>";
        }

        echo "</div>";
    }

    // Example 2: Students with biometric data only
    echo "<h2>2. Students with Biometric Data</h2>";
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.reg_no,
            u.first_name,
            u.last_name,
            JSON_EXTRACT(s.student_photos, '$.biometric_data.biometric_types') as biometric_types,
            JSON_EXTRACT(s.student_photos, '$.biometric_data.face_templates_count') as face_count,
            JSON_EXTRACT(s.student_photos, '$.biometric_data.fingerprint_quality') as fp_quality
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.student_photos IS NOT NULL
        AND JSON_EXTRACT(s.student_photos, '$.biometric_data.has_biometric_data') = true
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $biometricTypes = json_decode($row['biometric_types'], true);
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0;'>";
        echo "<strong>{$row['reg_no']}</strong> - {$row['first_name']} {$row['last_name']}<br>";
        echo "Biometric Types: " . implode(', ', $biometricTypes) . "<br>";
        echo "Face Images: {$row['face_count']} | Fingerprint Quality: {$row['fp_quality']}";
        echo "</div>";
    }

    // Example 3: Statistics
    echo "<h2>3. Database Statistics</h2>";
    $stats = $pdo->query("
        SELECT
            COUNT(*) as total_students,
            SUM(CASE WHEN s.student_photos IS NOT NULL AND JSON_EXTRACT(s.student_photos, '$.biometric_data.has_biometric_data') = true THEN 1 ELSE 0 END) as with_biometric,
            SUM(JSON_EXTRACT(s.student_photos, '$.biometric_data.face_templates_count')) as total_face_images,
            SUM(CASE WHEN JSON_EXTRACT(s.student_photos, '$.biometric_data.fingerprint_quality') > 0 THEN 1 ELSE 0 END) as with_fingerprints
        FROM students s
    ")->fetch(PDO::FETCH_ASSOC);

    echo "<div style='background: #e9ecef; padding: 15px; border-radius: 8px;'>";
    echo "<strong>Total Students:</strong> {$stats['total_students']}<br>";
    echo "<strong>With Biometric Data:</strong> {$stats['with_biometric']} (" . round(($stats['with_biometric']/$stats['total_students'])*100, 1) . "%)<br>";
    echo "<strong>Total Face Images:</strong> {$stats['total_face_images']}<br>";
    echo "<strong>With Fingerprints:</strong> {$stats['with_fingerprints']}<br>";
    echo "</div>";

    // Example 4: Search by registration number
    echo "<h2>4. Search Student by Registration Number</h2>";
    $searchRegNo = '22RP08976'; // Example search
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            u.first_name,
            u.last_name,
            u.phone,
            u.sex,
            u.dob,
            u.photo
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.reg_no = ?
    ");
    $stmt->execute([$searchRegNo]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        echo "<div style='border: 1px solid #28a745; padding: 15px; background: #d4edda; border-radius: 8px;'>";
        echo "<h4>Found Student: {$student['reg_no']}</h4>";
        echo "<strong>Name:</strong> {$student['first_name']} {$student['last_name']}<br>";
        echo "<strong>Phone:</strong> {$student['phone']}<br>";
        echo "<strong>Year Level:</strong> {$student['year_level']}<br>";
        echo "<strong>Status:</strong> {$student['status']}<br>";

        if ($student['student_photos']) {
            $bioData = json_decode($student['student_photos'], true);
            if (isset($bioData['biometric_data']['face_images'])) {
                $faceImages = $bioData['biometric_data']['face_images'];
                echo "<strong>Face Images:</strong> " . count($faceImages) . " images available<br>";
            }
        }
        echo "</div>";
    } else {
        echo "<div style='border: 1px solid #dc3545; padding: 15px; background: #f8d7da; border-radius: 8px;'>";
        echo "Student with registration number '$searchRegNo' not found.";
        echo "</div>";
    }

} catch (PDOException $e) {
    echo "<h2>Error</h2>";
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>