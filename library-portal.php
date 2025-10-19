<?php
/**
 * Library Portal - Student Portal
 * Access library resources, search books, and manage borrowing
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

// Get library statistics
try {
    // Total books available
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_books FROM library_books WHERE status = 'available'");
    $stmt->execute();
    $total_books = $stmt->fetch(PDO::FETCH_ASSOC)['total_books'] ?? 0;

    // Books currently borrowed by student
    $stmt = $pdo->prepare("SELECT COUNT(*) as borrowed_books FROM library_borrowings WHERE student_id = ? AND return_date IS NULL");
    $stmt->execute([$student['id']]);
    $borrowed_books = $stmt->fetch(PDO::FETCH_ASSOC)['borrowed_books'] ?? 0;

    // Overdue books
    $stmt = $pdo->prepare("SELECT COUNT(*) as overdue_books FROM library_borrowings WHERE student_id = ? AND return_date IS NULL AND due_date < CURDATE()");
    $stmt->execute([$student['id']]);
    $overdue_books = $stmt->fetch(PDO::FETCH_ASSOC)['overdue_books'] ?? 0;

    // Recent borrowings
    $stmt = $pdo->prepare("
        SELECT lb.*, b.title, b.author, b.isbn
        FROM library_borrowings lb
        JOIN library_books b ON lb.book_id = b.id
        WHERE lb.student_id = ?
        ORDER BY lb.borrow_date DESC
        LIMIT 5
    ");
    $stmt->execute([$student['id']]);
    $recent_borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $total_books = 0;
    $borrowed_books = 0;
    $overdue_books = 0;
    $recent_borrowings = [];
}

// Get popular books
try {
    $stmt = $pdo->prepare("
        SELECT b.*, COUNT(lb.id) as borrow_count
        FROM library_books b
        LEFT JOIN library_borrowings lb ON b.id = lb.book_id
        WHERE b.status = 'available'
        GROUP BY b.id
        ORDER BY borrow_count DESC
        LIMIT 6
    ");
    $stmt->execute();
    $popular_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $popular_books = [];
}

// Set user role for frontend compatibility
$userRole = 'student';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Portal | Student | RP Attendance System</title>
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

        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }

        .book-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
            transition: transform 0.3s;
        }

        .book-card:hover {
            transform: translateY(-5px);
        }

        .book-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 15px;
        }

        .book-title {
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }

        .book-author {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        .book-body {
            padding: 15px;
        }

        .book-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            font-size: 0.8rem;
            margin-bottom: 2px;
        }

        .detail-value {
            color: #666;
            font-size: 0.9rem;
        }

        .search-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .borrowing-history {
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

            .book-details {
                grid-template-columns: 1fr;
                gap: 8px;
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
    <h5 class="m-0 fw-bold">Library Portal</h5>
    <span>RP Attendance System</span>
</div>

<div class="main-content">
    <!-- Library Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-primary mb-2">
                    <i class="fas fa-book fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo $total_books; ?></div>
                <div class="text-muted">Books Available</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-info mb-2">
                    <i class="fas fa-hand-holding fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo $borrowed_books; ?></div>
                <div class="text-muted">Books Borrowed</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-warning mb-2">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo $overdue_books; ?></div>
                <div class="text-muted">Overdue Books</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-success mb-2">
                    <i class="fas fa-star fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo count($popular_books); ?></div>
                <div class="text-muted">Popular Books</div>
            </div>
        </div>
    </div>

    <!-- Search Books -->
    <div class="search-container">
        <h6 class="mb-3"><i class="fas fa-search me-2"></i>Search Books</h6>
        <div class="row g-3">
            <div class="col-md-8">
                <input type="text" id="bookSearch" class="form-control" placeholder="Search by title, author, or ISBN...">
            </div>
            <div class="col-md-4">
                <select id="categoryFilter" class="form-select">
                    <option value="">All Categories</option>
                    <option value="computer_science">Computer Science</option>
                    <option value="mathematics">Mathematics</option>
                    <option value="physics">Physics</option>
                    <option value="chemistry">Chemistry</option>
                    <option value="biology">Biology</option>
                    <option value="literature">Literature</option>
                    <option value="history">History</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary me-2" onclick="searchBooks()">
                <i class="fas fa-search me-1"></i>Search
            </button>
            <button class="btn btn-outline-secondary" onclick="clearSearch()">
                <i class="fas fa-times me-1"></i>Clear
            </button>
        </div>
    </div>

    <!-- Popular Books -->
    <div class="mb-4">
        <h6 class="mb-3"><i class="fas fa-star me-2"></i>Popular Books</h6>
        <div class="row" id="popularBooksContainer">
            <?php if (count($popular_books) > 0): ?>
                <?php foreach ($popular_books as $book): ?>
                    <div class="col-md-6 col-lg-4 book-item">
                        <div class="book-card">
                            <div class="book-header">
                                <h6 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h6>
                                <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                            </div>
                            <div class="book-body">
                                <div class="book-details">
                                    <div class="detail-item">
                                        <div class="detail-label">ISBN</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Category</div>
                                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $book['category'] ?? 'other')); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Times Borrowed</div>
                                        <div class="detail-value"><?php echo $book['borrow_count']; ?></div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary btn-sm flex-fill" onclick="borrowBook(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-hand-holding me-1"></i>Borrow
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="viewBookDetails(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h6>No Books Available</h6>
                        <p>The library collection is being updated.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Borrowing History -->
    <div class="borrowing-history">
        <h6 class="mb-3"><i class="fas fa-history me-2"></i>My Borrowing History</h6>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="borrowingHistoryTable">
                    <?php if (count($recent_borrowings) > 0): ?>
                        <?php foreach ($recent_borrowings as $borrowing): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($borrowing['title']); ?></td>
                                <td><?php echo htmlspecialchars($borrowing['author']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($borrowing['borrow_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($borrowing['due_date'])); ?></td>
                                <td>
                                    <?php if ($borrowing['return_date']): ?>
                                        <span class="badge bg-success">Returned</span>
                                    <?php elseif (strtotime($borrowing['due_date']) < time()): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Borrowed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$borrowing['return_date']): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="returnBook(<?php echo $borrowing['id']; ?>)">
                                            <i class="fas fa-undo me-1"></i>Return
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">Returned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-book-open"></i>
                                    <h6>No Borrowing History</h6>
                                    <p>You haven't borrowed any books yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Student Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality
function searchBooks() {
    const query = document.getElementById('bookSearch').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;

    // Show loading state
    showNotification('Searching books...', 'info');

    // Simulate search (in real implementation, this would be an AJAX call)
    setTimeout(() => {
        showNotification('Search completed. Feature will be fully implemented with backend.', 'success');
    }, 1000);
}

function clearSearch() {
    document.getElementById('bookSearch').value = '';
    document.getElementById('categoryFilter').value = '';
    showNotification('Search cleared', 'info');
}

// Book actions
function borrowBook(bookId) {
    if (confirm('Are you sure you want to borrow this book?')) {
        showNotification('Borrow request sent. This feature will be fully implemented with backend integration.', 'success');
    }
}

function returnBook(borrowingId) {
    if (confirm('Are you sure you want to return this book?')) {
        showNotification('Return request sent. This feature will be fully implemented with backend integration.', 'success');
    }
}

function viewBookDetails(bookId) {
    showNotification('Book details view will be implemented. Book ID: ' + bookId, 'info');
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

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+F to focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('bookSearch').focus();
    }
});
</script>

</body>
</html>