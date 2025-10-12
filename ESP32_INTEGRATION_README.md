# ESP32 Fingerprint Scanner Integration

## Overview
This document explains how the ESP32 fingerprint scanner integrates with the PHP attendance system.

## ESP32 Endpoints

The ESP32 device should expose the following HTTP endpoints:

### GET /identify
Identifies a fingerprint and returns the fingerprint ID.

**Response Format:**
```json
{
  "success": true,
  "fingerprint_id": "12345"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "No fingerprint found"
}
```

### GET /status
Returns the current status of the ESP32 device.

**Response Format:**
```json
{
  "status": "ok",
  "fingerprint_sensor": "connected",
  "wifi": "connected",
  "ip": "192.168.1.100"
}
```

### GET /display
Displays a message on the ESP32 OLED screen.

**Parameters:**
- `message` (string): Message to display

**Example:** `GET /display?message=Welcome%20to%20RP%20Polytechnic`

## PHP Integration Flow

### 1. Fingerprint Attendance Process

1. User clicks "Scan Fingerprint" button on attendance-session.php
2. JavaScript calls ESP32 `/identify` endpoint
3. ESP32 returns fingerprint_id if match found
4. JavaScript calls PHP `/api/mark_attendance.php?method=finger&fingerprint_id=XX&session_id=YY`
5. PHP validates session and marks attendance

### 2. ESP32 Request/Response Examples

#### Successful Identification:
```javascript
// ESP32 Response
{
  "success": true,
  "fingerprint_id": "12345"
}

// PHP API Call
GET /api/mark_attendance.php?method=finger&fingerprint_id=12345&session_id=1

// PHP Response
{
  "status": "success",
  "message": "Attendance Marked",
  "student": {
    "id": 1,
    "name": "John Doe",
    "registration_number": "RP001"
  },
  "method": "finger",
  "timestamp": "2025-01-15 10:30:00"
}
```

#### Fingerprint Not Registered:
```javascript
// ESP32 Response
{
  "success": true,
  "fingerprint_id": "99999"
}

// PHP API Call Response
{
  "status": "error",
  "message": "Fingerprint Not Registered"
}
```

#### Session Not Active:
```javascript
// PHP API Call Response
{
  "status": "error",
  "message": "Session Not Active"
}
```

#### Already Marked Today:
```javascript
// PHP API Call Response
{
  "status": "error",
  "message": "Already Marked Today"
}
```

## ESP32 Arduino Code Template

```cpp
#include <WiFi.h>
#include <WebServer.h>
#include <Adafruit_Fingerprint.h>
#include <Wire.h>
#include <Adafruit_SSD1306.h>

// WiFi credentials
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

// ESP32 IP (configure as needed)
IPAddress local_IP(192, 168, 1, 100);
IPAddress gateway(192, 168, 1, 1);
IPAddress subnet(255, 255, 255, 0);

// Hardware pins
#define RX_PIN 16
#define TX_PIN 17
#define OLED_RESET -1

WebServer server(80);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&Serial2);
Adafruit_SSD1306 display(128, 64, &Wire, OLED_RESET);

void setup() {
  Serial.begin(115200);

  // Initialize OLED
  if(!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println("SSD1306 allocation failed");
    for(;;);
  }
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(WHITE);

  // Connect to WiFi
  WiFi.config(local_IP, gateway, subnet);
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  Serial.println("Connected to WiFi");
  Serial.println(WiFi.localIP());

  // Initialize fingerprint sensor
  Serial2.begin(57600, SERIAL_8N1, RX_PIN, TX_PIN);
  finger.begin(57600);

  if (finger.verifyPassword()) {
    Serial.println("Found fingerprint sensor!");
  } else {
    Serial.println("Did not find fingerprint sensor :(");
    while (1);
  }

  // Setup web server routes
  server.on("/identify", handleIdentify);
  server.on("/status", handleStatus);
  server.on("/display", handleDisplay);

  server.begin();
  Serial.println("HTTP server started");

  displayMessage("System Ready");
}

void loop() {
  server.handleClient();
}

void handleIdentify() {
  displayMessage("Place finger on sensor");

  int p = finger.getImage();
  if (p != FINGERPRINT_OK) {
    server.send(200, "application/json", "{\"success\":false,\"error\":\"No finger detected\"}");
    return;
  }

  p = finger.image2Tz();
  if (p != FINGERPRINT_OK) {
    server.send(200, "application/json", "{\"success\":false,\"error\":\"Image conversion failed\"}");
    return;
  }

  p = finger.fingerFastSearch();
  if (p != FINGERPRINT_OK) {
    server.send(200, "application/json", "{\"success\":false,\"error\":\"No match found\"}");
    return;
  }

  // Found a match!
  String response = "{\"success\":true,\"fingerprint_id\":\"" + String(finger.fingerID) + "\"}";
  server.send(200, "application/json", response);

  displayMessage("Fingerprint identified");
}

void handleStatus() {
  String response = "{";
  response += "\"status\":\"ok\",";
  response += "\"fingerprint_sensor\":\"connected\",";
  response += "\"wifi\":\"connected\",";
  response += "\"ip\":\"" + WiFi.localIP().toString() + "\"";
  response += "}";

  server.send(200, "application/json", response);
}

void handleDisplay() {
  if (server.hasArg("message")) {
    String message = server.arg("message");
    displayMessage(message);
    server.send(200, "application/json", "{\"success\":true}");
  } else {
    server.send(400, "application/json", "{\"success\":false,\"error\":\"No message parameter\"}");
  }
}

void displayMessage(String message) {
  display.clearDisplay();
  display.setCursor(0, 0);
  display.println(message);
  display.display();
}
```

## Configuration

### ESP32 IP Address
Update the `esp32IP` variable in `attendance-session.php` to match your ESP32's IP address:

```javascript
let esp32IP = '192.168.1.100'; // Change this to your ESP32 IP
```

### Network Setup
Ensure your ESP32 and PHP server are on the same network and can communicate with each other.

## Troubleshooting

1. **ESP32 not responding**: Check WiFi connection and IP address
2. **Fingerprint not recognized**: Ensure fingerprints are properly enrolled
3. **CORS errors**: ESP32 should allow cross-origin requests
4. **Session validation fails**: Ensure session is active and biometric method matches

## Security Considerations

1. Use HTTPS in production
2. Implement proper authentication for ESP32 endpoints
3. Validate all input parameters
4. Rate limit API calls to prevent abuse
5. Log all attendance marking attempts for audit purposes