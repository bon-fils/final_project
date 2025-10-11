<?php
require_once 'config.php';

// Copy the logging function from test-face-match.php
function logFaceRecognitionResult($pdo, $capturedImage, $studentId, $studentRegNo, $result, $method, $studentType = 'regular') {
    try {
        error_log("LOGGING: Starting to log result for $studentType student $studentRegNo using method $method");

        $imageSize = filesize($capturedImage);
        error_log("LOGGING: Image size: $imageSize bytes");

        $stmt = $pdo->prepare("
            INSERT INTO face_recognition_logs
            (captured_image_path, captured_image_size, student_id, student_reg_no, student_type,
             comparison_score, confidence_level, processing_method, distance,
             pixel_similarity, size_similarity, match_found, processing_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $confidenceLevel = 'low';
        if (($result['score'] ?? 0) > 0.8) $confidenceLevel = 'high';
        else if (($result['score'] ?? 0) > 0.6) $confidenceLevel = 'medium';

        $params = [
            $capturedImage,
            $imageSize,
            $studentId,
            $studentRegNo,
            $studentType,
            $result['score'] ?? 0,
            $confidenceLevel,
            $method,
            $result['distance'] ?? 0,
            $result['pixel_similarity'] ?? $result['size_similarity'] ?? 0,
            $result['size_similarity'] ?? 0,
            $result['match'] ?? false,
            0 // processing_time - could be calculated if needed
        ];

        error_log("LOGGING: Executing insert with params: " . json_encode($params));
        $stmt->execute($params);

        $lastInsertId = $pdo->lastInsertId();
        error_log("LOGGING: Successfully inserted log record with ID: $lastInsertId");

    } catch (Exception $e) {
        error_log("LOGGING ERROR: Failed to log face recognition result: " . $e->getMessage());
        error_log("LOGGING ERROR: Stack trace: " . $e->getTraceAsString());
    }
}

// Test the logging function
echo "Testing face recognition logging...\n";

$result = [
    'score' => 0.75,
    'distance' => 0.25,
    'match' => true
];

logFaceRecognitionResult($pdo, 'test.jpg', 20, 'TEST123', $result, 'test_method', 'regular');

echo "Test completed. Check face_recognition_logs table.\n";

// Check if record was inserted
$stmt = $pdo->query('SELECT COUNT(*) as count FROM face_recognition_logs');
$result = $stmt->fetch();
echo "Records in table: " . $result['count'] . "\n";
?>