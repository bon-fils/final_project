<?php
/**
 * Attendance Reports Controller
 * Main controller for handling attendance report requests
 * Implements proper MVC pattern with error handling and security
 */

class AttendanceReportsController {
    private $model;
    private $validator;
    private $exportService;
    private $userId;
    private $userRole;
    private $userContext;

    public function __construct(PDO $pdo) {
        $this->model = new AttendanceReportModel($pdo);
        $this->validator = new AttendanceReportValidator();
        $this->exportService = new ExportService();

        $this->userId = $_SESSION['user_id'] ?? null;
        $this->userRole = $_SESSION['role'] ?? null;

        if (!$this->userId || !$this->userRole) {
            throw new RuntimeException('User session not found');
        }
    }

    /**
     * Main entry point for handling requests
     */
    public function handleRequest(): void {
        try {
            // Ensure database schema is up to date
            $this->model->ensureDatabaseSchema();

            // Get user context
            $this->userContext = $this->model->getUserContext($this->userId, $this->userRole);
            $lecturerId = $this->userContext['lecturer_id'];
            $isAdmin = $this->userRole === 'admin';

            // Handle export requests
            if (isset($_GET['export'])) {
                $this->handleExport($lecturerId, $isAdmin);
                return;
            }

            // Handle regular report generation
            $this->handleReportGeneration($lecturerId, $isAdmin);

        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handle report generation and display
     */
    private function handleReportGeneration(?int $lecturerId, bool $isAdmin): void {
        // Get filter parameters with validation
        $filters = $this->getValidatedFilters();

        // Get available data for filters
        $departments = $this->model->getAvailableDepartments($lecturerId, $isAdmin);
        $options = [];
        $classes = $this->model->getAvailableClasses($lecturerId, $isAdmin);
        $courses = [];

        if (!empty($filters['department_id'])) {
            $options = $this->model->getOptionsForDepartment($filters['department_id'], $lecturerId, $isAdmin);
        }

        if (!empty($filters['class_id'])) {
            $courses = $this->model->getCoursesForClass($filters['class_id'], $lecturerId, $isAdmin);
        }

        // Generate report if we have required filters
        $reportData = [];
        $hasRequiredFilters = $this->hasRequiredFilters($filters);

        if ($hasRequiredFilters) {
            $reportData = $this->model->generateReport($filters['report_type'], $filters, $lecturerId, $isAdmin);
        }

        // Render the view
        $this->renderView([
            'filters' => $filters,
            'departments' => $departments,
            'options' => $options,
            'classes' => $classes,
            'courses' => $courses,
            'report_data' => $reportData,
            'has_required_filters' => $hasRequiredFilters,
            'user_role' => $this->userRole,
            'user_context' => $this->userContext
        ]);
    }

    /**
     * Handle export requests
     */
    private function handleExport(?int $lecturerId, bool $isAdmin): void {
        // Validate export format
        $format = $_GET['export'];
        if (!$this->validator->validateExportFormat($format)) {
            throw new InvalidArgumentException('Invalid export format');
        }

        // Get validated filters
        $filters = $this->getValidatedFilters();

        // Check if we have required filters
        if (!$this->hasRequiredFilters($filters)) {
            throw new InvalidArgumentException('Required filters missing for export');
        }

        // Generate report data
        $reportData = $this->model->generateReport($filters['report_type'], $filters, $lecturerId, $isAdmin);

        // Generate filename
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "attendance_report_{$filters['report_type']}_{$timestamp}";

        // Export the data
        $this->exportService->export($reportData, $format, $filename);
    }

    /**
     * Get and validate filter parameters
     */
    private function getValidatedFilters(): array {
        $rawFilters = [
            'report_type' => $_GET['report_type'] ?? 'class',
            'department_id' => $_GET['department_id'] ?? null,
            'option_id' => $_GET['option_id'] ?? null,
            'class_id' => $_GET['class_id'] ?? null,
            'course_id' => $_GET['course_id'] ?? null,
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
        ];

        // Sanitize inputs
        $filters = $this->validator->sanitizeFilters($rawFilters);

        // Validate filters
        if (!$this->validator->validateFilters($filters)) {
            $errors = $this->validator->getErrors();
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }

        return $filters;
    }

    /**
     * Check if we have required filters for report generation
     */
    private function hasRequiredFilters(array $filters): bool {
        switch ($filters['report_type']) {
            case 'department':
                return !empty($filters['department_id']);
            case 'option':
                return !empty($filters['option_id']);
            case 'class':
                return !empty($filters['class_id']);
            case 'course':
                return !empty($filters['course_id']);
            default:
                return false;
        }
    }

    /**
     * Render the attendance reports view
     */
    private function renderView(array $data): void {
        // Extract data for template
        extract($data);

        // Include the view template
        require_once 'views/attendance-reports.php';
    }

    /**
     * Handle errors gracefully
     */
    private function handleError(Exception $e): void {
        error_log("Attendance Reports Error: " . $e->getMessage());

        // Check if this is an AJAX request
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'An error occurred while processing your request.',
                'message' => APP_ENV === 'development' ? $e->getMessage() : 'Internal server error'
            ]);
            exit;
        }

        // For regular requests, show error page
        $this->renderErrorView($e);
    }

    /**
     * Render error view
     */
    private function renderErrorView(Exception $e): void {
        $errorMessage = APP_ENV === 'development' ? $e->getMessage() : 'An unexpected error occurred.';
        $errorCode = $e->getCode() ?: 500;

        http_response_code($errorCode);

        // Simple error template
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Error - Attendance Reports</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body class='bg-light'>
            <div class='container mt-5'>
                <div class='row justify-content-center'>
                    <div class='col-md-6'>
                        <div class='card shadow'>
                            <div class='card-body text-center'>
                                <h1 class='card-title text-danger'>Error</h1>
                                <p class='card-text'>{$errorMessage}</p>
                                <a href='attendance-reports.php' class='btn btn-primary'>Back to Reports</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        exit;
    }
}