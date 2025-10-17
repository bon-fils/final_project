<?php
/**
 * Enhanced File Upload Handler
 * Secure file upload with comprehensive validation
 * Version: 1.0
 */

class FileUploadHandler {
    private $allowedTypes;
    private $maxSize;
    private $uploadPath;
    private $logger;
    
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
        ];
        $this->maxSize = 5 * 1024 * 1024; // 5MB default
        $this->uploadPath = 'uploads/';
    }
    
    /**
     * Set allowed file types
     */
    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
    }
    
    /**
     * Set maximum file size
     */
    public function setMaxSize($size) {
        $this->maxSize = $size;
    }
    
    /**
     * Set upload path
     */
    public function setUploadPath($path) {
        $this->uploadPath = rtrim($path, '/') . '/';
    }
    
    /**
     * Handle file upload with comprehensive validation
     */
    public function handleUpload($file, $subfolder = '') {
        try {
            // Validate file exists
            if (!isset($file) || !is_array($file)) {
                throw new Exception('No file provided');
            }
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception($this->getUploadErrorMessage($file['error']));
            }
            
            // Validate file size
            if ($file['size'] > $this->maxSize) {
                throw new Exception('File too large. Maximum size: ' . $this->formatBytes($this->maxSize));
            }
            
            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!array_key_exists($mimeType, $this->allowedTypes)) {
                throw new Exception('Invalid file type. Allowed types: ' . implode(', ', array_values($this->allowedTypes)));
            }
            
            // Validate file extension
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, array_values($this->allowedTypes))) {
                throw new Exception('Invalid file extension');
            }
            
            // Scan for malicious content
            if ($this->containsMaliciousContent($file['tmp_name'])) {
                throw new Exception('File contains potentially malicious content');
            }
            
            // Generate secure filename
            $filename = $this->generateSecureFilename($file['name'], $extension);
            
            // Create upload directory
            $uploadDir = $this->uploadPath . $subfolder;
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }
            
            // Move uploaded file
            $filepath = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Set secure permissions
            chmod($filepath, 0644);
            
            $this->log('info', 'File uploaded successfully', [
                'filename' => $filename,
                'size' => $file['size'],
                'type' => $mimeType
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => $file['size'],
                'type' => $mimeType
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'File upload failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check for malicious content in uploaded files
     */
    private function containsMaliciousContent($filePath) {
        $content = file_get_contents($filePath);
        
        // Check for common malicious patterns
        $maliciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\(/i',
            '/base64_decode/i',
            '/exec\(/i',
            '/system\(/i',
            '/shell_exec/i'
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename($originalName, $extension) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        
        return $safeName . '_' . $timestamp . '_' . $random . '.' . $extension;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $messages[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Logging helper
     */
    private function log($level, $message, $context = []) {
        if ($this->logger) {
            $this->logger->$level('FileUploadHandler', $message, $context);
        }
    }
    
    /**
     * Validate image dimensions (for image files)
     */
    public function validateImageDimensions($filePath, $maxWidth = 2000, $maxHeight = 2000) {
        $imageInfo = getimagesize($filePath);
        
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height) = $imageInfo;
        
        return $width <= $maxWidth && $height <= $maxHeight;
    }
    
    /**
     * Generate thumbnail for images
     */
    public function generateThumbnail($sourcePath, $thumbPath, $maxWidth = 200, $maxHeight = 200) {
        try {
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                return false;
            }
            
            list($width, $height, $type) = $imageInfo;
            
            // Calculate thumbnail dimensions
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $thumbWidth = intval($width * $ratio);
            $thumbHeight = intval($height * $ratio);
            
            // Create source image
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                default:
                    return false;
            }
            
            // Create thumbnail
            $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
            
            // Preserve transparency for PNG and GIF
            if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                imagealphablending($thumbImage, false);
                imagesavealpha($thumbImage, true);
                $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
                imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
            }
            
            // Resize image
            imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
            
            // Save thumbnail
            $result = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $result = imagejpeg($thumbImage, $thumbPath, 85);
                    break;
                case IMAGETYPE_PNG:
                    $result = imagepng($thumbImage, $thumbPath, 8);
                    break;
                case IMAGETYPE_GIF:
                    $result = imagegif($thumbImage, $thumbPath);
                    break;
            }
            
            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($thumbImage);
            
            return $result;
            
        } catch (Exception $e) {
            $this->log('error', 'Thumbnail generation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old files
     */
    public function cleanupOldFiles($days = 30) {
        try {
            $cutoffTime = time() - ($days * 24 * 60 * 60);
            $uploadDir = $this->uploadPath;
            
            if (!is_dir($uploadDir)) {
                return false;
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            $deletedCount = 0;
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                    if (unlink($file->getRealPath())) {
                        $deletedCount++;
                    }
                }
            }
            
            $this->log('info', "Cleaned up {$deletedCount} old files");
            return $deletedCount;
            
        } catch (Exception $e) {
            $this->log('error', 'File cleanup failed: ' . $e->getMessage());
            return false;
        }
    }
}
