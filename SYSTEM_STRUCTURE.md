# Rwanda Polytechnic Attendance Management System - Structure Overview

## System Architecture

This is a comprehensive attendance management system for Rwanda Polytechnic with biometric authentication (face recognition and fingerprint), multi-role user management, and advanced reporting capabilities.

## User Roles & Dashboards

### 1. Admin Dashboard (`admin-dashboard.php`)
**Main Dashboard**: `admin-dashboard.php`
**Related Pages**:
- `admin/index.php` - Admin dashboard (alternative)
- `admin/reports.php` - Admin reports
- `admin-view-users.php` - View all users
- `admin-register-lecturer.php` - Register lecturers
- `manage-users.php` - User management
- `manage-departments.php` - Department management
- `assign-hod.php` / `assign-hod-refined.php` - Assign HODs
- `register-student.php` - Student registration
- `attendance-reports.php` - Attendance reports
- `admin-reports.php` - Admin-specific reports

### 2. Lecturer Dashboard (`lecturer-dashboard.php`)
**Main Dashboard**: `lecturer-dashboard.php`
**Related Pages**:
- `lecturer-my-courses.php` - My courses
- `my-courses.php` - Course management
- `attendance-session.php` / `attendance-session-refined.php` - Attendance sessions
- `attendance-records.php` - Attendance records

### 3. HOD (Head of Department) Dashboard (`hod-dashboard.php`)
**Main Dashboard**: `hod-dashboard.php`
**Related Pages**:
- `hod-department-reports.php` - Department reports
- `hod-manage-lecturers.php` - Manage lecturers
- `hod-leave-management.php` - Leave management

### 4. Student Dashboard (`students-dashboard.php`)
**Main Dashboard**: `students-dashboard.php`
**Related Pages**:
- `leave-requests.php` - Leave requests
- `leave-status.php` - Leave status
- `request-leave.php` - Request leave

### 5. Tech Dashboard (`tech-dashboard.php`)
**Main Dashboard**: `tech-dashboard.php`
**Related Pages**:
- `system-logs.php` - System logs
- `test_login.php` - Login testing
- `debug_session.php` - Session debugging

## Core Functionality Pages

### Authentication & Security
- `login.php` - Main login page
- `logout.php` - Logout functionality
- `forgot-password.php` - Password recovery
- `reset-password.php` - Password reset
- `session_check.php` - Session validation

### Attendance Management
- `attendance-session.php` - Start/manage attendance sessions
- `attendance-session-refined.php` - Enhanced attendance sessions
- `attendance-records.php` - View attendance records
- `attendance-reports.php` - Generate reports
- `attendance-reports-refactored.php` - Refactored reports
- `attendance-reports-noauth.php` - Reports without auth
- `attendance-reports-bypass.php` - Bypass reports

### User Management
- `manage-users.php` - User CRUD operations
- `manage-users-template.php` - User management template
- `edit-lecturer.php` - Edit lecturer details
- `delete-lecturer.php` - Delete lecturer

### Department & Course Management
- `manage-departments.php` - Department management
- `assign-hod.php` - Assign department heads
- `assign-hod-refined.php` - Enhanced HOD assignment
- `create_courses.php` - Course creation


### Leave Management
- `leave-requests.php` - View leave requests
- `leave-status.php` - Check leave status
- `request-leave.php` - Submit leave requests
- `submit-leave.php` - Process leave submissions
- `hod-leave-management.php` - HOD leave management

### Biometric & Face Recognition
- `face_recognition_service.py` - Face recognition service
- `face_match.py` - Face matching algorithms
- `match.py` - Matching utilities
- `simple_face_match.py` - Simple face matching
- `check_face_recognition_setup.py` - Setup verification
- `run_face_tests.sh` / `run_face_tests.bat` - Testing scripts

### Fingerprint Integration
- `fingerprint-setup.php` - Fingerprint setup
- `ESP32_Fingerprint_Improvements.md` - ESP32 integration docs
- `ESP32_INTEGRATION_README.md` - Integration guide

### API Endpoints (`api/` directory)
- `api.php` - Main API handler

### Database & Setup
- `config.php` - Main configuration






### External Integrations
- `face_attendance_system/` - Python face recognition system
- `fingerprint_sever/` - Fingerprint server


## Key Features

1. **Multi-Role Authentication**: Admin, Lecturer, HOD, Student, Tech roles
2. **Biometric Attendance**: Face recognition and fingerprint scanning
3. **Real-time Attendance Tracking**: GPS location and timestamp verification
4. **Comprehensive Reporting**: Department-wise, course-wise, and individual reports
5. **Leave Management**: Request, approve, and track leave
6. **Department Management**: HOD assignments and department operations
7. **Course Management**: Assign courses to lecturers
8. **Mobile Responsive**: Works on all devices
9. **Security**: CSRF protection, input validation, secure sessions

## Technology Stack

- **Backend**: PHP 8.x, MySQL/MariaDB
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript/jQuery
- **Biometric**: Python (OpenCV, face_recognition), ESP32 for fingerprint
- **APIs**: RESTful JSON APIs
- **Security**: Password hashing, CSRF tokens, input sanitization
- **Caching**: File-based caching system
- **Logging**: Structured logging with different levels

## Database Structure

- `users` - User accounts with roles
- `students` - Student-specific data with biometric info
- `lecturers` - Lecturer information
- `departments` - Academic departments
- `options` - Programs/specializations
- `courses` - Course information
- `attendance_sessions` - Attendance session management
- `attendance_records` - Individual attendance entries
- `leave_requests` - Leave management
-
This structure provides a complete overview of the Rwanda Polytechnic Attendance Management System with all dashboards, pages, and functionalities organized by user roles and feature categories.