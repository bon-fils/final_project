/**
 * Enhanced ESP32 Fingerprint System
 * Complete fingerprint enrollment and identification system with web server
 * Integrates with PHP attendance system
 * 
 * Hardware Requirements:
 * - ESP32 Dev Board
 * - Fingerprint Sensor (AS608/GT-511C3)
 * - OLED Display (SSD1306)
 * - LEDs (Red, Yellow, Green)
 * - Resistors and connecting wires
 * 
 * Connections:
 * - Fingerprint Sensor: RX=16, TX=17
 * - OLED Display: SDA=21, SCL=22
 * - LEDs: Red=2, Yellow=4, Green=5
 */

#include <WiFi.h>
#include <WebServer.h>
#include <ESPmDNS.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <SoftwareSerial.h>
#include <Adafruit_Fingerprint.h>

// WiFi Configuration
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
IPAddress local_IP(192, 168, 1, 100);
IPAddress gateway(192, 168, 1, 1);
IPAddress subnet(255, 255, 255, 0);

// Hardware Configuration
#define FINGERPRINT_RX_PIN 16
#define FINGERPRINT_TX_PIN 17
#define LED_RED_PIN 2
#define LED_YELLOW_PIN 4
#define LED_GREEN_PIN 5
#define OLED_RESET -1

// Initialize Components
WebServer server(80);
SoftwareSerial fingerprintSerial(FINGERPRINT_RX_PIN, FINGERPRINT_TX_PIN);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&fingerprintSerial);
Adafruit_SSD1306 display(128, 64, &Wire, OLED_RESET);

// Global Variables
bool enrollmentActive = false;
uint8_t enrollmentId = 0;
String enrollmentStudentName = "";
String enrollmentRegNo = "";

void setup() {
    Serial.begin(115200);
    
    // Initialize hardware
    initializeHardware();
    
    // Connect to WiFi
    connectToWiFi();
    
    // Initialize fingerprint sensor
    initializeFingerprintSensor();
    
    // Setup web server routes
    setupWebServer();
    
    // Start web server
    server.begin();
    
    Serial.println("ESP32 Fingerprint System Ready!");
    displayMessage("System Ready");
    setLEDStatus("ready");
}

void loop() {
    server.handleClient();
    
    // Handle enrollment process
    if (enrollmentActive) {
        handleEnrollmentProcess();
    }
    
    delay(100);
}

void initializeHardware() {
    // Initialize OLED display
    if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
        Serial.println("OLED initialization failed!");
    }
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(SSD1306_WHITE);
    display.setCursor(0, 0);
    display.println("Initializing...");
    display.display();
    
    // Initialize LED pins
    pinMode(LED_RED_PIN, OUTPUT);
    pinMode(LED_YELLOW_PIN, OUTPUT);
    pinMode(LED_GREEN_PIN, OUTPUT);
    
    // Set initial LED state
    setLEDStatus("idle");
}

void connectToWiFi() {
    WiFi.config(local_IP, gateway, subnet);
    WiFi.begin(ssid, password);
    
    displayMessage("Connecting to WiFi...");
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(1000);
        attempts++;
        Serial.print(".");
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nWiFi Connected!");
        Serial.print("IP Address: ");
        Serial.println(WiFi.localIP());
        displayMessage("WiFi Connected");
        setLEDStatus("ready");
    } else {
        Serial.println("\nWiFi Connection Failed!");
        displayMessage("WiFi Failed");
        setLEDStatus("error");
    }
}

void initializeFingerprintSensor() {
    finger.begin(57600);
    
    if (finger.verifyPassword()) {
        Serial.println("Fingerprint sensor found!");
        displayMessage("Sensor Ready");
    } else {
        Serial.println("Fingerprint sensor not found!");
        displayMessage("Sensor Error");
        setLEDStatus("error");
    }
}

void setupWebServer() {
    // Enable CORS for browser requests
    server.enableCORS(true);
    
    // API Endpoints
    server.on("/identify", HTTP_GET, handleIdentify);
    server.on("/status", HTTP_GET, handleStatus);
    server.on("/display", HTTP_GET, handleDisplay);
    server.on("/enroll", HTTP_POST, handleEnroll);
    server.on("/test", HTTP_GET, handleTest);
    
    // Root endpoint
    server.on("/", HTTP_GET, []() {
        server.send(200, "text/html", getStatusPage());
    });
}

void handleIdentify() {
    Serial.println("Fingerprint identification requested");
    
    if (enrollmentActive) {
        server.send(400, "application/json", "{\"success\":false,\"error\":\"Enrollment in progress\"}");
        return;
    }
    
    displayMessage("Place finger to identify");
    setLEDStatus("scanning");
    
    // Get fingerprint image
    int p = finger.getImage();
    if (p != FINGERPRINT_OK) {
        server.send(400, "application/json", "{\"success\":false,\"error\":\"No finger detected\"}");
        setLEDStatus("error");
        return;
    }
    
    // Convert image to template
    p = finger.image2Tz();
    if (p != FINGERPRINT_OK) {
        server.send(400, "application/json", "{\"success\":false,\"error\":\"Image conversion failed\"}");
        setLEDStatus("error");
        return;
    }
    
    // Search for matching fingerprint
    p = finger.fingerFastSearch();
    if (p != FINGERPRINT_OK) {
        server.send(400, "application/json", "{\"success\":false,\"error\":\"No match found\"}");
        setLEDStatus("error");
        return;
    }
    
    // Success - fingerprint identified
    String response = "{\"success\":true,\"fingerprint_id\":\"" + String(finger.fingerID) + "\",\"confidence\":\"" + String(finger.confidence) + "\"}";
    server.send(200, "application/json", response);
    
    displayMessage("Fingerprint identified");
    setLEDStatus("success");
    
    // Reset LED after 2 seconds
    delay(2000);
    setLEDStatus("ready");
}

void handleStatus() {
    String response = "{";
    response += "\"status\":\"ok\",";
    response += "\"fingerprint_sensor\":\"" + String(finger.verifyPassword() ? "connected" : "disconnected") + "\",";
    response += "\"wifi\":\"" + String(WiFi.status() == WL_CONNECTED ? "connected" : "disconnected") + "\",";
    response += "\"ip\":\"" + WiFi.localIP().toString() + "\",";
    response += "\"enrollment_active\":\"" + String(enrollmentActive ? "true" : "false") + "\"";
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

void handleEnroll() {
    if (server.hasArg("plain")) {
        // Handle JSON body
        String body = server.arg("plain");
        DynamicJsonDocument doc(1024);
        deserializeJson(doc, body);
        
        if (doc.containsKey("id")) {
            enrollmentId = doc["id"];
            enrollmentStudentName = doc["student_name"] | "";
            enrollmentRegNo = doc["reg_no"] | "";
            
            startEnrollment();
            server.send(200, "application/json", "{\"success\":true,\"message\":\"Enrollment started\"}");
        } else {
            server.send(400, "application/json", "{\"success\":false,\"error\":\"No ID parameter\"}");
        }
    } else {
        server.send(400, "application/json", "{\"success\":false,\"error\":\"Invalid request format\"}");
    }
}

void handleTest() {
    server.send(200, "application/json", "{\"success\":true,\"message\":\"ESP32 Fingerprint System is working\"}");
}

void startEnrollment() {
    enrollmentActive = true;
    displayMessage("Enrollment Started");
    setLEDStatus("enrolling");
    
    Serial.println("Starting enrollment for ID: " + String(enrollmentId));
}

void handleEnrollmentProcess() {
    static int step = 0;
    static unsigned long lastStepTime = 0;
    
    if (millis() - lastStepTime < 2000) return; // Wait 2 seconds between steps
    
    switch (step) {
        case 0:
            displayMessage("Place finger on sensor");
            setLEDStatus("waiting");
            step++;
            break;
            
        case 1:
            if (enrollFingerprintStep1()) {
                step++;
            } else {
                step = 0; // Restart if failed
            }
            break;
            
        case 2:
            displayMessage("Remove finger");
            setLEDStatus("ready");
            step++;
            break;
            
        case 3:
            if (enrollFingerprintStep2()) {
                step++;
            } else {
                step = 0; // Restart if failed
            }
            break;
            
        case 4:
            displayMessage("Place same finger");
            setLEDStatus("waiting");
            step++;
            break;
            
        case 5:
            if (enrollFingerprintStep3()) {
                enrollmentActive = false;
                displayMessage("Enrollment Complete!");
                setLEDStatus("success");
                step = 0;
                
                // Send completion notification
                sendEnrollmentComplete();
            } else {
                step = 0; // Restart if failed
            }
            break;
    }
    
    lastStepTime = millis();
}

bool enrollFingerprintStep1() {
    int p = finger.getImage();
    if (p != FINGERPRINT_OK) {
        displayMessage("No finger detected");
        return false;
    }
    
    p = finger.image2Tz(1);
    if (p != FINGERPRINT_OK) {
        displayMessage("Image conversion failed");
        return false;
    }
    
    displayMessage("Finger 1 captured");
    return true;
}

bool enrollFingerprintStep2() {
    int p = finger.getImage();
    if (p != FINGERPRINT_OK) {
        displayMessage("No finger detected");
        return false;
    }
    
    p = finger.image2Tz(2);
    if (p != FINGERPRINT_OK) {
        displayMessage("Image conversion failed");
        return false;
    }
    
    displayMessage("Finger 2 captured");
    return true;
}

bool enrollFingerprintStep3() {
    int p = finger.createModel();
    if (p != FINGERPRINT_OK) {
        displayMessage("Template creation failed");
        return false;
    }
    
    p = finger.storeModel(enrollmentId);
    if (p != FINGERPRINT_OK) {
        displayMessage("Storage failed");
        return false;
    }
    
    displayMessage("Enrollment successful!");
    return true;
}

void sendEnrollmentComplete() {
    // This could send a notification to the PHP system
    // For now, just log it
    Serial.println("Enrollment completed for ID: " + String(enrollmentId));
}

void displayMessage(String message) {
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(SSD1306_WHITE);
    display.setCursor(0, 0);
    display.println(message);
    display.display();
    
    Serial.println("Display: " + message);
}

void setLEDStatus(String status) {
    // Turn off all LEDs first
    digitalWrite(LED_RED_PIN, LOW);
    digitalWrite(LED_YELLOW_PIN, LOW);
    digitalWrite(LED_GREEN_PIN, LOW);
    
    if (status == "idle" || status == "ready") {
        digitalWrite(LED_RED_PIN, HIGH);
    } else if (status == "waiting" || status == "scanning" || status == "enrolling") {
        digitalWrite(LED_YELLOW_PIN, HIGH);
    } else if (status == "success") {
        digitalWrite(LED_GREEN_PIN, HIGH);
    } else if (status == "error") {
        // Blink red LED
        digitalWrite(LED_RED_PIN, HIGH);
        delay(100);
        digitalWrite(LED_RED_PIN, LOW);
        delay(100);
        digitalWrite(LED_RED_PIN, HIGH);
    }
}

String getStatusPage() {
    return "<!DOCTYPE html><html><head><title>ESP32 Fingerprint System</title></head><body>"
           "<h1>ESP32 Fingerprint System</h1>"
           "<p>Status: Ready</p>"
           "<p>IP: " + WiFi.localIP().toString() + "</p>"
           "<p>Fingerprint Sensor: " + String(finger.verifyPassword() ? "Connected" : "Disconnected") + "</p>"
           "<h2>API Endpoints:</h2>"
           "<ul>"
           "<li>GET /identify - Identify fingerprint</li>"
           "<li>GET /status - Get system status</li>"
           "<li>GET /display?message=text - Display message</li>"
           "<li>POST /enroll - Start enrollment</li>"
           "<li>GET /test - Test endpoint</li>"
           "</ul>"
           "</body></html>";
}
