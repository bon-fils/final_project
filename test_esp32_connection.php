<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP32 Connection Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        .warning { background: #fff3cd; color: #856404; }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover { background: #0056b3; }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .test-result {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔌 ESP32 Fingerprint Scanner - Connection Test</h1>
        
        <div class="status info">
            <strong>ESP32 Configuration:</strong><br>
            IP Address: <code><?php echo ESP32_IP; ?></code><br>
            Port: <code><?php echo ESP32_PORT; ?></code><br>
            Timeout: <code><?php echo ESP32_TIMEOUT; ?></code> seconds
        </div>

        <div class="test-controls">
            <button onclick="testConnection()">🔍 Test Connection</button>
            <button onclick="testStatus()">📊 Get Status</button>
            <button onclick="testScan()">👆 Test Scan</button>
            <button onclick="clearResults()">🗑️ Clear Results</button>
        </div>

        <div id="results"></div>
    </div>

    <script>
        const ESP32_URL = 'http://<?php echo ESP32_IP; ?>:<?php echo ESP32_PORT; ?>';

        async function testConnection() {
            showLoading('Testing connection to ESP32...');
            
            try {
                const response = await fetch(ESP32_URL + '/status', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                showResult('success', '✅ Connection Successful!', 
                    `ESP32 is online and responding at ${ESP32_URL}`, data);
                    
            } catch (error) {
                showResult('error', '❌ Connection Failed', 
                    error.message, {
                        error: error.toString(),
                        esp32_url: ESP32_URL,
                        troubleshooting: [
                            'Check ESP32 is powered on',
                            'Verify ESP32 is connected to WiFi (CAPTAIN)',
                            'Confirm IP address: <?php echo ESP32_IP; ?>',
                            'Check same network as PC',
                            'Try ping from command: ping <?php echo ESP32_IP; ?>'
                        ]
                    });
            }
        }

        async function testStatus() {
            showLoading('Requesting ESP32 status...');
            
            try {
                const response = await fetch(ESP32_URL + '/status');
                const data = await response.json();
                
                showResult('success', '📊 Status Retrieved', 
                    'ESP32 status information:', data);
                    
            } catch (error) {
                showResult('error', '❌ Status Request Failed', 
                    error.message, {error: error.toString()});
            }
        }

        async function testScan() {
            showLoading('Testing fingerprint scan endpoint...<br>Place finger on sensor now!');
            
            try {
                const response = await fetch(ESP32_URL + '/scan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({action: 'scan'})
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    showResult('success', '✅ Scan Successful!', 
                        `Fingerprint detected!<br>ID: ${data.fingerprint_id}<br>Confidence: ${data.confidence}%`, 
                        data);
                } else {
                    showResult('warning', '⚠️ Scan Result', 
                        data.message || 'No fingerprint detected', data);
                }
                
            } catch (error) {
                showResult('error', '❌ Scan Failed', 
                    error.message, {error: error.toString()});
            }
        }

        function showLoading(message) {
            document.getElementById('results').innerHTML = `
                <div class="test-result">
                    <div class="status info">
                        <div style="display: flex; align-items: center;">
                            <div style="margin-right: 10px;">⏳</div>
                            <div>${message}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        function showResult(type, title, message, data = null) {
            const timestamp = new Date().toLocaleTimeString();
            let html = `
                <div class="test-result">
                    <div class="status ${type}">
                        <strong>${title}</strong><br>
                        ${message}<br>
                        <small>Time: ${timestamp}</small>
                    </div>
            `;
            
            if (data) {
                html += `
                    <details>
                        <summary style="cursor: pointer; padding: 10px; background: #f8f9fa; margin-top: 10px; border-radius: 3px;">
                            <strong>📋 Full Response Data (click to expand)</strong>
                        </summary>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </details>
                `;
            }
            
            html += '</div>';
            
            document.getElementById('results').innerHTML = html + document.getElementById('results').innerHTML;
        }

        function clearResults() {
            document.getElementById('results').innerHTML = '';
        }

        // Auto-test on page load
        window.addEventListener('load', function() {
            setTimeout(testConnection, 500);
        });
    </script>
</body>
</html>

<?php
require_once "config.php";
?>
