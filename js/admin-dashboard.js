// Mobile sidebar toggle function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');

    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
    }
});

function showAlert(type, msg) {
    $("#alertBox").html(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`);
    setTimeout(() => $("#alertBox").html(""), 5000);
}

function showLoading() {
    $("#loadingOverlay").fadeIn();
}

function hideLoading() {
    $("#loadingOverlay").fadeOut();
}

function fetchDashboardData() {
    showLoading();

    $.ajax({
        url: 'admin-dashboard.php',
        method: 'GET',
        data: { ajax: '1', t: Date.now() },
        timeout: 10000,
        success: function(response) {
            hideLoading();

            if (response.status === 'success') {
                const data = response.data;

                // Check for error in data
                if (data.error) {
                    showAlert("warning", data.error);
                    return;
                }

                // Animate value updates
                animateValue('total_students', data.total_students);
                animateValue('total_lecturers', data.total_lecturers);
                animateValue('total_hods', data.total_hods);
                animateValue('total_departments', data.total_departments);
                animateValue('active_attendance', data.active_attendance);
                animateValue('total_sessions', data.total_sessions);
                animateValue('pending_requests', data.pending_requests);
                animateValue('avg_attendance_rate', data.avg_attendance_rate);

                // Update additional metrics
                $('#active_users_count').text(data.active_users_24h || 0);
                $('#avg_attendance_display').text(data.avg_attendance_rate + '%');

                // Update last update time
                updateLastUpdateDisplay();

                showAlert("success", "Dashboard updated successfully");
            } else {
                const errorMsg = response.message || "Failed to update dashboard data";
                showAlert("danger", errorMsg);
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            let errorMsg = "Failed to fetch dashboard data. Please try again.";
            if (status === 'timeout') {
                errorMsg = "Request timed out. Please check your connection.";
            } else if (xhr.status === 500) {
                errorMsg = "Server error occurred. Please try again later.";
            }
            showAlert("danger", errorMsg);
            console.error('Dashboard AJAX Error:', error);
        }
    });
}

function animateValue(id, newValue, duration = 600) {
    const el = document.getElementById(id);
    if (!el) return;

    const current = parseInt(el.innerText) || 0;
    const startTime = Date.now();

    function update() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const value = Math.floor(current + (newValue - current) * easeOut);

        el.innerText = value;

        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            el.innerText = newValue;
        }
    }

    requestAnimationFrame(update);
}

function updateLastUpdateDisplay() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    $('#last_update').text(timeString);
}

// Initialize dashboard
$(document).ready(function() {
    // Initial data load
    fetchDashboardData();

    // Set up refresh button
    $('#refreshDashboard').click(function() {
        fetchDashboardData();
    });

    // Auto-refresh every 5 minutes
    setInterval(fetchDashboardData, 300000);

    // Update time display every minute
    setInterval(updateLastUpdateDisplay, 60000);

    // Initialize time display
    updateLastUpdateDisplay();
});