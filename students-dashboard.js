// Global variables
let notificationsVisible = false;

// Initialize when document is ready
$(document).ready(function() {
    initializeDashboard();
    setupEventListeners();
    loadDashboardData();
});

// Initialize dashboard components
function initializeDashboard() {
    // Set current date/time
    updateDateTime();

    // Initialize animations
    initializeAnimations();

    // Check for low attendance warning
    checkAttendanceStatus();

    // Update time every minute
    setInterval(updateDateTime, 60000);
}

// Setup all event listeners
function setupEventListeners() {
    // Sidebar toggle for mobile
    $('#sidebarToggle').on('click', toggleSidebar);

    // Notification bell click
    $('.notification-bell').on('click', toggleNotifications);

    // Close notifications when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.notification-bell, .notifications-dropdown').length) {
            hideNotifications();
        }
    });

    // Keyboard shortcuts
    $(document).on('keydown', handleKeyboardShortcuts);

    // Handle window resize
    $(window).on('resize', handleWindowResize);
}

// Update date and time display
function updateDateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    $('.topbar .text-muted').last().html(`<i class="fas fa-clock me-1"></i>${timeString}`);
}

// Initialize animations
function initializeAnimations() {
    // Add entrance animations to cards
    $('.stat-card, .action-card, .notification-card, .schedule-card').each(function(index) {
        $(this).css('animation-delay', (index * 0.1) + 's');
    });
}

// Check attendance status and show warnings
function checkAttendanceStatus() {
    const attendanceRate = window.attendanceRate;

    if (attendanceRate < 75) {
        showAttendanceWarning(attendanceRate);
    }
}

// Show attendance warning
function showAttendanceWarning(rate) {
    const warningHtml = `
        <div class="alert alert-warning alert-dismissible fade show position-fixed"
             style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attendance Alert!</strong> Your attendance rate is ${rate}%.
            Please improve to avoid academic penalties.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    $('body').append(warningHtml);

    // Auto-dismiss after 10 seconds
    setTimeout(() => {
        $('.alert-warning').fadeOut(function() {
            $(this).remove();
        });
    }, 10000);
}

// Toggle sidebar for mobile
function toggleSidebar() {
    $('#sidebar').toggleClass('show');
}

// Toggle notifications dropdown
function toggleNotifications() {
    if (notificationsVisible) {
        hideNotifications();
    } else {
        showNotifications();
    }
}

// Show notifications
function showNotifications() {
    // Create notifications dropdown if it doesn't exist
    if (!$('.notifications-dropdown').length) {
        const notifications = window.notifications;
        const notificationsHtml = `
            <div class="notifications-dropdown position-absolute bg-white rounded shadow"
                 style="top: 50px; right: 0; width: 350px; max-height: 400px; overflow-y: auto; z-index: 1000;">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6>
                </div>
                <div class="notifications-list">
                    ${notifications.length > 0 ? notifications.map(notification => `
                        <div class="notification-item p-3 border-bottom hover-bg-light" style="cursor: pointer;"
                             onclick="handleNotificationClick('${notification.action ?? '#'}')">
                            <div class="d-flex">
                                <div class="notification-icon me-3">
                                    <div class="bg-${notification.type === 'warning' ? 'warning' : (notification.type === 'success' ? 'success' : 'info')} bg-opacity-10 rounded-circle p-2">
                                        <i class="fas fa-${notification.icon} text-${notification.type === 'warning' ? 'warning' : (notification.type === 'success' ? 'success' : 'info')}"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small">${notification.title}</div>
                                    <div class="text-muted small">${notification.message}</div>
                                </div>
                            </div>
                        </div>
                    `).join('') : `
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                            <div>No new notifications</div>
                        </div>
                    `}
                </div>
            </div>
        `;

        $('.notification-bell').append(notificationsHtml);
    }

    $('.notifications-dropdown').fadeIn(200);
    notificationsVisible = true;
}

// Hide notifications
function hideNotifications() {
    $('.notifications-dropdown').fadeOut(200);
    notificationsVisible = false;
}

// Handle notification click
function handleNotificationClick(action) {
    if (action && action !== '#') {
        window.location.href = action;
    }
    hideNotifications();
}

// Handle keyboard shortcuts
function handleKeyboardShortcuts(e) {
    // Alt + D to focus dashboard
    if (e.altKey && e.key === 'd') {
        e.preventDefault();
        $('html, body').animate({ scrollTop: 0 }, 500);
    }

    // Alt + A for attendance
    if (e.altKey && e.key === 'a') {
        e.preventDefault();
        window.location.href = 'attendance-records.php';
    }

    // Alt + L for leave
    if (e.altKey && e.key === 'l') {
        e.preventDefault();
        window.location.href = 'request-leave.php';
    }

    // Escape to close modals/dropdowns
    if (e.key === 'Escape') {
        hideNotifications();
        $('.modal').modal('hide');
    }
}

// Handle window resize
function handleWindowResize() {
    // Close sidebar on desktop
    if ($(window).width() >= 768) {
        $('#sidebar').removeClass('show');
    }
}

// Load dashboard data (for future AJAX updates)
function loadDashboardData() {
    // This could be used for real-time updates
    // For now, it's a placeholder for future enhancements
}

// Show profile modal
function showProfileModal() {
    $('#profileModal').modal('show');
}

// Animate value changes
function animateValue(element, newValue, duration = 1000) {
    const $element = $(element);
    const currentValue = parseInt($element.text()) || 0;

    $({ val: currentValue }).animate({ val: newValue }, {
        duration: duration,
        easing: 'swing',
        step: function(val) {
            $element.text(Math.floor(val));
        },
        complete: function() {
            $element.text(newValue);
        }
    });
}

// Performance monitoring
function monitorPerformance() {
    if ('performance' in window && 'getEntriesByType' in performance) {
        // Monitor page load performance
        window.addEventListener('load', function() {
            setTimeout(() => {
                const perfData = performance.getEntriesByType('navigation')[0];
                console.log('Page load time:', perfData.loadEventEnd - perfData.fetchStart + 'ms');
            }, 0);
        });
    }
}

// Initialize performance monitoring
monitorPerformance();

// Handle online/offline status
window.addEventListener('online', function() {
    showStatusMessage('Connection restored', 'success');
});

window.addEventListener('offline', function() {
    showStatusMessage('You are offline', 'warning');
});

// Show status messages
function showStatusMessage(message, type = 'info') {
    const alertId = 'status_alert_' + Date.now();
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed"
             id="${alertId}" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    $('body').append(alertHtml);

    setTimeout(() => {
        $(`#${alertId}`).fadeOut(function() {
            $(this).remove();
        });
    }, 5000);
}

// Add hover effects for better UX
$(document).on('mouseenter', '.action-card, .stat-card', function() {
    $(this).addClass('hover-lift');
});

$(document).on('mouseleave', '.action-card, .stat-card', function() {
    $(this).removeClass('hover-lift');
});