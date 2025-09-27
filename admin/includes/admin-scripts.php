<?php
/**
 * Admin Scripts Component
 * Common JavaScript functions for all admin pages
 */
?>

<!-- Common Admin Scripts -->
<script>
// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const header = document.getElementById('adminHeader');
    const main = document.getElementById('mainContent') || document.querySelector('.main-content');

    if (sidebar && header && main) {
        sidebar.classList.toggle('show');
        header.classList.toggle('collapsed');
        main.classList.toggle('expanded');
    }
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');

    if (window.innerWidth <= 768 && sidebar && toggleBtn) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('show');
            document.getElementById('adminHeader')?.classList.remove('collapsed');
            document.querySelector('.main-content')?.classList.remove('expanded');
        }
    }
});

// Refresh data
function refreshData() {
    if (typeof showLoading === 'function') {
        showLoading();
    }

    setTimeout(() => {
        if (typeof hideLoading === 'function') {
            hideLoading();
        }
        showAlert('Data refreshed successfully', 'success');
    }, 1000);
}

// Fullscreen toggle
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

// Show notifications
function showNotifications() {
    showAlert('No new notifications', 'info');
}

// Toggle user menu
function toggleUserMenu() {
    showAlert('User menu clicked', 'info');
}

// Loading functions
function showLoading() {
    let loadingOverlay = document.getElementById('loadingOverlay');
    if (!loadingOverlay) {
        loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loadingOverlay';
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
        document.body.appendChild(loadingOverlay);
    }
    loadingOverlay.style.display = 'flex';
}

function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}

// Alert helper
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 90px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Remove existing alerts
    document.querySelectorAll('.alert').forEach(alert => alert.remove());
    document.body.insertAdjacentHTML('beforeend', alertHtml);

    // Auto remove after 5 seconds
    setTimeout(() => {
        document.querySelector('.alert')?.remove();
    }, 5000);
}

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('adminSidebar');
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove('show');
    }
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-refresh statistics every 5 minutes if function exists
    if (typeof loadStatistics === 'function') {
        setInterval(loadStatistics, 300000);
    }
});
</script>