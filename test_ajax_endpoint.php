<?php
/**
 * AJAX Endpoint Test Script
 * Tests the manage-users.php AJAX functionality
 */

echo "<h1>AJAX Endpoint Test</h1>";

if (isset($_GET['test'])) {
    // Simulate AJAX request
    $_GET['ajax'] = '1';
    $_GET['action'] = $_GET['test'];

    require_once "manage-users.php";
    exit;
}

echo "<h2>Test AJAX Endpoints:</h2>";
echo "<ul>";
echo "<li><a href='?test=debug' target='_blank'>Test Debug Endpoint</a></li>";
echo "<li><a href='?test=get_users' target='_blank'>Test Get Users Endpoint</a></li>";
echo "</ul>";

echo "<h2>Direct AJAX Test:</h2>";
echo "<button onclick='testAjax()'>Test AJAX Call</button>";
echo "<div id='result'></div>";

echo "
<script>
function testAjax() {
    fetch('manage-users.php?ajax=1&action=debug')
        .then(r => r.json())
        .then(d => {
            document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(d, null, 2) + '</pre>';
            console.log('AJAX Test Result:', d);
        })
        .catch(e => {
            document.getElementById('result').innerHTML = '<div style=\"color: red;\">Error: ' + e.message + '</div>';
            console.error('AJAX Test Error:', e);
        });
}
</script>
";

echo "<h2>Instructions:</h2>";
echo "<p>1. Make sure you're logged in as an admin user</p>";
echo "<p>2. Click the test buttons above to test AJAX endpoints</p>";
echo "<p>3. Use browser developer tools (F12) to check console for detailed logs</p>";
echo "<p>4. If tests fail, check the debug endpoint for system information</p>";
?>