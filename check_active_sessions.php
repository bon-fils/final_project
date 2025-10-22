<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Active Sessions</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
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
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .status-active {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .status-completed {
            background: #6c757d;
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
        .action-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .action-btn:hover {
            background: #c82333;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #28a745;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Active Attendance Sessions Checker</h1>
        
        <?php
        require_once "config.php";
        session_start();
        
        if (!isset($_SESSION["user_id"])) {
            echo '<div class="warning">⚠️ You are not logged in. <a href="login.php">Login here</a></div>';
            exit;
        }
        
        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['role'] ?? 'Unknown';
        
        echo '<div class="info-box">';
        echo '<strong>Current User:</strong> User ID: ' . $user_id . ' | Role: ' . $user_role;
        echo '</div>';
        
        // Handle session end request
        if (isset($_POST['end_session_id'])) {
            $session_id_to_end = (int)$_POST['end_session_id'];
            try {
                $end_stmt = $pdo->prepare("
                    UPDATE attendance_sessions 
                    SET status = 'completed', end_time = NOW() 
                    WHERE id = ? AND lecturer_id = ?
                ");
                $end_stmt->execute([$session_id_to_end, $user_id]);
                echo '<div class="success">✅ Session #' . $session_id_to_end . ' has been ended!</div>';
            } catch (Exception $e) {
                echo '<div class="warning">❌ Failed to end session: ' . $e->getMessage() . '</div>';
            }
        }
        
        // Get all YOUR active sessions
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    ats.id,
                    ats.session_date,
                    ats.start_time,
                    ats.end_time,
                    ats.biometric_method,
                    ats.year_level,
                    ats.status,
                    c.name as course_name,
                    c.course_code,
                    o.name as option_name,
                    d.name as department_name,
                    CONCAT(u.first_name, ' ', u.last_name) as lecturer_name,
                    (SELECT COUNT(*) FROM attendance_records 
                     WHERE session_id = ats.id AND status = 'present') as students_present
                FROM attendance_sessions ats
                LEFT JOIN courses c ON ats.course_id = c.id
                LEFT JOIN options o ON ats.option_id = o.id
                LEFT JOIN departments d ON ats.department_id = d.id
                LEFT JOIN users u ON ats.lecturer_id = u.id
                WHERE ats.lecturer_id = ?
                ORDER BY ats.id DESC
                LIMIT 20
            ");
            $stmt->execute([$user_id]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($sessions)) {
                echo '<div class="info-box">ℹ️ You have no attendance sessions in the system.</div>';
            } else {
                $active_count = 0;
                $completed_count = 0;
                foreach ($sessions as $s) {
                    if ($s['status'] === 'active') $active_count++;
                    else $completed_count++;
                }
                
                echo '<h2>📊 Your Sessions Summary</h2>';
                echo '<div class="info-box">';
                echo '✅ Active Sessions: <strong>' . $active_count . '</strong><br>';
                echo '⏹️ Completed Sessions: <strong>' . $completed_count . '</strong><br>';
                echo '📋 Total Sessions: <strong>' . count($sessions) . '</strong>';
                echo '</div>';
                
                if ($active_count > 0) {
                    echo '<div class="warning">';
                    echo '⚠️ <strong>You have ' . $active_count . ' active session(s).</strong><br>';
                    echo 'You can only have ONE active session at a time. End old sessions before starting new ones.';
                    echo '</div>';
                }
                
                echo '<h2>📋 Your Sessions (Last 20)</h2>';
                echo '<table>';
                echo '<thead><tr>';
                echo '<th>ID</th>';
                echo '<th>Date</th>';
                echo '<th>Start Time</th>';
                echo '<th>Course</th>';
                echo '<th>Option</th>';
                echo '<th>Year</th>';
                echo '<th>Method</th>';
                echo '<th>Present</th>';
                echo '<th>Status</th>';
                echo '<th>Action</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($sessions as $session) {
                    echo '<tr>';
                    echo '<td>#' . $session['id'] . '</td>';
                    echo '<td>' . ($session['session_date'] ?? 'N/A') . '</td>';
                    echo '<td>' . ($session['start_time'] ?? 'N/A') . '</td>';
                    echo '<td>' . ($session['course_name'] ?? 'NULL') . ' (' . ($session['course_code'] ?? 'NULL') . ')</td>';
                    echo '<td>' . ($session['option_name'] ?? 'NULL') . '</td>';
                    echo '<td>' . ($session['year_level'] ?? 'NULL') . '</td>';
                    echo '<td>' . ($session['biometric_method'] ?? 'NULL') . '</td>';
                    echo '<td>' . ($session['students_present'] ?? 0) . '</td>';
                    echo '<td><span class="status-' . $session['status'] . '">' . strtoupper($session['status']) . '</span></td>';
                    echo '<td>';
                    if ($session['status'] === 'active') {
                        echo '<form method="POST" style="display:inline;" onsubmit="return confirm(\'End this session?\');">';
                        echo '<input type="hidden" name="end_session_id" value="' . $session['id'] . '">';
                        echo '<button type="submit" class="action-btn">End Session</button>';
                        echo '</form>';
                    } else {
                        echo '<span style="color: #999;">Completed</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            }
            
        } catch (Exception $e) {
            echo '<div class="warning">❌ Database Error: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="attendance-session.php" style="background: #007bff; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; display: inline-block;">
                ← Back to Attendance Session
            </a>
            <a href="close_all_sessions.php" style="background: #dc3545; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; display: inline-block; margin-left: 10px;">
                🗑️ Close All Active Sessions
            </a>
        </div>
    </div>
</body>
</html>
