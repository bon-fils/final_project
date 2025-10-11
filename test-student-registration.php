<?php
// test-student-registration.php
header('Content-Type: application/json');

// Disable error output to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['reg_no']) || !isset($data['first_name']) || !isset($data['last_name']) || !isset($data['images'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required data'
        ]);
        exit;
    }

    $regNo = trim($data['reg_no']);
    $firstName = trim($data['first_name']);
    $lastName = trim($data['last_name']);
    $images = $data['images'];

    if (empty($regNo) || empty($firstName) || empty($lastName)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required'
        ]);
        exit;
    }

    if (count($images) !== 4) {
        echo json_encode([
            'success' => false,
            'message' => 'Exactly 4 images are required'
        ]);
        exit;
    }

    // Check if student already exists
    $stmt = $pdo->prepare("SELECT id FROM test_students WHERE reg_no = ?");
    $stmt->execute([$regNo]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Student with this registration number already exists'
        ]);
        exit;
    }

    // Create uploads/test directory if it doesn't exist
    $uploadDir = 'uploads/test/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Save images and collect paths
    $imagePaths = [];
    foreach ($images as $imageData) {
        $imageBase64 = $imageData['data'];
        $filename = $imageData['filename'];

        // Remove data URL prefix if present
        if (strpos($imageBase64, 'data:image') === 0) {
            $imageBase64 = explode(',', $imageBase64)[1];
        }

        // Decode and save image
        $imageContent = base64_decode($imageBase64);
        $filepath = $uploadDir . $filename;

        if (file_put_contents($filepath, $imageContent) === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save image: ' . $filename
            ]);
            exit;
        }

        $imagePaths[] = $filepath;
    }

    // Insert student into database
    $stmt = $pdo->prepare("
        INSERT INTO test_students (reg_no, first_name, last_name, face_image_1, face_image_2, face_image_3, face_image_4, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    $stmt->execute([
        $regNo,
        $firstName,
        $lastName,
        $imagePaths[0],
        $imagePaths[1],
        $imagePaths[2],
        $imagePaths[3]
    ]);

    $studentId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Student registered successfully',
        'student' => [
            'id' => $studentId,
            'reg_no' => $regNo,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'face_images' => $imagePaths
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
} catch (Throwable $t) {
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed'
    ]);
}
?>