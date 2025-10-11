<?php
/**
 * Face Recognition Logs Viewer
 * Displays analysis of face recognition test results
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Face Recognition Logs Analysis</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .danger { color: #dc3545; }
        .info { color: #17a2b8; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 5px; flex: 1; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; font-size: 14px; }
        .filter-form { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .filter-form select, .filter-form input { padding: 8px; margin: 0 10px; border: 1px solid #ddd; border-radius: 3px; }
        .filter-form button { padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        .filter-form button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📊 Face Recognition Logs Analysis</h1>
        <p>Monitor and analyze face recognition performance across all test attempts.</p>";

try {
    // Get filter parameters
    $method = $_GET['method'] ?? '';
    $student = $_GET['student'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    // Build query with filters
    $where = [];
    $params = [];

    if ($method) {
        $where[] = "processing_method = ?";
        $params[] = $method;
    }

    if ($student) {
        $where[] = "test_student_reg_no = ?";
        $params[] = $student;
    }

    if ($date_from) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $date_to;
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    // Get statistics
    $statsQuery = "SELECT
        COUNT(*) as total_comparisons,
        AVG(comparison_score) as avg_score,
        MAX(comparison_score) as max_score,
        MIN(comparison_score) as min_score,
        SUM(CASE WHEN match_found = 1 THEN 1 ELSE 0 END) as successful_matches,
        COUNT(DISTINCT student_id) as unique_students_tested,
        COUNT(DISTINCT DATE(created_at)) as days_tested
        FROM face_recognition_logs $whereClause";

    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get method breakdown
    $methodQuery = "SELECT
        processing_method,
        COUNT(*) as count,
        AVG(comparison_score) as avg_score,
        SUM(CASE WHEN match_found = 1 THEN 1 ELSE 0 END) as matches
        FROM face_recognition_logs $whereClause
        GROUP BY processing_method
        ORDER BY count DESC";

    $stmt = $pdo->prepare($methodQuery);
    $stmt->execute($params);
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent logs
    $logsQuery = "SELECT * FROM face_recognition_logs $whereClause
        ORDER BY created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($logsQuery);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique students for filter
    $studentsQuery = "SELECT DISTINCT student_reg_no FROM face_recognition_logs ORDER BY student_reg_no";
    $students = $pdo->query($studentsQuery)->fetchAll(PDO::FETCH_COLUMN);

    echo "
    <div class='stats'>
        <div class='stat-card'>
            <div class='stat-number'>{$stats['total_comparisons']}</div>
            <div class='stat-label'>Total Comparisons</div>
        </div>
        <div class='stat-card'>
            <div class='stat-number'>" . number_format($stats['avg_score'] * 100, 1) . "%</div>
            <div class='stat-label'>Average Score</div>
        </div>
        <div class='stat-card'>
            <div class='stat-number'>{$stats['successful_matches']}</div>
            <div class='stat-label'>Successful Matches</div>
        </div>
        <div class='stat-card'>
            <div class='stat-number'>{$stats['unique_students_tested']}</div>
            <div class='stat-label'>Students Tested</div>
        </div>
    </div>

    <h2>🔍 Filters</h2>
    <form class='filter-form' method='GET'>
        <select name='method'>
            <option value=''>All Methods</option>
            <option value='python_face_recognition'" . ($method == 'python_face_recognition' ? ' selected' : '') . ">Python AI</option>
            <option value='php_fallback'" . ($method == 'php_fallback' ? ' selected' : '') . ">PHP Fallback</option>
            <option value='php_pixel_comparison'" . ($method == 'php_pixel_comparison' ? ' selected' : '') . ">PHP Pixel</option>
            <option value='php_simple_comparison'" . ($method == 'php_simple_comparison' ? ' selected' : '') . ">PHP Simple</option>
        </select>

        <select name='student'>
            <option value=''>All Students</option>";
            foreach ($students as $student_reg) {
                $selected = ($student == $student_reg) ? ' selected' : '';
                echo "<option value='$student_reg'$selected>$student_reg</option>";
            }
        echo "</select>

        <input type='date' name='date_from' value='$date_from' placeholder='From Date'>
        <input type='date' name='date_to' value='$date_to' placeholder='To Date'>
        <button type='submit'>🔍 Filter</button>
        <a href='?' style='margin-left: 10px; color: #007bff;'>Clear Filters</a>
    </form>

    <h2>📈 Method Performance</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Comparisons</th>
                <th>Average Score</th>
                <th>Successful Matches</th>
                <th>Success Rate</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($methods as $method_data) {
        $success_rate = $method_data['count'] > 0 ? ($method_data['matches'] / $method_data['count']) * 100 : 0;
        echo "<tr>
            <td>{$method_data['processing_method']}</td>
            <td>{$method_data['count']}</td>
            <td>" . number_format($method_data['avg_score'] * 100, 1) . "%</td>
            <td>{$method_data['matches']}</td>
            <td>" . number_format($success_rate, 1) . "%</td>
        </tr>";
    }

    echo "</tbody>
    </table>

    <h2>📋 Recent Comparison Logs</h2>
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>Type</th>
                <th>Student</th>
                <th>Method</th>
                <th>Score</th>
                <th>Confidence</th>
                <th>Match</th>
                <th>Distance</th>
                <th>Image Size</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($logs as $log) {
        $match_class = $log['match_found'] ? 'success' : 'danger';
        $confidence_class = 'danger';
        if ($log['comparison_score'] > 0.8) $confidence_class = 'success';
        else if ($log['comparison_score'] > 0.6) $confidence_class = 'warning';

        echo "<tr>
            <td>" . date('H:i:s', strtotime($log['created_at'])) . "</td>
            <td class='" . ($log['student_type'] == 'test' ? 'info' : '') . "'>{$log['student_type']}</td>
            <td>{$log['student_reg_no']}</td>
            <td>{$log['processing_method']}</td>
            <td>" . number_format($log['comparison_score'] * 100, 1) . "%</td>
            <td class='$confidence_class'>{$log['confidence_level']}</td>
            <td class='$match_class'>" . ($log['match_found'] ? '✓' : '✗') . "</td>
            <td>" . number_format($log['distance'], 3) . "</td>
            <td>" . number_format($log['captured_image_size'] / 1024, 1) . " KB</td>
        </tr>";
    }

    echo "</tbody>
    </table>

    <h2>💡 Analysis Insights</h2>
    <ul>
        <li><strong>Python AI Recognition:</strong> Most accurate but requires face detection</li>
        <li><strong>PHP Pixel Comparison:</strong> Good fallback when GD is available</li>
        <li><strong>PHP Simple Comparison:</strong> Basic fallback using file size similarity</li>
        <li><strong>Success Rate:</strong> Higher scores indicate better face matching algorithms</li>
        <li><strong>Distance Metric:</strong> Lower values indicate better matches (for AI methods)</li>
    </ul>";

} catch (Exception $e) {
    echo "<h2 class='danger'>❌ Error: " . $e->getMessage() . "</h2>";
}

echo "
    </div>
</body>
</html>";
?>