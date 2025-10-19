# RP Attendance System - Entity Relationship Diagram (ERD)

This document contains the Entity-Relationship Diagram for the RP Attendance System project, generated from the database schema in `rp_attendance_system (11).sql`.

## Text-Based ERD Diagram

```
+----------------+       +-----------------+
|     users      |       |   departments   |
| (admin, lect,  |<------|   (hod_id)      |
|  student, hod, |       |                 |
|  tech staff)   |       +-----------------+
+----------------+               |
        |                        |
        |                        |
        v                        v
+----------------+       +-----------------+
|   lecturers    |       |     options     |
| (user_id)      |       | (department_id) |
+----------------+       +-----------------+
        |                        |
        |                        |
        v                        v
+----------------+       +-----------------+
|    students    |<------|     courses     |
| (user_id,      |       | (department_id, |
|  option_id)    |       |  option_id,     |
+----------------+       |  lecturer_id)   |
        |                +-----------------+
        |                        |
        |                        |
        v                        v
+----------------+       +-----------------+
| attendance_    |<------| attendance_     |
|   records      |       |   sessions      |
| (student_id,   |       | (lecturer_id,   |
|  session_id)   |       |  course_id,     |
+----------------+       |  option_id)     |
                         +-----------------+
```

### Detailed Relationships:

**Core User Management:**
```
users -----> departments (hod_id: one-to-many)
users -----> lecturers (user_id: one-to-one)
users -----> students (user_id: one-to-one)
users -----> leave_requests (reviewed_by: one-to-many)
users -----> activity_logs (user_id: one-to-many)
users -----> audit_trail (user_id: one-to-many)
users -----> remember_tokens (user_id: one-to-many)
users -----> system_logs (user_id: one-to-many)
users -----> tech_logs (tech_id: one-to-many)
users -----> student_photos (user_id: one-to-many)
```

**Academic Structure:**
```
departments -----> options (department_id: one-to-many)
departments -----> courses (department_id: one-to-many)
departments -----> lecturers (department_id: one-to-many)
options -----> courses (option_id: one-to-many)
options -----> students (option_id: one-to-many)
courses -----> attendance_sessions (course_id: one-to-many)
```

**Attendance System:**
```
lecturers -----> attendance_sessions (lecturer_id: one-to-many)
attendance_sessions -----> attendance_records (session_id: one-to-many)
students -----> attendance_records (student_id: one-to-many)
```

**Student Information:**
```
students -----> guardians (student_id: one-to-many)
students -----> leave_requests (student_id: one-to-many)
students -----> student_locations (student_id: one-to-many)
students -----> student_images (student_id: one-to-many)
students -----> student_photos (student_id: one-to-many)
students -----> face_recognition_logs (student_id: one-to-many)
locations -----> student_locations (location_id: one-to-many)
```

**Logging and Security:**
```
users -----> login_attempts (tracked by email/username)
users -----> remember_tokens (user_id: one-to-many)
users -----> system_logs (user_id: one-to-many)
users -----> activity_logs (user_id: one-to-many)
users -----> audit_trail (user_id: one-to-many)
```

## Overview

This ERD shows the main entities in your attendance system and their relationships. The system centers around users (admin, lecturers, students, HODs, tech staff) and includes biometric attendance tracking with both face recognition and fingerprint methods.

### Key Relationships:
- Users can be lecturers, students, or department heads
- Departments contain options (specializations) and courses
- Students are enrolled in options and attend courses through attendance sessions
- Biometric data is stored for students with face images and fingerprints
- The system tracks attendance records, leave requests, and various logs for auditing

### Legend:
- `----->` indicates one-to-many relationship
- `(foreign_key)` shows the foreign key field
- Entities are grouped by functional areas for clarity