<?php
/**
 * Simple AJAX Test - No Session Required
 */

header('Content-Type: application/json');

if (isset($_GET['action'])) {
    try {
        require_once "config.php";

        switch ($_GET['action']) {
            case 'ping':
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Backend is responding',
                    'timestamp' => time(),
                    'database' => 'connected'
                ]);
                break;

            case 'users':
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                $count = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'status' => 'success',
                    'user_count' => $count['count'],
                    'message' => 'Users query successful'
                ]);
                break;

            default:
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid action'
                ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple AJAX Test</title>
</head>
<body>
    <h1>Simple AJAX Test</h1>

    <button onclick="testPing()">Test Ping</button>
    <button onclick="testUsers()">Test Users</button>

    <div id="result"></div>

    <script>
        function testPing() {
            fetch('simple_ajax_test.php?action=ping')
                .then(r => r.json())
                .then(d => {
                    document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(d, null, 2) + '</pre>';
                    console.log('Ping Result:', d);
                })
                .catch(e => {
                    document.getElementById('result').innerHTML = '<div style="color: red;">Error: ' + e.message + '</div>';
                });
        }

        function testUsers() {
            fetch('simple_ajax_test.php?action=users')
                .then(r => r.json())
                .then(d => {
                    document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(d, null, 2) + '</pre>';
                    console.log('Users Result:', d);
                })
                .catch(e => {
                    document.getElementById('result').innerHTML = '<div style="color: red;">Error: ' + e.message + '</div>';
                });
        }
    </script>
</body>
</html>