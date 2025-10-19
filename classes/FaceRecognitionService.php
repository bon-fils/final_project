<?php
/**
 * Face Recognition Service
 * Handles face recognition processing using Python script
 */

class FaceRecognitionService {
    private $logger;

    public function __construct() {
        $this->logger = new AttendanceSessionLogger();
    }

    /**
     * Process captured image for face recognition
     */
    public function processImage($imageData, $sessionId) {
        try {
            $this->logger->logInfo("Processing face recognition for session: {$sessionId}");

            // Validate input
            if (empty($imageData)) {
                throw new Exception('No image data provided');
            }

            if (!$sessionId || !is_numeric($sessionId)) {
                throw new Exception('Invalid session ID');
            }

            // Create temporary file
            $tempFile = $this->createTempImageFile($imageData);

            try {
                // Execute Python script with the image file as argument
                $result = $this->executePythonScript($tempFile);

                // Log result
                $this->logger->logInfo("Face recognition result: " . json_encode($result));

                // Validate result structure
                if (!is_array($result) || !isset($result['status'])) {
                    throw new Exception('Invalid result structure from face recognition script');
                }

                // Normalize result
                return $this->normalizeResult($result);

            } catch (Exception $e) {
                // Log the error but don't rethrow - return error result instead
                $this->logger->logError("Face recognition processing error: " . $e->getMessage());
                return [
                    'status' => 'error',
                    'message' => 'Face recognition processing failed: ' . $e->getMessage(),
                    'recognized' => false,
                    'student_id' => null,
                    'student_name' => null,
                    'student_reg' => null,
                    'distance' => null,
                    'confidence' => 0,
                    'confidence_level' => 'low',
                    'auto_mark' => false,
                    'requires_confirmation' => false,
                    'faces_detected' => 0,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } finally {
                // Clean up temp file
                $this->cleanupTempFile($tempFile);
            }

        } catch (Exception $e) {
            $this->logger->logError("Face recognition setup error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Face recognition setup failed: ' . $e->getMessage(),
                'recognized' => false,
                'student_id' => null,
                'student_name' => null,
                'student_reg' => null,
                'distance' => null,
                'confidence' => 0,
                'confidence_level' => 'low',
                'auto_mark' => false,
                'requires_confirmation' => false,
                'faces_detected' => 0,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Create temporary image file from base64 data
     */
    private function createTempImageFile($imageData) {
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'face_capture_');
        $imageFile = $tempFile . '.jpg';

        // Remove temp file if it exists
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        // Decode base64 image data
        if (strpos($imageData, 'data:image') === 0) {
            $imageData = explode(',', $imageData)[1];
        }

        $imageBinary = base64_decode($imageData);
        if ($imageBinary === false) {
            throw new Exception('Invalid base64 image data');
        }

        // Save to file
        if (file_put_contents($imageFile, $imageBinary) === false) {
            throw new Exception('Failed to save temporary image file');
        }

        // Set permissions
        chmod($imageFile, 0644);

        return $imageFile;
    }

    /**
     * Execute Python face recognition script
     */
    private function executePythonScript($imageFile) {
        $pythonScript = __DIR__ . '/../face_match.py';

        // Check if Python script exists
        if (!file_exists($pythonScript)) {
            throw new Exception('Face recognition script not found');
        }

        $pythonExecutable = $this->getPythonExecutable();

        // Build command with timeout (30 seconds)
        $command = sprintf(
            'timeout 30s %s %s 2>&1',
            escapeshellcmd($pythonExecutable),
            escapeshellarg($pythonScript)
        );

        // Set environment variables
        $env = $this->getDatabaseEnvironment();

        // Execute command
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            throw new Exception('Failed to start Python face recognition script');
        }

        // Get output and error with timeout
        $output = '';
        $error = '';
        $startTime = time();

        // Read pipes with timeout
        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 1) === false) {
                break;
            }

            foreach ($read as $pipe) {
                if ($pipe === $pipes[1]) {
                    $output .= fread($pipe, 8192);
                } elseif ($pipe === $pipes[2]) {
                    $error .= fread($pipe, 8192);
                }
            }

            // Check for timeout
            if ((time() - $startTime) > 35) {
                proc_terminate($process);
                throw new Exception('Face recognition script timed out');
            }
        }

        // Close pipes
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        // Get exit code
        $exitCode = proc_close($process);

        // Log execution details
        $this->logger->logInfo("Python command executed: {$command}");
        $this->logger->logInfo("Exit code: {$exitCode}");

        if (!empty($error)) {
            $this->logger->logError("Python stderr: {$error}");
        }

        // Check for execution errors
        if ($exitCode !== 0) {
            $errorMsg = !empty($error) ? trim($error) : "Exit code {$exitCode}";
            throw new Exception("Face recognition script failed: {$errorMsg}");
        }

        // Parse JSON output
        $output = trim($output);
        if (empty($output)) {
            throw new Exception('No output from face recognition script');
        }

        $result = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->logError("Invalid JSON output: {$output}");
            throw new Exception('Invalid JSON response from face recognition script: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Get Python executable path
     */
    private function getPythonExecutable() {
        // Check environment variable first
        $configuredPython = getenv('PYTHON_EXECUTABLE');
        if ($configuredPython && $this->isValidPythonExecutable($configuredPython)) {
            return $configuredPython;
        }

        // Try different Python executables
        $executables = ['python3', 'python'];

        foreach ($executables as $exe) {
            if ($this->isValidPythonExecutable($exe)) {
                return $exe;
            }
        }

        // Default fallback
        return 'python3';
    }

    /**
     * Check if Python executable is valid
     */
    private function isValidPythonExecutable($executable) {
        try {
            $command = escapeshellcmd($executable) . ' --version 2>&1';
            $output = shell_exec($command);
            return $output && strpos($output, 'Python') === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get database environment variables
     */
    private function getDatabaseEnvironment() {
        return [
            'DB_HOST=' . (getenv('DB_HOST') ?: 'localhost'),
            'DB_NAME=' . (getenv('DB_NAME') ?: 'rp_attendance_system'),
            'DB_USER=' . (getenv('DB_USER') ?: 'root'),
            'DB_PASS=' . (getenv('DB_PASS') ?: '')
        ];
    }

    /**
     * Normalize result format
     */
    private function normalizeResult($result) {
        return [
            'status' => $result['status'] ?? 'error',
            'message' => $result['message'] ?? 'Face recognition completed',
            'recognized' => in_array($result['status'], ['success', 'low_confidence']),
            'student_id' => $result['student_id'] ?? null,
            'student_name' => $result['student_name'] ?? null,
            'student_reg' => $result['student_reg'] ?? null,
            'distance' => $result['distance'] ?? null,
            'confidence' => $result['confidence'] ?? 0,
            'confidence_level' => $this->getConfidenceLevel($result['status'] ?? 'error'),
            'auto_mark' => ($result['status'] ?? 'error') === 'success',
            'requires_confirmation' => ($result['status'] ?? 'error') === 'low_confidence',
            'faces_detected' => $result['faces_detected'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get confidence level description
     */
    private function getConfidenceLevel($status) {
        switch ($status) {
            case 'success':
                return 'high';
            case 'low_confidence':
                return 'medium';
            default:
                return 'low';
        }
    }

    /**
     * Clean up temporary file
     */
    private function cleanupTempFile($filePath) {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}