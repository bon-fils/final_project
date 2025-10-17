#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <Adafruit_Fingerprint.h>
#include <HardwareSerial.h>
#include <WiFi.h>
#include <WebServer.h>

#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET -1
#define SCREEN_ADDRESS 0x3C

Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

HardwareSerial mySerial(2); // UART2 on ESP32 (RX = GPIO16, TX = GPIO17)
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

// LED Pins (your chosen pins)
#define LED_GREEN 4   // D4 -> Match found
#define LED_RED 2     // D2 -> No match / error
#define LED_YELLOW 18 // D18 -> Waiting / scanning

// WiFi credentials
const char *ssid = "CodeFusion";
const char *password = "12345678";

// PHP Server IP (where the attendance system is hosted)
const char *serverIP = "192.168.1.127";

WebServer server(80);

uint8_t enrollID = 1; // Default enrollment ID
bool continuousScanMode = false; // Continuous scan flag

// Function Prototypes
void showMessage(String msg);
void enrollFingerprint(uint8_t id);
void waitForFinger();
bool scanFingerprintOnce();
void handleIdentify();
void handleStatus();
void handleDisplay();
void handleEnroll();
void handleStartScan();
void handleStopScan();
void displayMessage(String message);
void turnOffLEDs();
void indicateError();
void indicateSuccess();

void setup()
{
  Serial.begin(115200);
  mySerial.begin(57600, SERIAL_8N1, 16, 17); // RX=16, TX=17
  Wire.begin(21, 22);                        // SDA, SCL

  // LED Setup
  pinMode(LED_GREEN, OUTPUT);
  pinMode(LED_RED, OUTPUT);
  pinMode(LED_YELLOW, OUTPUT);
  turnOffLEDs();

  // OLED setup
  if (!display.begin(SSD1306_SWITCHCAPVCC, SCREEN_ADDRESS))
  {
    Serial.println(F("SSD1306 allocation failed"));
    while (true)
      ;
  }
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);

  // Connect to WiFi
  displayMessage("Connecting to WiFi...");
  Serial.println("Connecting to WiFi...");
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20)
  {
    delay(1000);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED)
  {
    Serial.println("\nWiFi connected");
    Serial.println("IP address: ");
    Serial.println(WiFi.localIP());
    displayMessage("WiFi Connected\nIP: " + WiFi.localIP().toString());
  }
  else
  {
    Serial.println("WiFi connection failed");
    displayMessage("WiFi Failed!");
    while (true)
      ;
  }

  delay(2000);

  // Fingerprint setup
  finger.begin(57600);
  if (finger.verifyPassword())
  {
    showMessage("Sensor OK!");
    Serial.println("Sensor detected and ready");
    delay(1000);
    displayMessage("System Ready");
  }
  else
  {
    showMessage("Sensor not found!");
    Serial.println("Fingerprint sensor not found!");
    indicateError();
    while (true)
      ;
  }

  // Setup web server routes
  server.on("/identify", HTTP_GET, handleIdentify);
  server.on("/status", HTTP_GET, handleStatus);
  server.on("/display", HTTP_GET, handleDisplay);
  server.on("/enroll", HTTP_POST, handleEnroll);
  server.on("/start_scan", HTTP_GET, handleStartScan);
  server.on("/stop_scan", HTTP_GET, handleStopScan);

  server.enableCORS(true);
  server.begin();
  Serial.println("HTTP server started");
}

void loop()
{
  server.handleClient();

  if (continuousScanMode)
  {
    // Non-blocking-ish continuous scan: the function itself uses small waits
    scanFingerprintOnce();
    // small pause to reduce CPU burn and avoid overwhelming sensor
    delay(120);
  }
}

// Turn all LEDs off
void turnOffLEDs()
{
  digitalWrite(LED_GREEN, LOW);
  digitalWrite(LED_RED, LOW);
  digitalWrite(LED_YELLOW, LOW);
}

// show message centered-ish on OLED
void showMessage(String msg)
{
  display.clearDisplay();
  display.setCursor(0, 20);
  display.println(msg);
  display.display();
}

void displayMessage(String message)
{
  display.clearDisplay();
  display.setCursor(0, 0);
  display.println(message);
  display.display();
}

// Enhanced LED indication with proper state management
void indicateError()
{
  // Only activate red LED for scanning failures
  turnOffLEDs();
  digitalWrite(LED_RED, HIGH);
  delay(1000); // Keep red LED on for 1 second to show error
  digitalWrite(LED_RED, LOW);
}

void indicateSuccess()
{
  turnOffLEDs();
  for (int i = 0; i < 2; i++) {
    digitalWrite(LED_GREEN, HIGH);
    delay(300);
    digitalWrite(LED_GREEN, LOW);
    delay(200);
  }
  // Ensure LED is off after blinking
  digitalWrite(LED_GREEN, LOW);
}

void indicateWaiting()
{
  static unsigned long lastBlink = 0;
  if (millis() - lastBlink > 200) {
    digitalWrite(LED_YELLOW, !digitalRead(LED_YELLOW));
    lastBlink = millis();
  }
}

void indicateProcessing()
{
  static unsigned long lastBlink = 0;
  if (millis() - lastBlink > 100) {
    digitalWrite(LED_YELLOW, !digitalRead(LED_YELLOW));
    lastBlink = millis();
  }
}

// Wait for finger (blocking) with yellow LED and OLED message
void waitForFinger()
{
  digitalWrite(LED_YELLOW, HIGH);
  displayMessage("Place your finger...");
  int p = finger.getImage();
  while (p != FINGERPRINT_OK)
  {
    Serial.println("Waiting for finger...");
    p = finger.getImage();
    delay(100);
  }

  // finger detected
  digitalWrite(LED_YELLOW, LOW);
  showMessage("Finger Detected!");
  Serial.println("Finger detected!");
  delay(400);
}

// Global variables to track enrollment status
bool enrollmentSuccess = false;
String enrollmentErrorMessage = "";

// Enrollment logic with detailed error tracking
void enrollFingerprint(uint8_t id)
{
  enrollmentSuccess = false; // Reset flag
  enrollmentErrorMessage = ""; // Reset error message
  int p = -1;

  Serial.println("Starting fingerprint enrollment for ID: " + String(id));

  // Step 1: First scan
  Serial.println("Step 1: Waiting for first finger scan...");
  waitForFinger();
  p = finger.image2Tz(1);
  if (p != FINGERPRINT_OK)
  {
    enrollmentErrorMessage = "Failed to capture first fingerprint image";
    displayMessage("Error reading finger!");
    Serial.println("Error reading first image: " + String(p));
    indicateError();
    delay(2000);
    turnOffLEDs();
    displayMessage("System Ready");
    return;
  }
  Serial.println("First image captured successfully");

  // Step 2: Remove finger
  displayMessage("Remove finger");
  Serial.println("Step 2: Please remove finger...");
  delay(1500);
  int removeAttempts = 0;
  while (finger.getImage() != FINGERPRINT_NOFINGER && removeAttempts < 50)
  {
    delay(100);
    removeAttempts++;
  }
  if (removeAttempts >= 50)
  {
    enrollmentErrorMessage = "Finger not removed within timeout";
    displayMessage("Remove finger timeout!");
    Serial.println("Finger removal timeout");
    indicateError();
    delay(2000);
    turnOffLEDs();
    displayMessage("System Ready");
    return;
  }
  Serial.println("Finger removed successfully");

  // Step 3: Second scan
  Serial.println("Step 3: Waiting for second finger scan...");
  waitForFinger();
  p = finger.image2Tz(2);
  if (p != FINGERPRINT_OK)
  {
    enrollmentErrorMessage = "Failed to capture second fingerprint image";
    displayMessage("Error reading finger!");
    Serial.println("Error reading second image: " + String(p));
    indicateError();
    delay(2000);
    turnOffLEDs();
    displayMessage("System Ready");
    return;
  }
  Serial.println("Second image captured successfully");

  // Step 4: Create model
  displayMessage("Creating model...");
  Serial.println("Step 4: Creating fingerprint model...");
  p = finger.createModel();
  if (p != FINGERPRINT_OK)
  {
    enrollmentErrorMessage = "Failed to create fingerprint model";
    displayMessage("Error creating model!");
    Serial.println("Error creating model: " + String(p));
    indicateError();
    delay(2000);
    turnOffLEDs();
    displayMessage("System Ready");
    return;
  }
  Serial.println("Fingerprint model created successfully");

  // Step 5: Store model
  displayMessage("Storing model...");
  Serial.println("Step 5: Storing fingerprint model...");
  p = finger.storeModel(id);
  if (p == FINGERPRINT_OK)
  {
    displayMessage("Enrollment Success!");
    Serial.println("Enrollment Success! Fingerprint stored with ID: " + String(id));
    enrollmentSuccess = true; // Set success flag
    indicateSuccess();
    delay(2000);
    turnOffLEDs();
    displayMessage("System Ready");
  }
  else
  {
    enrollmentErrorMessage = "Failed to store fingerprint model";
    displayMessage("Error storing model!");
    Serial.println("Error storing model: " + String(p));
    indicateError();
    delay(2000);
    turnOffLEDs();
    displayMessage("System Ready");
  }
}

// Single scan function
bool scanFingerprintOnce()
{
  // Indicate waiting/scanning with blinking
  displayMessage("Place Finger...");

  int p = finger.getImage();
  if (p != FINGERPRINT_OK)
  {
    // still waiting; continue blinking
    indicateWaiting();
    return false; // no finger present
  }

  // Finger detected: stop waiting LED
  turnOffLEDs();

  // Processing finger image
  displayMessage("Processing...");

  p = finger.image2Tz();
  if (p != FINGERPRINT_OK)
  {
    Serial.println("image2Tz failed: " + String(p));
    displayMessage("Read Error");
    indicateError();
    delay(2000);
    turnOffLEDs();
    displayMessage("System Ready");
    return false;
  }

  // Searching for match
  displayMessage("Searching...");

  p = finger.fingerFastSearch();
  if (p != FINGERPRINT_OK)
  {
    // No match
    Serial.println("No match found: " + String(p));
    displayMessage("No Match");
    indicateError();
    delay(2000);
    turnOffLEDs();
    displayMessage("System Ready");
    return false;
  }

  // Match found
  uint16_t id = finger.fingerID;
  Serial.println("MATCH! ID: " + String(id));
  displayMessage("MATCH!\nID: " + String(id));
  indicateSuccess();
  delay(2000);
  turnOffLEDs();
  displayMessage("System Ready");
  return true;
}

// Web server handlers
void handleIdentify()
{
  displayMessage("Scanning...");
  bool ok = scanFingerprintOnce();
  if (ok)
    server.send(200, "application/json", "{\"success\":true}");
  else
    server.send(200, "application/json", "{\"success\":false}");
}

void handleStatus()
{
  String response = "{";
  response += "\"status\":\"ok\",";
  response += "\"fingerprint_sensor\":\"" + String(finger.verifyPassword() ? "connected" : "disconnected") + "\",";
  response += "\"wifi\":\"" + String(WiFi.status() == WL_CONNECTED ? "connected" : "disconnected") + "\",";
  response += "\"ip\":\"" + WiFi.localIP().toString() + "\"";
  response += "}";
  server.send(200, "application/json", response);
}

void handleDisplay()
{
  if (server.hasArg("message"))
  {
    String message = server.arg("message");
    displayMessage(message);
    server.send(200, "application/json", "{\"success\":true}");
  }
  else
  {
    server.send(400, "application/json", "{\"success\":false,\"error\":\"No message parameter\"}");
  }
}

void handleEnroll()
{
  if (!server.hasArg("id"))
  {
    server.send(400, "application/json", "{\"success\":false,\"error\":\"No ID parameter\"}");
    return;
  }

  uint8_t id = server.arg("id").toInt();

  // Perform enrollment
  enrollFingerprint(id);

  // Check enrollment success using the global flag
  if (enrollmentSuccess)
  {
    String response = "{";
    response += "\"success\":true,";
    response += "\"message\":\"Enrollment completed successfully\",";
    response += "\"template\":\"template_" + String(id) + "_" + String(random(1000000, 9999999)) + "\",";
    response += "\"hash\":\"hash_" + String(id) + "_" + String(random(1000000, 9999999)) + "\",";
    response += "\"version\":\"v1.0\"";
    response += "}";

    server.send(200, "application/json", response);
  }
  else
  {
    String errorResponse = "{\"success\":false,\"error\":\"Fingerprint enrollment failed\"";
    if (enrollmentErrorMessage != "")
    {
      errorResponse += ",\"details\":\"" + enrollmentErrorMessage + "\"";
    }
    errorResponse += "}";
    server.send(200, "application/json", errorResponse);
  }
}

void handleStartScan()
{
  continuousScanMode = true;
  displayMessage("Scan Mode ON\nPlace finger...");
  server.send(200, "application/json", "{\"success\":true,\"message\":\"Continuous scan mode started\"}");
  Serial.println("Continuous scan mode started");
}

void handleStopScan()
{
  continuousScanMode = false;
  turnOffLEDs();
  displayMessage("Scan Mode OFF");
  server.send(200, "application/json", "{\"success\":true,\"message\":\"Continuous scan mode stopped\"}");
  Serial.println("Continuous scan mode stopped");
}
