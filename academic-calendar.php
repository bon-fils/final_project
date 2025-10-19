<?php
/**
 * Academic Calendar - Student Portal
 * View academic calendar with events, important dates, and schedules
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

// Get academic calendar events
try {
    $stmt = $pdo->prepare("
        SELECT * FROM academic_calendar
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC, event_time ASC
        LIMIT 50
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $events = [];
}

// Get upcoming events (next 7 days)
$upcoming_events = array_filter($events, function($event) {
    $event_date = strtotime($event['event_date']);
    $week_from_now = strtotime('+7 days');
    return $event_date <= $week_from_now;
});

// Get events by month
$events_by_month = [];
foreach ($events as $event) {
    $month = date('F Y', strtotime($event['event_date']));
    if (!isset($events_by_month[$month])) {
        $events_by_month[$month] = [];
    }
    $events_by_month[$month][] = $event;
}

// Get event types for filtering
$event_types = array_unique(array_column($events, 'event_type'));

// Set user role for frontend compatibility
$userRole = 'student';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar | Student | RP Attendance System</title>
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

        .event-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
            border-left: 4px solid var(--primary-color);
        }

        .event-card.upcoming {
            border-left-color: var(--warning-color);
        }

        .event-card.today {
            border-left-color: var(--success-color);
        }

        .event-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .event-header.upcoming {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
        }

        .event-header.today {
            background: linear-gradient(135deg, var(--success-color), #218838);
        }

        .event-title {
            font-weight: 600;
            margin: 0;
        }

        .event-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .event-body {
            padding: 20px;
        }

        .event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }

        .detail-value {
            color: #666;
            font-size: 0.9rem;
        }

        .event-description {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #dee2e6;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .calendar-grid {
            display: grid;
            gap: 20px;
        }

        .month-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }

        .month-header {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
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

            .event-details {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .event-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
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
    <h5 class="m-0 fw-bold">Academic Calendar</h5>
    <span>RP Attendance System</span>
</div>

<div class="main-content">
    <!-- Statistics Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="text-primary mb-2">
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                    <div class="h4 mb-1"><?php echo count($events); ?></div>
                    <div class="text-muted">Total Events</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="text-warning mb-2">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div class="h4 mb-1"><?php echo count($upcoming_events); ?></div>
                    <div class="text-muted">Upcoming (7 days)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="text-success mb-2">
                        <i class="fas fa-graduation-cap fa-2x"></i>
                    </div>
                    <div class="h4 mb-1"><?php echo count(array_filter($events, fn($e) => $e['event_type'] === 'academic')); ?></div>
                    <div class="text-muted">Academic Events</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="text-info mb-2">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <div class="h4 mb-1"><?php echo count(array_filter($events, fn($e) => $e['event_type'] === 'social')); ?></div>
                    <div class="text-muted">Social Events</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-buttons">
        <button class="filter-btn active" data-filter="all">All Events (<?php echo count($events); ?>)</button>
        <button class="filter-btn" data-filter="upcoming">Upcoming (<?php echo count($upcoming_events); ?>)</button>
        <button class="filter-btn" data-filter="academic">Academic</button>
        <button class="filter-btn" data-filter="social">Social</button>
        <button class="filter-btn" data-filter="sports">Sports</button>
        <button class="filter-btn" data-filter="other">Other</button>
    </div>

    <!-- Events List -->
    <div id="eventsContainer">
        <?php if (count($events) > 0): ?>
            <?php foreach ($events as $event): ?>
                <?php
                $event_date = strtotime($event['event_date']);
                $today = strtotime(date('Y-m-d'));
                $is_today = $event_date === $today;
                $is_upcoming = $event_date > $today && $event_date <= strtotime('+7 days');
                $card_class = $is_today ? 'today' : ($is_upcoming ? 'upcoming' : '');
                $header_class = $is_today ? 'today' : ($is_upcoming ? 'upcoming' : '');
                ?>
                <div class="event-card <?php echo $card_class; ?>" data-type="<?php echo htmlspecialchars($event['event_type']); ?>" data-date="<?php echo $event_date; ?>">
                    <div class="event-header <?php echo $header_class; ?>">
                        <div>
                            <h5 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                            <div class="event-date">
                                <i class="fas fa-calendar me-1"></i><?php echo date('F d, Y', $event_date); ?>
                                <?php if ($event['event_time']): ?>
                                    <i class="fas fa-clock ms-2 me-1"></i><?php echo date('H:i', strtotime($event['event_time'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark"><?php echo ucfirst(htmlspecialchars($event['event_type'])); ?></span>
                        </div>
                    </div>

                    <div class="event-body">
                        <div class="event-details">
                            <div class="detail-item">
                                <div class="detail-label">Date & Time</div>
                                <div class="detail-value">
                                    <?php echo date('l, F d, Y', $event_date); ?>
                                    <?php if ($event['event_time']): ?>
                                        at <?php echo date('H:i A', strtotime($event['event_time'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($event['location']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Location</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($event['location']); ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <div class="detail-label">Event Type</div>
                                <div class="detail-value"><?php echo ucfirst(htmlspecialchars($event['event_type'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <?php if ($is_today): ?>
                                        <span class="badge bg-success">Today</span>
                                    <?php elseif ($is_upcoming): ?>
                                        <span class="badge bg-warning">Upcoming</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Scheduled</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($event['description']): ?>
                            <div class="event-description">
                                <strong>Description:</strong><br>
                                <?php echo htmlspecialchars($event['description']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                Added: <?php echo date('M d, Y H:i', strtotime($event['created_at'])); ?>
                            </small>
                            <?php if ($event['location']): ?>
                                <button class="btn btn-sm btn-outline-primary" onclick="openLocation('<?php echo htmlspecialchars($event['location']); ?>')">
                                    <i class="fas fa-map-marker-alt me-1"></i>Get Directions
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h4>No Events Scheduled</h4>
                <p>There are no upcoming academic events at this time.</p>
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh Calendar
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Student Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const eventCards = document.querySelectorAll('.event-card');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            const filterType = this.dataset.filter;

            eventCards.forEach(card => {
                const eventType = card.dataset.type;
                const eventDate = parseInt(card.dataset.date);
                const now = Date.now() / 1000; // Convert to seconds
                const weekFromNow = now + (7 * 24 * 60 * 60);

                let show = false;

                switch (filterType) {
                    case 'all':
                        show = true;
                        break;
                    case 'upcoming':
                        show = eventDate >= now && eventDate <= weekFromNow;
                        break;
                    case 'academic':
                    case 'social':
                    case 'sports':
                    case 'other':
                        show = eventType === filterType;
                        break;
                }

                card.style.display = show ? 'block' : 'none';
            });
        });
    });
});

// Open location in maps
function openLocation(location) {
    // You can integrate with Google Maps or other mapping service
    const encodedLocation = encodeURIComponent(location + ", Rwanda Polytechnic");
    window.open(`https://www.google.com/maps/search/?api=1&query=${encodedLocation}`, '_blank');
}

// Auto-refresh calendar every 5 minutes
setInterval(function() {
    // Optional: Add auto-refresh functionality
    console.log('Calendar auto-refresh check...');
}, 300000);
</script>

</body>
</html>