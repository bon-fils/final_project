<?php
/**
 * Enhanced Attendance Reports - Refactored Backend
 * Modern, secure, and maintainable attendance reporting system
 * Uses MVC architecture with proper separation of concerns
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'hod', 'admin']);

// Include the new backend classes
require_once "classes/AttendanceReportValidator.php";
require_once "classes/AttendanceReportModel.php";
require_once "classes/ExportService.php";
require_once "classes/AttendanceReportsController.php";

try {
    // Initialize the controller and handle the request
    $controller = new AttendanceReportsController($pdo);
    $controller->handleRequest();

} catch (Exception $e) {
    // Global error handler
    error_log("Fatal error in attendance reports: " . $e->getMessage());

    // Check if AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
             strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'An unexpected error occurred.',
            'message' => APP_ENV === 'development' ? $e->getMessage() : 'Please try again later.'
        ]);
        exit;
    }

    // Show user-friendly error page
    http_response_code(500);
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>System Error - RP Attendance System</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
            .error-container { max-width: 600px; margin: 5rem auto; padding: 2rem; }
            .error-card { background: rgba(255,255,255,0.95); border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='error-container'>
                <div class='error-card card border-0'>
                    <div class='card-body text-center p-5'>
                        <div class='mb-4'>
                            <i class='fas fa-exclamation-triangle fa-4x text-warning'></i>
                        </div>
                        <h2 class='card-title text-danger mb-3'>System Error</h2>
                        <p class='card-text text-muted mb-4'>
                            We're experiencing technical difficulties. Please try again in a few moments.
                        </p>
                        <div class='d-grid gap-2 d-md-flex justify-content-md-center'>
                            <a href='javascript:history.back()' class='btn btn-outline-primary me-md-2'>
                                <i class='fas fa-arrow-left me-2'></i>Go Back
                            </a>
                            <a href='index.php' class='btn btn-primary'>
                                <i class='fas fa-home me-2'></i>Dashboard
                            </a>
                        </div>
                        " . (APP_ENV === 'development' ? "<div class='mt-4 p-3 bg-light rounded'><small class='text-muted'>Debug: {$e->getMessage()}</small></div>" : "") . "
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
    exit;
}