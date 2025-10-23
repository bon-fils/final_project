<?php
/**
 * Check which students have photos in the database
 */

require_once "config.php";

try {
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.reg_no,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            s.option_id,
            s.year_level,
            LENGTH(s.student_photos) as photo_size_bytes,
            CASE 
                WHEN s.student_photos IS NULL THEN '❌ No photo'
                WHEN LENGTH(s.student_photos) < 1000 THEN '⚠️ Photo too small'
                ELSE '✅ Has photo'
            END as photo_status
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active'
        ORDER BY s.option_id, s.year_level, s.reg_no
    ");
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Student Photos Check</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
            th { background-color: #4CAF50; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .has-photo { color: green; font-weight: bold; }
            .no-photo { color: red; font-weight: bold; }
            .warning { color: orange; font-weight: bold; }
            .summary { background: #e7f3ff; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>📷 Student Photos Status</h1>";
    
    // Calculate summary
    $total = count($students);
    $with_photos = 0;
    $without_photos = 0;
    
    foreach ($students as $student) {
        if ($student['photo_size_bytes'] > 1000) {
            $with_photos++;
        } else {
            $without_photos++;
        }
    }
    
    echo "<div class='summary'>
        <h3>Summary:</h3>
        <p><strong>Total Students:</strong> $total</p>
        <p><strong>✅ With Photos:</strong> $with_photos (" . round(($with_photos/$total)*100, 1) . "%)</p>
        <p><strong>❌ Without Photos:</strong> $without_photos (" . round(($without_photos/$total)*100, 1) . "%)</p>
    </div>";
    
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Reg No</th>
            <th>Name</th>
            <th>Option</th>
            <th>Year</th>
            <th>Photo Size</th>
            <th>Status</th>
        </tr>";
    
    foreach ($students as $student) {
        $status_class = 'no-photo';
        if ($student['photo_size_bytes'] > 1000) {
            $status_class = 'has-photo';
        } elseif ($student['photo_size_bytes'] > 0) {
            $status_class = 'warning';
        }
        
        $photo_size = $student['photo_size_bytes'] ? number_format($student['photo_size_bytes']) . ' bytes' : 'NULL';
        
        echo "<tr>
            <td>{$student['id']}</td>
            <td>{$student['reg_no']}</td>
            <td>{$student['name']}</td>
            <td>{$student['option_id']}</td>
            <td>{$student['year_level']}</td>
            <td>$photo_size</td>
            <td class='$status_class'>{$student['photo_status']}</td>
        </tr>";
    }
    
    echo "</table>
    
    <div style='margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 5px;'>
        <h3>⚠️ Important Notes:</h3>
        <ul>
            <li>Students without photos cannot be recognized by face recognition</li>
            <li>Photos should be clear, front-facing, and well-lit</li>
            <li>Only one face should be in each photo</li>
            <li>Photos are stored in the <code>students.student_photos</code> column as BLOB</li>
            <li>To add photos, use the student registration form</li>
        </ul>
    </div>
    
    </body>
    </html>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
