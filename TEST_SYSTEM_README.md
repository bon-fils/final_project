# Facial Recognition Attendance System - Test Setup

## Overview
This test system allows you to register students with 4 face images and test the facial recognition attendance marking functionality.

## Files Created

### Database
- `test_students` table - Stores test student data with 4 face image paths

### Frontend
- `test-student-registration.html` - Web form to register test students
- `test-attendance.html` - Camera interface for attendance testing

### Backend
- `test-student-registration.php` - API to register test students
- `test-face-match.php` - Face recognition and attendance marking API

## How to Use

### 1. Register Test Students
1. Open `test-student-registration.html` in your browser
2. Fill in:
   - Registration Number (unique)
   - First Name
   - Last Name
3. Upload exactly 4 face images
4. Click "Register Student"

### 2. Test Attendance System
1. Open `test-attendance.html` in your browser
2. Allow camera access
3. Click "Capture & Send"
4. System will:
   - Capture your face
   - Compare with registered students
   - Mark attendance if match found

## Database Structure

### test_students table
```sql
CREATE TABLE test_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reg_no VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    face_image_1 VARCHAR(255),
    face_image_2 VARCHAR(255),
    face_image_3 VARCHAR(255),
    face_image_4 VARCHAR(255),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Sample Data
- Demo student: DEMO001 (Demo Student) with 4 face images

## API Endpoints

### Register Student
- **URL**: `test-student-registration.php`
- **Method**: POST
- **Data**: JSON with reg_no, first_name, last_name, images[]

### Mark Attendance
- **URL**: `test-face-match.php`
- **Method**: POST
- **Data**: JSON with image (base64)

## Features Tested

✅ Student registration with multiple face images
✅ Face image storage and retrieval
✅ Camera capture and image processing
✅ Face recognition matching (simulation mode)
✅ Attendance record creation
✅ Duplicate attendance prevention
✅ JSON API responses
✅ Error handling

## Current Status

- **Face Recognition**: Simulation mode (always matches for testing)
- **Database**: Fully functional
- **Attendance Marking**: Working
- **Image Storage**: Local file system

## For Production

To enable real face recognition:
1. Install Python dependencies
2. Change `$useSimulation = false` in `test-face-match.php`
3. Run Python face recognition service

## Testing Checklist

- [ ] Register a test student
- [ ] Verify images are saved
- [ ] Test attendance capture
- [ ] Check attendance records in database
- [ ] Test duplicate prevention
- [ ] Verify JSON responses