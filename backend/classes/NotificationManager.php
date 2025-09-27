<?php
/**
 * Notification Management System
 * Handles email, SMS, and in-app notifications
 * Version: 2.0 - Enhanced with multiple notification channels
 */

class NotificationManager {
    private $db;
    private $logger;
    private $emailConfig;
    private $smsConfig;

    // Notification types
    const TYPE_EMAIL = 'email';
    const TYPE_SMS = 'sms';
    const TYPE_IN_APP = 'in_app';
    const TYPE_PUSH = 'push';

    // Notification priorities
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    public function __construct($pdo, $logger = null, $emailConfig = [], $smsConfig = []) {
        $this->db = $pdo;
        $this->logger = $logger;
        $this->emailConfig = $emailConfig;
        $this->smsConfig = $smsConfig;
    }

    /**
     * Send notification
     */
    public function send($type, $recipient, $subject, $message, $options = []) {
        $notificationId = $this->createNotification($type, $recipient, $subject, $message, $options);

        try {
            switch ($type) {
                case self::TYPE_EMAIL:
                    return $this->sendEmail($recipient, $subject, $message, $options);
                case self::TYPE_SMS:
                    return $this->sendSMS($recipient, $message, $options);
                case self::TYPE_IN_APP:
                    return $this->sendInAppNotification($recipient, $subject, $message, $options);
                case self::TYPE_PUSH:
                    return $this->sendPushNotification($recipient, $subject, $message, $options);
                default:
                    throw new Exception("Unsupported notification type: {$type}");
            }
        } catch (Exception $e) {
            $this->updateNotificationStatus($notificationId, 'failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send email notification
     */
    public function sendEmail($to, $subject, $message, $options = []) {
        // Validate email
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address: {$to}");
        }

        $headers = [
            'From: ' . ($this->emailConfig['from_email'] ?? 'noreply@system.com'),
            'Reply-To: ' . ($this->emailConfig['reply_to'] ?? 'noreply@system.com'),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: Student Registration System'
        ];

        // Add CC and BCC if specified
        if (isset($options['cc'])) {
            $headers[] = 'Cc: ' . $options['cc'];
        }

        if (isset($options['bcc'])) {
            $headers[] = 'Bcc: ' . $options['bcc'];
        }

        // Add attachments if specified
        if (isset($options['attachments'])) {
            $message = $this->addAttachmentsToEmail($message, $options['attachments']);
        }

        $success = mail($to, $subject, $message, implode("\r\n", $headers));

        if (!$success) {
            throw new Exception("Failed to send email to: {$to}");
        }

        if ($this->logger) {
            $this->logger->info('NotificationManager', 'Email sent successfully', [
                'to' => $to,
                'subject' => $subject
            ]);
        }

        return true;
    }

    /**
     * Send SMS notification
     */
    public function sendSMS($to, $message, $options = []) {
        // Validate phone number
        $phone = preg_replace('/[^\d+]/', '', $to);
        if (strlen($phone) < 10) {
            throw new Exception("Invalid phone number: {$to}");
        }

        // SMS gateway integration would go here
        // For now, we'll simulate SMS sending
        $simulated = $this->simulateSMS($phone, $message);

        if ($this->logger) {
            $this->logger->info('NotificationManager', 'SMS sent successfully', [
                'to' => $phone,
                'message_length' => strlen($message)
            ]);
        }

        return $simulated;
    }

    /**
     * Send in-app notification
     */
    public function sendInAppNotification($userId, $title, $message, $options = []) {
        $notificationData = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'info',
            'priority' => $options['priority'] ?? self::PRIORITY_MEDIUM,
            'read_status' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if (isset($options['type'])) {
            $notificationData['type'] = $options['type'];
        }

        $sql = "INSERT INTO notifications (user_id, title, message, type, priority, read_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $userId,
            $title,
            $message,
            $notificationData['type'],
            $notificationData['priority'],
            $notificationData['read_status'],
            $notificationData['created_at']
        ]);

        if (!$result) {
            throw new Exception("Failed to create in-app notification");
        }

        if ($this->logger) {
            $this->logger->info('NotificationManager', 'In-app notification created', [
                'user_id' => $userId,
                'title' => $title
            ]);
        }

        return $this->db->lastInsertId();
    }

    /**
     * Send push notification
     */
    public function sendPushNotification($deviceToken, $title, $message, $options = []) {
        // Push notification service integration would go here
        // For now, we'll simulate push notification
        $simulated = $this->simulatePush($deviceToken, $title, $message);

        if ($this->logger) {
            $this->logger->info('NotificationManager', 'Push notification sent', [
                'device_token' => substr($deviceToken, 0, 10) . '...',
                'title' => $title
            ]);
        }

        return $simulated;
    }

    /**
     * Create notification record
     */
    private function createNotification($type, $recipient, $subject, $message, $options = []) {
        $sql = "INSERT INTO notifications_log (type, recipient, subject, message, status, priority, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $type,
            $recipient,
            $subject,
            $message,
            'pending',
            $options['priority'] ?? self::PRIORITY_MEDIUM,
            json_encode($options),
            date('Y-m-d H:i:s')
        ]);

        if (!$result) {
            throw new Exception("Failed to create notification record");
        }

        return $this->db->lastInsertId();
    }

    /**
     * Update notification status
     */
    private function updateNotificationStatus($notificationId, $status, $errorMessage = null) {
        $sql = "UPDATE notifications_log SET status = ?, error_message = ?, updated_at = ? WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $errorMessage, date('Y-m-d H:i:s'), $notificationId]);
    }

    /**
     * Add attachments to email
     */
    private function addAttachmentsToEmail($message, $attachments) {
        // This is a simplified implementation
        // In a real system, you'd use a proper email library like PHPMailer

        $boundary = md5(time());
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $message . "\r\n\r\n";

        // Add attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $fileContent = file_get_contents($attachment);
                $fileName = basename($attachment);

                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: application/octet-stream; name=\"{$fileName}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
                $body .= chunk_split(base64_encode($fileContent)) . "\r\n\r\n";
            }
        }

        $body .= "--{$boundary}--";

        return $body;
    }

    /**
     * Simulate SMS sending (for development/testing)
     */
    private function simulateSMS($phone, $message) {
        // In a real implementation, this would integrate with an SMS gateway
        // For now, we'll just log the SMS details

        $smsData = [
            'to' => $phone,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'simulated'
        ];

        // Log SMS attempt
        if ($this->logger) {
            $this->logger->info('NotificationManager', 'SMS simulation', $smsData);
        }

        return true;
    }

    /**
     * Simulate push notification (for development/testing)
     */
    private function simulatePush($deviceToken, $title, $message) {
        // In a real implementation, this would integrate with push notification services
        // For now, we'll just log the push details

        $pushData = [
            'device_token' => $deviceToken,
            'title' => $title,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'simulated'
        ];

        // Log push attempt
        if ($this->logger) {
            $this->logger->info('NotificationManager', 'Push notification simulation', $pushData);
        }

        return true;
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $limit = 20, $unreadOnly = false) {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];

        if ($unreadOnly) {
            $sql .= " AND read_status = 0";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        $sql = "UPDATE notifications SET read_status = 1, read_at = ? WHERE id = ? AND user_id = ?";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([date('Y-m-d H:i:s'), $notificationId, $userId]);

        if ($this->logger) {
            $this->logger->info('NotificationManager', 'Notification marked as read', [
                'notification_id' => $notificationId,
                'user_id' => $userId
            ]);
        }

        return $result;
    }

    /**
     * Mark all user notifications as read
     */
    public function markAllAsRead($userId) {
        $sql = "UPDATE notifications SET read_status = 1, read_at = ? WHERE user_id = ? AND read_status = 0";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([date('Y-m-d H:i:s'), $userId]);

        if ($this->logger) {
            $this->logger->info('NotificationManager', 'All notifications marked as read', [
                'user_id' => $userId,
                'affected_rows' => $result
            ]);
        }

        return $result;
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats($userId = null) {
        $stats = [];

        if ($userId) {
            // User-specific stats
            $sql = "SELECT
                        COUNT(*) as total,
                        COUNT(CASE WHEN read_status = 0 THEN 1 END) as unread,
                        COUNT(CASE WHEN type = 'info' THEN 1 END) as info_count,
                        COUNT(CASE WHEN type = 'warning' THEN 1 END) as warning_count,
                        COUNT(CASE WHEN type = 'error' THEN 1 END) as error_count,
                        COUNT(CASE WHEN type = 'success' THEN 1 END) as success_count
                    FROM notifications
                    WHERE user_id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // System-wide stats
            $sql = "SELECT
                        COUNT(*) as total_notifications,
                        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
                    FROM notifications_log";

            $stmt = $this->db->query($sql);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $stats;
    }

    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications($days = 30) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Delete old notification logs
        $sql1 = "DELETE FROM notifications_log WHERE created_at < ? AND status != 'pending'";
        $stmt1 = $this->db->prepare($sql1);
        $stmt1->execute([$cutoffDate]);

        // Mark old read notifications as archived
        $sql2 = "UPDATE notifications SET status = 'archived' WHERE read_status = 1 AND read_at < ?";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([$cutoffDate]);

        if ($this->logger) {
            $this->logger->info('NotificationManager', 'Cleaned up old notifications', [
                'days' => $days,
                'log_entries_deleted' => $stmt1->rowCount(),
                'notifications_archived' => $stmt2->rowCount()
            ]);
        }

        return [
            'log_entries_deleted' => $stmt1->rowCount(),
            'notifications_archived' => $stmt2->rowCount()
        ];
    }

    /**
     * Send bulk notifications
     */
    public function sendBulk($type, $recipients, $subject, $message, $options = []) {
        $results = [
            'total' => count($recipients),
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($recipients as $recipient) {
            try {
                $this->send($type, $recipient, $subject, $message, $options);
                $results['sent']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ];
            }
        }

        if ($this->logger) {
            $this->logger->info('NotificationManager', 'Bulk notification completed', $results);
        }

        return $results;
    }
}