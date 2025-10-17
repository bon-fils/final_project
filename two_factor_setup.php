<?php
/**
 * Two-Factor Authentication Setup Page
 * Allows users to enable/disable 2FA for their accounts
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_once "two_factor_auth.php";

checkAuthentication();

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
$twoFA = new TwoFactorAuth($pdo);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enable_2fa') {
        $code = $_POST['code'] ?? '';
        
        if ($twoFA->verifyCode($user_id, $code)) {
            if ($twoFA->enable2FA($user_id)) {
                $backupCodes = $twoFA->generateBackupCodes($user_id);
                $message = '2FA enabled successfully! Please save your backup codes.';
                $messageType = 'success';
                $showBackupCodes = $backupCodes;
            } else {
                $message = 'Failed to enable 2FA. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid verification code. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'disable_2fa') {
        $code = $_POST['code'] ?? '';
        
        if ($twoFA->verifyCode($user_id, $code) || $twoFA->verifyBackupCode($user_id, $code)) {
            if ($twoFA->disable2FA($user_id)) {
                $message = '2FA disabled successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to disable 2FA. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid verification code. Please try again.';
            $messageType = 'error';
        }
    }
}

// Get current 2FA status
$is2FAEnabled = $twoFA->is2FAEnabled($user_id);
$secret = $twoFA->getUserSecret($user_id);
$qrCodeUrl = $secret ? $twoFA->getQRCodeUrl($user_id, $email) : '';

// Create 2FA table if it doesn't exist
$twoFA->createTable();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication Setup | RP Attendance System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .backup-codes {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .backup-code {
            font-family: monospace;
            font-size: 1.1em;
            font-weight: bold;
            color: #495057;
            margin: 5px 0;
        }
        
        .step {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
        .step-number {
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>
                            Two-Factor Authentication Setup
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($showBackupCodes)): ?>
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Important: Save Your Backup Codes</h5>
                                <p>These backup codes can be used to access your account if you lose your authenticator device. Store them in a safe place!</p>
                                <div class="backup-codes">
                                    <?php foreach ($showBackupCodes as $code): ?>
                                        <div class="backup-code"><?php echo htmlspecialchars($code); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <button class="btn btn-outline-primary btn-sm" onclick="printBackupCodes()">
                                    <i class="fas fa-print me-1"></i>Print Backup Codes
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is2FAEnabled): ?>
                            <!-- 2FA is enabled -->
                            <div class="text-center mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                <h4 class="text-success mt-3">Two-Factor Authentication is Enabled</h4>
                                <p class="text-muted">Your account is protected with 2FA. You'll need to enter a code from your authenticator app when logging in.</p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#disable2FAModal">
                                    <i class="fas fa-times me-2"></i>Disable Two-Factor Authentication
                                </button>
                            </div>
                            
                        <?php else: ?>
                            <!-- 2FA setup -->
                            <div class="step">
                                <h5>
                                    <span class="step-number">1</span>
                                    Install an Authenticator App
                                </h5>
                                <p>Download and install an authenticator app on your mobile device:</p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fab fa-google-play me-2"></i>
                                            <strong>Google Authenticator</strong>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-mobile-alt me-2"></i>
                                            <strong>Microsoft Authenticator</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-shield-alt me-2"></i>
                                            <strong>Authy</strong>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-lock me-2"></i>
                                            <strong>1Password</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="step">
                                <h5>
                                    <span class="step-number">2</span>
                                    Scan QR Code
                                </h5>
                                <p>Open your authenticator app and scan this QR code:</p>
                                
                                <?php if ($qrCodeUrl): ?>
                                    <div class="qr-code">
                                        <div id="qrcode"></div>
                                        <p class="mt-3 text-muted">
                                            <small>Or manually enter this secret key:</small><br>
                                            <code><?php echo htmlspecialchars($secret); ?></code>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center">
                                        <button class="btn btn-primary" onclick="generateSecret()">
                                            <i class="fas fa-qrcode me-2"></i>Generate QR Code
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="step">
                                <h5>
                                    <span class="step-number">3</span>
                                    Verify Setup
                                </h5>
                                <p>Enter the 6-digit code from your authenticator app to verify the setup:</p>
                                
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="enable_2fa">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control form-control-lg text-center" 
                                               name="code" placeholder="000000" maxlength="6" 
                                               pattern="[0-9]{6}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-success btn-lg w-100">
                                            <i class="fas fa-check me-2"></i>Enable 2FA
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="admin-dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Disable 2FA Modal -->
    <div class="modal fade" id="disable2FAModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Disable Two-Factor Authentication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Disabling 2FA will make your account less secure.
                    </div>
                    <p>Enter a verification code from your authenticator app or a backup code to disable 2FA:</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="disable_2fa">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="code" 
                                   placeholder="Enter 6-digit code or backup code" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times me-2"></i>Disable 2FA
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- QR Code Generator -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <script>
        <?php if ($qrCodeUrl): ?>
        // Generate QR Code
        QRCode.toCanvas(document.getElementById('qrcode'), '<?php echo $qrCodeUrl; ?>', {
            width: 200,
            height: 200,
            margin: 2
        });
        <?php endif; ?>
        
        function generateSecret() {
            window.location.href = '?generate_secret=1';
        }
        
        function printBackupCodes() {
            const backupCodes = document.querySelector('.backup-codes');
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Backup Codes - RP Attendance System</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            .backup-code { font-family: monospace; font-size: 1.2em; margin: 10px 0; }
                            h1 { color: #007bff; }
                        </style>
                    </head>
                    <body>
                        <h1>Backup Codes - RP Attendance System</h1>
                        <p><strong>Important:</strong> Store these codes in a safe place. Each code can only be used once.</p>
                        ${backupCodes.innerHTML}
                        <p><small>Generated on: ${new Date().toLocaleString()}</small></p>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Auto-focus on code input
        document.querySelector('input[name="code"]')?.focus();
        
        // Format code input
        document.querySelectorAll('input[name="code"]').forEach(input => {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });
    </script>
</body>
</html>
