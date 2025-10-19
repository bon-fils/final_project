<?php
/**
 * Fee Payments - Student Portal
 * View and manage fee payments and outstanding balances
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['student']);

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// Get student info
$stmt = $pdo->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    header("Location: login.php");
    exit;
}

// Get fee payment data
try {
    // Outstanding balance
    $stmt = $pdo->prepare("SELECT outstanding_balance FROM student_fees WHERE student_id = ?");
    $stmt->execute([$student['id']]);
    $outstanding_balance = $stmt->fetch(PDO::FETCH_ASSOC)['outstanding_balance'] ?? 0;

    // Recent payments
    $stmt = $pdo->prepare("
        SELECT * FROM fee_payments
        WHERE student_id = ?
        ORDER BY payment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$student['id']]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fee breakdown
    $stmt = $pdo->prepare("
        SELECT fee_type, amount, due_date, status
        FROM student_fee_breakdown
        WHERE student_id = ?
        ORDER BY due_date ASC
    ");
    $stmt->execute([$student['id']]);
    $fee_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $outstanding_balance = 0;
    $recent_payments = [];
    $fee_breakdown = [];
}

// Calculate totals
$total_paid = array_sum(array_column($recent_payments, 'amount'));
$total_fees = array_sum(array_column($fee_breakdown, 'amount'));
$pending_fees = array_sum(array_filter(array_column($fee_breakdown, 'amount'), function($amount, $index) use ($fee_breakdown) {
    return $fee_breakdown[$index]['status'] === 'pending';
}, ARRAY_FILTER_USE_BOTH));

// Set user role for frontend compatibility
$userRole = 'student';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Payments | Student | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #0066cc;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background-color: #343a40;
            color: white;
            padding: 20px 0;
        }

        .sidebar a {
            display: block;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
        }

        .sidebar a.active {
            background-color: #007bff;
        }

        .topbar {
            margin-left: var(--sidebar-width);
            background-color: white;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .balance-card {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }

        .balance-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .balance-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }

        .payment-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
            border-left: 4px solid var(--primary-color);
        }

        .payment-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 15px;
        }

        .payment-title {
            font-weight: 600;
            margin: 0;
        }

        .payment-date {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        .payment-body {
            padding: 15px;
        }

        .fee-breakdown {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .footer {
            text-align: center;
            margin-left: var(--sidebar-width);
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }

            .topbar, .main-content, .footer {
                margin-left: 0;
            }

            .balance-amount {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<?php
// Include student sidebar for consistent navigation
$student_name = htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$student = $student ?? ['department_name' => 'Department'];
include 'includes/student_sidebar.php';
?>

<div class="topbar">
    <h5 class="m-0 fw-bold">Fee Payments</h5>
    <span>RP Attendance System</span>
</div>

<div class="main-content">
    <!-- Outstanding Balance -->
    <div class="balance-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="balance-amount">RWF <?php echo number_format($outstanding_balance, 0); ?></div>
                <div class="balance-label">Outstanding Balance</div>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-light btn-lg" onclick="makePayment()">
                    <i class="fas fa-credit-card me-2"></i>Pay Now
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-success mb-2">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <div class="h4 mb-1">RWF <?php echo number_format($total_paid, 0); ?></div>
                <div class="text-muted">Total Paid</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-primary mb-2">
                    <i class="fas fa-calculator fa-2x"></i>
                </div>
                <div class="h4 mb-1">RWF <?php echo number_format($total_fees, 0); ?></div>
                <div class="text-muted">Total Fees</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-warning mb-2">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
                <div class="h4 mb-1">RWF <?php echo number_format($pending_fees, 0); ?></div>
                <div class="text-muted">Pending Fees</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-info mb-2">
                    <i class="fas fa-receipt fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo count($recent_payments); ?></div>
                <div class="text-muted">Payments Made</div>
            </div>
        </div>
    </div>

    <!-- Fee Breakdown -->
    <div class="fee-breakdown">
        <h6 class="mb-3"><i class="fas fa-list me-2"></i>Fee Breakdown</h6>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Fee Type</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($fee_breakdown) > 0): ?>
                        <?php foreach ($fee_breakdown as $fee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                                <td>RWF <?php echo number_format($fee['amount'], 0); ?></td>
                                <td><?php echo date('M d, Y', strtotime($fee['due_date'])); ?></td>
                                <td>
                                    <?php if ($fee['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($fee['status'] === 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php elseif ($fee['status'] === 'overdue'): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($fee['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fee['status'] !== 'paid'): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="payFee('<?php echo $fee['fee_type']; ?>', <?php echo $fee['amount']; ?>)">
                                            <i class="fas fa-credit-card me-1"></i>Pay
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">Paid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-calculator"></i>
                                    <h6>No Fee Records</h6>
                                    <p>Your fee information is being processed.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="fee-breakdown">
        <h6 class="mb-3"><i class="fas fa-history me-2"></i>Recent Payments</h6>
        <div id="paymentsContainer">
            <?php if (count($recent_payments) > 0): ?>
                <?php foreach ($recent_payments as $payment): ?>
                    <div class="payment-card">
                        <div class="payment-header">
                            <h6 class="payment-title">Payment Receipt #<?php echo htmlspecialchars($payment['id']); ?></h6>
                            <div class="payment-date">
                                <i class="fas fa-calendar me-1"></i><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?>
                                <i class="fas fa-clock ms-2 me-1"></i><?php echo date('H:i', strtotime($payment['payment_date'])); ?>
                            </div>
                        </div>
                        <div class="payment-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Amount:</strong> RWF <?php echo number_format($payment['amount'], 0); ?><br>
                                    <strong>Method:</strong> <?php echo htmlspecialchars($payment['payment_method'] ?? 'Online'); ?><br>
                                    <strong>Reference:</strong> <?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong>
                                    <span class="badge bg-success ms-1">Completed</span><br>
                                    <strong>Processed By:</strong> <?php echo htmlspecialchars($payment['processed_by'] ?? 'System'); ?>
                                </div>
                            </div>
                            <?php if (!empty($payment['notes'])): ?>
                                <div class="mt-2">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewReceipt(<?php echo $payment['id']; ?>)">
                                    <i class="fas fa-eye me-1"></i>View Receipt
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="downloadReceipt(<?php echo $payment['id']; ?>)">
                                    <i class="fas fa-download me-1"></i>Download
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h6>No Payment History</h6>
                    <p>You haven't made any payments yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Student Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Payment functions
function makePayment() {
    showNotification('Payment gateway integration will be implemented. This is a demo.', 'info');
}

function payFee(feeType, amount) {
    if (confirm(`Pay RWF ${amount.toLocaleString()} for ${feeType}?`)) {
        showNotification(`Payment request for ${feeType} sent. Integration pending.`, 'success');
    }
}

function viewReceipt(paymentId) {
    showNotification('Receipt viewing will be implemented. Payment ID: ' + paymentId, 'info');
}

function downloadReceipt(paymentId) {
    showNotification('Receipt download will be implemented. Payment ID: ' + paymentId, 'info');
}

// Notification system
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' :
                        type === 'error' ? 'alert-danger' :
                        type === 'warning' ? 'alert-warning' : 'alert-info';

    const icon = type === 'success' ? 'fas fa-check-circle' :
                  type === 'error' ? 'fas fa-exclamation-triangle' :
                  type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;';
    alert.innerHTML = `
        <div class="d-flex align-items-start">
            <i class="${icon} me-2 mt-1"></i>
            <div class="flex-grow-1">
                <div class="fw-bold">${type.toUpperCase()}</div>
                <div>${message}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    document.body.appendChild(alert);

    setTimeout(() => {
        if (alert.parentNode) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 300);
        }
    }, 4000);
}

// Auto-refresh balance every 30 seconds
setInterval(function() {
    // In real implementation, this would fetch updated balance
    console.log('Balance check...');
}, 30000);
</script>

</body>
</html>