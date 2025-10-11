# Face Recognition Service for RP Attendance System

This Python-based face recognition service provides advanced facial recognition capabilities for the RP Attendance System using the `face_recognition` library.

## Features

- **Advanced Face Detection**: Uses dlib's CNN-based face detector for accurate face detection
- **Face Recognition**: Compares captured faces against enrolled student photos
- **Confidence Scoring**: Provides confidence levels (high/medium/low) for recognition results
- **Session Filtering**: Filters recognition to students in the current attendance session
- **Caching**: Caches face encodings for improved performance
- **REST API**: Provides HTTP endpoints for integration with PHP backend

## Requirements

### System Dependencies (Ubuntu/Debian)
```bash
sudo apt-get update
sudo apt-get install -y build-essential cmake pkg-config
sudo apt-get install -y libx11-dev libatlas-base-dev libgtk-3-dev libboost-python-dev
sudo apt-get install -y python3-dev python3-pip
```

### Python Dependencies
All Python dependencies are listed in `requirements.txt`:
- Flask==2.3.3
- Flask-CORS==4.0.0
- face_recognition==1.3.0
- Pillow==10.0.1
- numpy==1.24.3
- mysql-connector-python==8.1.0
- python-dotenv==1.0.0

## Installation

1. **Install system dependencies** (see above)

2. **Create virtual environment**:
```bash
python3 -m venv venv
source venv/bin/activate
```

3. **Install Python dependencies**:
```bash
pip install -r requirements.txt
```

4. **Configure environment variables**:
Create a `.env` file or set environment variables:
```bash
DB_HOST=localhost
DB_NAME=rp_attendance_system
DB_USER=root
DB_PASS=your_password
FACE_RECOGNITION_PORT=5000
FLASK_DEBUG=false
```

## Usage

### Starting the Service

Use the provided startup script:
```bash
chmod +x start_face_recognition.sh
./start_face_recognition.sh
```

Or start manually:
```bash
source venv/bin/activate
python3 face_recognition_service.py
```

The service will start on `http://localhost:5000` by default.

### API Endpoints

#### Health Check
```
GET /health
```
Returns service health status and cached encodings count.

#### Face Recognition
```
POST /recognize
```
Recognizes faces in captured images.

**Request Body:**
```json
{
  "image_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ...",
  "session_id": 123,
  "department_id": 1,
  "option_id": 2
}
```

**Response:**
```json
{
  "status": "success",
  "recognized": true,
  "student_id": 456,
  "student_name": "John Doe",
  "student_reg": "22RP06557",
  "confidence": 87.5,
  "confidence_level": "high",
  "auto_mark": true,
  "top_matches": [...],
  "faces_detected": 1,
  "timestamp": "2025-01-06T14:30:00"
}
```

#### Reload Cache
```
POST /reload_cache
```
Forces reload of face encodings cache.

#### Get Statistics
```
GET /stats
```
Returns service statistics and configuration.

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | localhost | Database host |
| `DB_NAME` | rp_attendance_system | Database name |
| `DB_USER` | root | Database username |
| `DB_PASS` | "" | Database password |
| `FACE_RECOGNITION_PORT` | 5000 | Service port |
| `FLASK_DEBUG` | false | Enable debug mode |
| `FACE_RECOGNITION_URL` | http://localhost:5000 | Service URL (for PHP config) |

### Recognition Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `FACE_RECOGNITION_TOLERANCE` | 0.4 | Lower = stricter matching |
| `MIN_FACE_SIZE` | 50 | Minimum face size in pixels |
| `CONFIDENCE_THRESHOLD_HIGH` | 0.8 | High confidence threshold |
| `CONFIDENCE_THRESHOLD_MEDIUM` | 0.6 | Medium confidence threshold |

## Integration with PHP

The PHP backend automatically calls this service when face recognition is requested. Configure the service URL in your PHP config:

```php
// In config.php or environment
putenv('FACE_RECOGNITION_URL=http://localhost:5000');
```

## Performance Optimization

### Caching
- Face encodings are cached for 5 minutes
- Automatic cache invalidation on service restart
- Manual cache reload via `/reload_cache` endpoint

### Database Optimization
- Efficient queries for student photo retrieval
- Session-based filtering reduces comparison scope
- Connection pooling ready

## Troubleshooting

### Common Issues

1. **"face_recognition library not available"**
   - Install system dependencies
   - Ensure CMake and build tools are available
   - Try: `pip install --no-cache-dir face_recognition`

2. **"Database connection failed"**
   - Check database credentials in environment variables
   - Ensure MySQL server is running
   - Verify database and tables exist

3. **"No faces detected"**
   - Ensure good lighting and clear face visibility
   - Check image quality and resolution
   - Verify webcam settings

4. **Low recognition accuracy**
   - Ensure student photos are clear and well-lit
   - Update face encodings cache
   - Adjust confidence thresholds if needed

### Logs

Service logs are written to:
- `face_recognition.log` (main log file)
- Console output (when running in foreground)

### Health Monitoring

Check service health:
```bash
curl http://localhost:5000/health
```

Get service statistics:
```bash
curl http://localhost:5000/stats
```

## Security Considerations

- Service runs on localhost by default
- No authentication required (intended for internal use)
- Input validation on all endpoints
- Secure file handling for temporary images
- Database credentials via environment variables

## Development

### Running in Debug Mode
```bash
export FLASK_DEBUG=true
python3 face_recognition_service.py
```

### Testing the API
```bash
# Health check
curl http://localhost:5000/health

# Test recognition (replace with actual image data)
curl -X POST http://localhost:5000/recognize \
  -d "image_data=data:image/jpeg;base64,YOUR_BASE64_DATA" \
  -d "session_id=1"
```

## License

This service is part of the RP Attendance System and follows the same licensing terms.

## Support

For technical support:
- Check service logs for error details
- Verify database connectivity
- Ensure all dependencies are properly installed
- Test with sample images to verify face detection