# ESP32 Fingerprint System Setup Guide

## Hardware Requirements

### Components Needed:
- ESP32 Dev Board (ESP32-WROOM-32)
- Fingerprint Sensor (AS608 or GT-511C3)
- OLED Display (SSD1306, 128x64)
- LEDs (Red, Yellow, Green)
- Resistors (220Ω for LEDs)
- Breadboard and connecting wires

### Pin Connections:

#### ESP32 to Fingerprint Sensor:
- ESP32 GPIO 16 → Fingerprint Sensor RX
- ESP32 GPIO 17 → Fingerprint Sensor TX
- ESP32 3.3V → Fingerprint Sensor VCC
- ESP32 GND → Fingerprint Sensor GND

#### ESP32 to OLED Display:
- ESP32 GPIO 21 → OLED SDA
- ESP32 GPIO 22 → OLED SCL
- ESP32 3.3V → OLED VCC
- ESP32 GND → OLED GND

#### ESP32 to LEDs:
- ESP32 GPIO 2 → Red LED (with 220Ω resistor)
- ESP32 GPIO 4 → Yellow LED (with 220Ω resistor)
- ESP32 GPIO 5 → Green LED (with 220Ω resistor)
- ESP32 GND → LED cathodes

## Software Setup

### 1. Install Required Libraries

Open Arduino IDE and install these libraries:
- **WiFi** (built-in)
- **WebServer** (built-in)
- **ESPmDNS** (built-in)
- **Wire** (built-in)
- **Adafruit GFX Library**
- **Adafruit SSD1306**
- **SoftwareSerial** (built-in)
- **Adafruit Fingerprint Sensor Library**
- **ArduinoJson** (for JSON handling)

### 2. Configure WiFi Settings

Edit the ESP32 code (`esp32_fingerprint_enhanced.ino`) and update these settings:

```cpp
// WiFi Configuration
const char* ssid = "YOUR_WIFI_SSID";           // Your WiFi network name
const char* password = "YOUR_WIFI_PASSWORD";    // Your WiFi password
IPAddress local_IP(192, 168, 1, 100);         // Static IP for ESP32
IPAddress gateway(192, 168, 1, 1);            // Your router IP
IPAddress subnet(255, 255, 255, 0);          // Subnet mask
```

### 3. Upload Code to ESP32

1. Connect ESP32 to your computer via USB
2. Select the correct board: **ESP32 Dev Module**
3. Select the correct port
4. Upload the code

### 4. Configure PHP System

Add these settings to your `.env` file or `config.php`:

```php
// ESP32 Configuration
ESP32_IP=192.168.1.100
ESP32_PORT=80
ESP32_TIMEOUT=10
FINGERPRINT_ENABLED=true
```

## Testing the Setup

### 1. Check ESP32 Connection

After uploading the code, open Serial Monitor (115200 baud) and look for:
```
WiFi Connected!
IP Address: 192.168.1.100
ESP32 Fingerprint System Ready!
```

### 2. Test Web Interface

Open a web browser and go to: `http://192.168.1.100`

You should see the ESP32 status page.

### 3. Test API Endpoints

Test these endpoints using a tool like Postman or curl:

```bash
# Check status
curl http://192.168.1.100/status

# Test endpoint
curl http://192.168.1.100/test

# Display message
curl "http://192.168.1.100/display?message=Hello%20World"
```

### 4. Test Fingerprint Enrollment

Use the student registration form to test fingerprint enrollment.

## Troubleshooting

### Common Issues:

1. **WiFi Connection Failed**
   - Check WiFi credentials
   - Ensure ESP32 and computer are on same network
   - Check if router allows new devices

2. **Fingerprint Sensor Not Found**
   - Check wiring connections
   - Verify sensor is powered (3.3V)
   - Check if sensor is compatible

3. **OLED Display Not Working**
   - Check I2C connections (SDA/SCL)
   - Verify display address (usually 0x3C)
   - Check power connections

4. **LEDs Not Working**
   - Check GPIO pin assignments
   - Verify resistor values (220Ω)
   - Check ground connections

### Debug Steps:

1. **Check Serial Monitor** for error messages
2. **Test individual components** (OLED, LEDs, sensor)
3. **Verify network connectivity** (ping ESP32 IP)
4. **Check PHP error logs** for API issues

## Security Considerations

1. **Change default API key** in production
2. **Use HTTPS** for production deployment
3. **Implement rate limiting** to prevent abuse
4. **Regular firmware updates** for security patches

## Production Deployment

1. **Set static IP** for ESP32
2. **Configure firewall** rules
3. **Enable HTTPS** on PHP server
4. **Set up monitoring** and logging
5. **Create backup** of fingerprint data

## Support

If you encounter issues:
1. Check the Serial Monitor output
2. Verify all connections
3. Test individual components
4. Check network connectivity
5. Review PHP error logs
