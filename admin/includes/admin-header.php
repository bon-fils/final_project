<?php
/**
 * Admin Header Component
 * Reusable header for all admin pages
 */

$page_title = $page_title ?? 'Admin Panel';
$current_page = $_GET['page'] ?? 'dashboard';
?>

<!-- Header -->
<header class="admin-header" id="adminHeader">
    <div class="header-content">
        <div class="header-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title"><?php echo $page_title; ?></h1>
        </div>
        <div class="header-right">
            <div class="header-actions">
                <button class="btn-icon" onclick="refreshData()" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button class="btn-icon" onclick="toggleFullscreen()" title="Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
                <button class="btn-icon" onclick="showNotifications()" title="Notifications">
                    <i class="fas fa-bell"></i>
                </button>
            </div>
            <div class="user-menu" onclick="toggleUserMenu()">
                <div class="user-avatar-small">
                    <?php echo strtoupper(substr($_SESSION['role'], 0, 1)); ?>
                </div>
                <span>Admin</span>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </div>
</header>