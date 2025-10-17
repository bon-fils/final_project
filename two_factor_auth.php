<?php
/**
 * Two-Factor Authentication (2FA) Manager
 * TOTP-based 2FA implementation for enhanced security
 * Version: 1.0
 */

class TwoFactorAuth {
    private $pdo;
    private $logger;
    private $issuer = 'RP Attendance System';
    
    public function __construct($pdo, $logger = null) {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }
    
    /**
     * Generate a new secret key for a user
     */
    public function generateSecret($userId) {
        $secret = $this->generateRandomSecret();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_2fa (user_id, secret, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                secret = VALUES(secret), 
                updated_at = NOW()
            ");
            $stmt->execute([$userId, $secret]);
            
            $this->log('info', '2FA secret generated for user', ['user_id' => $userId]);
            
            return $secret;
            
        } catch (PDOException $e) {
            $this->log('error', 'Failed to generate 2FA secret: ' . $e->getMessage());
            throw new Exception('Failed to generate 2FA secret');
        }
    }
    
    /**
     * Get user's 2FA secret
     */
    public function getUserSecret($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT secret FROM user_2fa WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['secret'] : null;
            
        } catch (PDOException $e) {
            $this->log('error', 'Failed to get user 2FA secret: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate QR code URL for authenticator app
     */
    public function getQRCodeUrl($userId, $email) {
        $secret = $this->getUserSecret($userId);
        
        if (!$secret) {
            $secret = $this->generateSecret($userId);
        }
        
        $label = urlencode($email);
        $issuer = urlencode($this->issuer);
        
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";
    }
    
    /**
     * Verify TOTP code
     */
    public function verifyCode($userId, $code) {
        $secret = $this->getUserSecret($userId);
        
        if (!$secret) {
            return false;
        }
        
        // Allow codes from current and previous/next 30-second windows
        $timeStep = 30;
        $currentTime = floor(time() / $timeStep);
        
        for ($i = -1; $i <= 1; $i++) {
            $time = $currentTime + $i;
            $expectedCode = $this->generateTOTP($secret, $time);
            
            if (hash_equals($expectedCode, $code)) {
                $this->log('info', '2FA code verified successfully', ['user_id' => $userId]);
                return true;
            }
        }
        
        $this->log('warning', '2FA code verification failed', ['user_id' => $userId]);
        return false;
    }
    
    /**
     * Enable 2FA for a user
     */
    public function enable2FA($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_2fa 
                SET enabled = 1, enabled_at = NOW(), updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            $this->log('info', '2FA enabled for user', ['user_id' => $userId]);
            
            return true;
            
        } catch (PDOException $e) {
            $this->log('error', 'Failed to enable 2FA: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable 2FA for a user
     */
    public function disable2FA($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_2fa 
                SET enabled = 0, enabled_at = NULL, updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            $this->log('info', '2FA disabled for user', ['user_id' => $userId]);
            
            return true;
            
        } catch (PDOException $e) {
            $this->log('error', 'Failed to disable 2FA: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if 2FA is enabled for a user
     */
    public function is2FAEnabled($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT enabled FROM user_2fa 
                WHERE user_id = ? AND enabled = 1
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? true : false;
            
        } catch (PDOException $e) {
            $this->log('error', 'Failed to check 2FA status: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate backup codes for user
     */
    public function generateBackupCodes($userId) {
        $codes = [];
        
        for ($i = 0; $i < 10; $i++) {
            $codes[] = $this->generateRandomCode();
        }
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_2fa 
                SET backup_codes = ?, updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([json_encode($codes), $userId]);
            
            $this->log('info', 'Backup codes generated for user', ['user_id' => $userId]);
            
            return $codes;
            
        } catch (PDOException $e) {
            $this->log('error', 'Failed to generate backup codes: ' . $e->getMessage());
            throw new Exception('Failed to generate backup codes');
        }
    }
    
    /**
     * Verify backup code
     */
    public function verifyBackupCode($userId, $code) {
        try {
            $stmt = $this->pdo->prepare("SELECT backup_codes FROM user_2fa WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || !$result['backup_codes']) {
                return false;
            }
            
            $backupCodes = json_decode($result['backup_codes'], true);
            
            if (in_array($code, $backupCodes)) {
                // Remove used backup code
                $backupCodes = array_diff($backupCodes, [$code]);
                
                $stmt = $this->pdo->prepare("
                    UPDATE user_2fa 
                    SET backup_codes = ?, updated_at = NOW() 
                    WHERE user_id = ?
                ");
                $stmt->execute([json_encode(array_values($backupCodes)), $userId]);
                
                $this->log('info', 'Backup code used successfully', ['user_id' => $userId]);
                
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            $this->log('error', 'Failed to verify backup code: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate TOTP code
     */
    private function generateTOTP($secret, $time) {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate random secret
     */
    private function generateRandomSecret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $secret;
    }
    
    /**
     * Generate random backup code
     */
    private function generateRandomCode() {
        return strtoupper(bin2hex(random_bytes(4)));
    }
    
    /**
     * Base32 decode
     */
    private function base32Decode($input) {
        $map = [
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
            'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
        ];
        
        $input = strtoupper($input);
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            if (!isset($map[$char])) {
                continue;
            }
            
            $v <<= 5;
            $v += $map[$char];
            $vbits += 5;
            
            if ($vbits >= 8) {
                $output .= chr(($v >> ($vbits - 8)) & 255);
                $vbits -= 8;
            }
        }
        
        return $output;
    }
    
    /**
     * Logging helper
     */
    private function log($level, $message, $context = []) {
        if ($this->logger) {
            $this->logger->$level('TwoFactorAuth', $message, $context);
        }
    }
    
    /**
     * Create 2FA table if it doesn't exist
     */
    public function createTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS user_2fa (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL UNIQUE,
                    secret VARCHAR(32) NOT NULL,
                    enabled BOOLEAN DEFAULT FALSE,
                    enabled_at DATETIME NULL,
                    backup_codes TEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user_id (user_id),
                    INDEX idx_enabled (enabled)
                )
            ";
            
            $this->pdo->exec($sql);
            
            $this->log('info', '2FA table created successfully');
            
            return true;
            
        } catch (PDOException $e) {
            $this->log('error', 'Failed to create 2FA table: ' . $e->getMessage());
            return false;
        }
    }
}
