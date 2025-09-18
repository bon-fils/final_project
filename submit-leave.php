<?php
session_start();
require_once "config.php"; // $pdo

// Ensure student is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// Resolve student_id from user_id
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    header("Location: login.php");
    exit;
}
$student_id = (int)$student['id'];

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: request-leave.php");
    exit;
}

// Basic validation
$requestTo = trim($_POST['requestTo'] ?? '');
$courseId  = trim($_POST['courseId'] ?? '');
$fromDate  = trim($_POST['fromDate'] ?? '');
$toDate    = trim($_POST['toDate'] ?? '');
$reason    = trim($_POST['reason'] ?? '');

if ($requestTo === '' || $fromDate === '' || $toDate === '' || $reason === '') {
    header("Location: request-leave.php?error=missing_fields");
    exit;
}

// Append structured info into reason since DB schema lacks date/course columns
$structuredReason = $reason;
$structuredReason .= "\n-- Details --\n";
$structuredReason .= "From: " . $fromDate . "\n";
$structuredReason .= "To: " . $toDate . "\n";
if ($requestTo === 'lecturer' && $courseId !== '') {
    $structuredReason .= "Course ID: " . $courseId . "\n";
}
$structuredReason .= "Requested To: " . ($requestTo === 'hod' ? 'HoD' : 'Lecturer') . "\n";

// Handle file upload (optional)
$uploadedName = null;
if (isset($_FILES['supportingFile']) && is_uploaded_file($_FILES['supportingFile']['tmp_name'])) {
    $file = $_FILES['supportingFile'];

    // Validate size (<= 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        header("Location: request-leave.php?error=file_too_large");
        exit;
    }

    // Validate MIME
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/jpeg' => 'jpg',
        'image/png'  => 'png'
    ];
    if (!isset($allowed[$mime])) {
        header("Location: request-leave.php?error=invalid_file_type");
        exit;
    }

    // Ensure directory exists
    $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'leave_docs';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }

    // Generate unique name
    $ext = $allowed[$mime];
    $uploadedName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $uploadedName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        header("Location: request-leave.php?error=upload_failed");
        exit;
    }
}

// Insert into DB
$ins = $pdo->prepare("INSERT INTO leave_requests (student_id, reason, supporting_file, status) VALUES (:sid, :reason, :file, 'pending')");
$ins->execute([
    'sid'    => $student_id,
    'reason' => $structuredReason,
    'file'   => $uploadedName
]);

header("Location: leave-status.php?success=1");
exit;
 
