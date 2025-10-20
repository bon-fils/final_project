# Face Recognition Service for RP Attendance System

## Overview

The Face Recognition Service is a Python-based microservice that provides advanced face detection, recognition, and attendance marking capabilities for the RP Attendance System. It uses OpenCV and the face_recognition library to process images captured from webcams and match them against enrolled student faces.

## Features

- **Real-time Face Detection**: Detects faces in images with configurable minimum size limits
- **Face Recognition**: Compares detected faces against enrolled students with confidence scoring
- **Database Integration**: Stores and retrieves face encodings from MySQL database
- **Redis Caching**: Caches face encodings for improved performance
- **RESTful API**: Provides HTTP endpoints for face recognition operations
- **Asynchronous Processing**: Uses thread pools for concurrent image processing
- **Comprehensive Logging**: Detailed logging for debugging and monitoring
- **Health Monitoring**: Built-in health checks and service statistics

## Architecture

### Components

1. **FaceRecognitionService Class**: Core service handling face recognition logic
2. **Flask Web Application**: REST API endpoints
3. **Database Layer**: MySQL integration for student data and attendance records
4. **Cache Layer**: Redis for face encoding caching
5. **Image Processing**: OpenCV and face_recognition for computer vision tasks

### Data Flow

```
Webcam Image → Base64 Decode → Face Detection → Face Encoding → Face Matching → Attendance Marking
```

## Installation

### Prerequisites

- Python 3.8+
- MySQL 5.7+
- Redis 6.0+
- Linux/Windows/macOS

### Dependencies

Install required packages:

```bash
pip install -r requirements.txt
```

### System Dependencies

For face_recognition library:

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install -y cmake libsm6 libxext6 libxrender-dev libgomp1 libglib2.0-0
```

**macOS:**
```bash
brew install cmake
```

**Windows:**
No additional dependencies required.

## Configuration

### Environment Variables

Create a `.env` file or set environment variables:

```bash
# Database Configuration
DB_HOST=localhost
DB_NAME=rp_attendance_system
DB_USER=root
DB_PASS=your_password

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_DB=0

# Face Recognition Settings
FACE_TOLERANCE=0.6
MIN_FACE_SIZE=50
MAX_FACES_PER_IMAGE=1
CONFIDENCE_THRESHOLD=0.7

# Service Configuration
SERVICE_HOST=localhost
SERVICE_PORT=5000
DEBUG=false
MAX_WORKERS=4

# Image Settings
MAX_IMAGE_SIZE=1048576
```

## Usage

### Starting the Service

```bash
python face_recognition_service.py
```

The service will start on the configured host and port (default: localhost:5000).

### API Endpoints

#### Health Check
```http
GET /health
```

Returns service health status and statistics.

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2025-01-20T10:30:00",
  "stats": {
    "known_faces_count": 150,
    "service_uptime": 3600.5,
    "config": {
      "face_tolerance": 0.6,
      "min_face_size": 50,
      "confidence_threshold": 0.7,
      "max_faces_per_image": 1
    }
  }
}
```

#### Face Recognition
```http
POST /recognize
Content-Type: application/json

{
  "image": "base64_encoded_image_data",
  "session_data": {
    "session_id": 123,
    "course_id": 456,
    "department_id": 789
  }
}
```

**Response:**
```json
{
  "status": "success",
  "faces_detected": 1,
  "faces_recognized": 1,
  "results": [
    {
      "student_id": "STU001",
      "student_name": "John Doe",
      "confidence": 92.5,
      "distance": 0.35,
      "metadata": {
        "id": 1,
        "department_id": 1,
        "year_level": 1
      }
    }
  ],
  "processing_time": 1.234
}
```

#### Reload Face Data
```http
POST /reload-faces
```

Reloads face encodings from the database.

#### Service Statistics
```http
GET /stats
```

Returns detailed service statistics.

## Integration with PHP Frontend

### JavaScript Integration

The service integrates with the existing `attendance-session-demo.js` file. Update the face recognition function to call the Python service:

```javascript
// Enhanced face recognition with Python service
async function recognizeFaceWithService(imageData, sessionData) {
    try {
        const response = await fetch('http://localhost:5000/recognize', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                image: imageData,
                session_data: sessionData
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            // Process successful recognition
            handleRecognitionSuccess(result.results);
        } else {
            // Handle recognition failure
            handleRecognitionError(result.message);
        }

    } catch (error) {
        console.error('Face recognition service error:', error);
        // Fallback to demo mode
        fallbackToDemoRecognition();
    }
}
```

### Database Schema Requirements

Ensure the following database tables exist:

#### students table (with face recognition fields)
```sql
ALTER TABLE students
ADD COLUMN face_encoding JSON NULL,
ADD COLUMN face_image_path VARCHAR(255) NULL,
ADD COLUMN face_enrolled_at TIMESTAMP NULL,
ADD COLUMN face_enrollment_confidence DECIMAL(5,2) NULL;
```

#### attendance_records table
```sql
CREATE TABLE attendance_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) NOT NULL,
    session_id INT NULL,
    biometric_method ENUM('face_recognition', 'fingerprint') NOT NULL,
    confidence_score DECIMAL(5,2) NULL,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(id)
);
```

## Performance Optimization

### Caching Strategy

- **Face Encodings**: Cached in Redis for 1 hour
- **Database Queries**: Prepared statements with connection pooling
- **Image Processing**: Asynchronous processing with thread pools

### Performance Metrics

- **Face Detection**: < 100ms per image
- **Face Recognition**: < 500ms per image (150 known faces)
- **API Response Time**: < 2 seconds end-to-end
- **Memory Usage**: < 500MB for 1000 face encodings

## Monitoring and Logging

### Log Files

- **Service Logs**: `logs/face_recognition_service.log`
- **Error Logs**: Separate error log file
- **Performance Logs**: Request timing and throughput

### Health Checks

The service provides health check endpoints for monitoring:

- `/health`: Overall service health
- `/stats`: Detailed performance statistics

### Monitoring Integration

Integrate with monitoring systems:

```python
# Prometheus metrics example
from prometheus_client import Counter, Histogram, Gauge

face_recognition_requests = Counter('face_recognition_requests_total', 'Total face recognition requests')
face_recognition_duration = Histogram('face_recognition_duration_seconds', 'Face recognition duration')
known_faces_gauge = Gauge('face_recognition_known_faces', 'Number of known faces')
```

## Security Considerations

### Input Validation

- Image size limits (1MB max)
- Supported formats: JPEG, PNG, JPG
- Base64 validation
- Face count limits

### Access Control

- API endpoints should be protected
- Rate limiting implementation
- Input sanitization

### Data Protection

- Face encodings stored securely
- No raw images stored in service
- Secure communication with database

## Troubleshooting

### Common Issues

1. **"No faces detected"**
   - Check image quality and lighting
   - Ensure face is clearly visible
   - Verify minimum face size settings

2. **"Recognition failed"**
   - Check if student is enrolled with face data
   - Verify face encoding quality
   - Adjust confidence threshold

3. **"Service unavailable"**
   - Check if Python service is running
   - Verify network connectivity
   - Check service logs

### Debug Mode

Enable debug logging:

```bash
export DEBUG=true
python face_recognition_service.py
```

### Performance Tuning

Adjust configuration for performance:

```python
# For better accuracy (slower)
FACE_TOLERANCE = 0.5
CONFIDENCE_THRESHOLD = 0.8

# For better speed (less accurate)
FACE_TOLERANCE = 0.7
CONFIDENCE_THRESHOLD = 0.6
```

## Development

### Running Tests

```bash
pytest tests/
```

### Code Formatting

```bash
black face_recognition_service.py
flake8 face_recognition_service.py
```

### Adding New Features

1. Extend the `FaceRecognitionService` class
2. Add new API endpoints in the Flask app
3. Update configuration options
4. Add comprehensive logging
5. Update documentation

## Deployment

### Production Deployment

1. **Environment Setup**:
   ```bash
   pip install -r requirements.txt
   ```

2. **Configuration**:
   - Set production environment variables
   - Configure database and Redis connections
   - Adjust performance settings

3. **Service Management**:
   ```bash
   # Using systemd
   sudo cp face-recognition.service /etc/systemd/system/
   sudo systemctl enable face-recognition
   sudo systemctl start face-recognition
   ```

4. **Monitoring**:
   - Setup log rotation
   - Configure health checks
   - Monitor performance metrics

### Docker Deployment

```dockerfile
FROM python:3.9-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install -r requirements.txt

COPY face_recognition_service.py .

EXPOSE 5000

CMD ["python", "face_recognition_service.py"]
```

## License

This face recognition service is part of the RP Attendance System and follows the same licensing terms.

## Support

For issues and questions:

1. Check the service logs
2. Review the troubleshooting section
3. Contact the development team
4. Check GitHub issues for similar problems