<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Fingerprint IDs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #007bff;
            color: white;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .status-enrolled {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .status-not_enrolled {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .info-box {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            background: #e7f3ff;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #28a745;
        }
        .highlight {
            background: #fffacd;
            font-weight: bold;
        }
        input[type="number"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Fingerprint ID Database Check</h1>
        <p>This tool shows all students with fingerprint data and helps diagnose why fingerprints aren't matching.</p>

        <?php
        require_once "config.php";
        
        // Check if we're searching for a specific fingerprint_id
        $search_id = isset($_GET['search_id']) ? (int)$_GET['search_id'] : null;
        
        try {
            // Get all students with fingerprint data
            $stmt = $pdo->prepare("
                SELECT 
                    s.id,
                    s.user_id,
                    s.reg_no,
                    s.student_id_number,
                    s.fingerprint_id,
                    s.fingerprint_status,
                    s.fingerprint_enrolled_at,
                    s.option_id,
                    s.year_level,
                    s.status as student_status,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    o.name as option_name,
                    d.name as department_name
                FROM students s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN options o ON s.option_id = o.id
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.fingerprint_id IS NOT NULL
                ORDER BY s.fingerprint_id ASC
            ");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get count by status
            $enrolled_count = 0;
            $not_enrolled_count = 0;
            foreach ($students as $s) {
                if ($s['fingerprint_status'] === 'enrolled') $enrolled_count++;
                else $not_enrolled_count++;
            }
            
            echo '<div class="info-box">';
            echo '<strong>📊 Database Summary:</strong><br>';
            echo '✅ Students with Enrolled Fingerprints: <strong>' . $enrolled_count . '</strong><br>';
            echo '❌ Students with Non-Enrolled Fingerprints: <strong>' . $not_enrolled_count . '</strong><br>';
            echo '📋 Total Students with Fingerprint Data: <strong>' . count($students) . '</strong>';
            echo '</div>';
            
            // Search box
            echo '<div class="info-box">';
            echo '<form method="GET">';
            echo '<strong>🔎 Search by Fingerprint ID:</strong> ';
            echo '<input type="number" name="search_id" placeholder="Enter ID" value="' . ($search_id ?? '') . '">';
            echo '<button type="submit">Search</button>';
            if ($search_id) {
                echo ' <a href="check_fingerprint_ids.php"><button type="button">Clear Search</button></a>';
            }
            echo '</form>';
            echo '</div>';
            
            if ($search_id) {
                $found = false;
                foreach ($students as $s) {
                    if ($s['fingerprint_id'] == $search_id) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    echo '<div class="success">';
                    echo '✅ <strong>Fingerprint ID ' . $search_id . ' FOUND in database!</strong><br>';
                    echo 'See highlighted row below for details.';
                    echo '</div>';
                } else {
                    echo '<div class="warning">';
                    echo '❌ <strong>Fingerprint ID ' . $search_id . ' NOT FOUND in database!</strong><br><br>';
                    echo '<strong>This means:</strong><br>';
                    echo '1. This fingerprint is enrolled in the ESP32 scanner memory<br>';
                    echo '2. But the student record doesn\'t have this fingerprint_id value<br>';
                    echo '3. You need to re-enroll this student via student registration<br><br>';
                    echo '<strong>Action Required:</strong><br>';
                    echo '• Go to student registration page<br>';
                    echo '• Find the student<br>';
                    echo '• Re-enroll their fingerprint<br>';
                    echo '• The system will update fingerprint_id = ' . $search_id . ' in database';
                    echo '</div>';
                }
            }
            
            if (empty($students)) {
                echo '<div class="warning">⚠️ No students have fingerprint data in the database.</div>';
            } else {
                echo '<h2>📋 All Students with Fingerprint Data</h2>';
                echo '<table>';
                echo '<thead><tr>';
                echo '<th>Fingerprint ID</th>';
                echo '<th>Student Name</th>';
                echo '<th>Reg No</th>';
                echo '<th>Option</th>';
                echo '<th>Year Level</th>';
                echo '<th>Status</th>';
                echo '<th>Enrolled At</th>';
                echo '<th>Student Status</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($students as $student) {
                    $rowClass = ($search_id && $student['fingerprint_id'] == $search_id) ? ' class="highlight"' : '';
                    echo '<tr' . $rowClass . '>';
                    echo '<td><strong>' . $student['fingerprint_id'] . '</strong></td>';
                    echo '<td>' . htmlspecialchars($student['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($student['reg_no'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($student['option_name'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($student['year_level'] ?? 'NULL') . '</td>';
                    echo '<td><span class="status-' . $student['fingerprint_status'] . '">' . 
                         strtoupper(str_replace('_', ' ', $student['fingerprint_status'])) . '</span></td>';
                    echo '<td>' . ($student['fingerprint_enrolled_at'] ?? 'N/A') . '</td>';
                    echo '<td>' . ($student['student_status'] ?? 'N/A') . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            }
            
            // Show ESP32 scanner information
            echo '<h2>🔌 ESP32 Scanner Information</h2>';
            echo '<div class="info-box">';
            echo '<strong>ESP32 IP:</strong> <code>' . ESP32_IP . '</code><br>';
            echo '<strong>Port:</strong> <code>' . ESP32_PORT . '</code><br><br>';
            echo '<strong>How Fingerprint Matching Works:</strong><br>';
            echo '1. Student places finger on ESP32 sensor<br>';
            echo '2. ESP32 searches its memory and returns <code>fingerprint_id</code> (e.g., 5)<br>';
            echo '3. PHP searches database for student with <code>fingerprint_id = 5</code><br>';
            echo '4. If found and status=\'enrolled\' → Attendance marked ✅<br>';
            echo '5. If not found → "Not enrolled" error ❌<br><br>';
            echo '<strong>Common Issues:</strong><br>';
            echo '• ESP32 returns ID 5, but database has NULL or different ID<br>';
            echo '• Student enrolled but <code>fingerprint_status</code> not set to \'enrolled\'<br>';
            echo '• Student in different year level or option than session';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="warning">❌ Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="attendance-session.php" style="background: #007bff; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; display: inline-block;">
                ← Back to Attendance Session
            </a>
            <a href="test_esp32_connection.php" style="background: #28a745; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; display: inline-block; margin-left: 10px;">
                🔌 Test ESP32 Connection
            </a>
        </div>
    </div>
</body>
</html>
