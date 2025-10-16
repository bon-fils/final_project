# Main Tables and Columns

## Table 1: Users

| Field | Data Type | Size | Constraints | Description |
|-------|-----------|------|-------------|-------------|
| id | INT | 11 | PK, AI | Unique user ID |
| username | VARCHAR | 50 | NOT NULL | User's login username |
| email | VARCHAR | 100 | NOT NULL, UNIQUE | User's email address |
| password | VARCHAR | 255 | NOT NULL | Hashed password |
| role | ENUM | - | NOT NULL | User role (admin, lecturer, student, hod, tech) |
| created_at | TIMESTAMP | - | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Account creation timestamp |
| status | ENUM | - | NOT NULL, DEFAULT 'active' | Account status (active, inactive, suspended) |
| updated_at | TIMESTAMP | - | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last update timestamp |
| last_login | TIMESTAMP | - | NULL | Last login timestamp |
| first_name | VARCHAR | 100 | NULL | User's first name |
| last_name | VARCHAR | 100 | NULL | User's last name |
| phone | VARCHAR | 20 | NULL | User's phone number |
| sex | ENUM | - | NULL | User's gender (Male, Female, Other) |
| photo | VARCHAR | 255 | NULL | Path to user's profile photo |
| dob | DATE | - | NULL | User's date of birth |

## Table 2: Students

| Field | Data Type | Size | Constraints | Description |
|-------|-----------|------|-------------|-------------|
| id | INT | 11 | PK, AI | Unique student ID |
| user_id | INT | 11 | FK, NOT NULL | References Users(id) |
| option_id | INT | 11 | FK, NOT NULL | References Options(id) |
| year_level | VARCHAR | 20 | NOT NULL | Student's academic year level |
| student_photos | LONGTEXT | - | NULL, CHECK JSON_VALID | JSON data for biometric photos |
| profile_photo | VARCHAR | 255 | NULL | Path to profile photo |
| parent_first_name | VARCHAR | 100 | NULL | Parent's first name |
| parent_last_name | VARCHAR | 100 | NULL | Parent's last name |
| parent_contact | VARCHAR | 20 | NULL | Parent's contact number |
| reg_no | VARCHAR | 50 | NOT NULL | Student registration number |
| student_id_number | VARCHAR | 25 | NULL | Student ID number |
| department_id | INT | 11 | FK, NULL | References Departments(id) |
| fingerprint | VARCHAR | 255 | NULL | Fingerprint data |
| status | ENUM | - | DEFAULT 'active' | Student status (active, inactive, graduated) |
| fingerprint_path | VARCHAR | 255 | NULL | Path to fingerprint file |
| fingerprint_quality | INT | 11 | DEFAULT 0 | Fingerprint quality score |

## Table 3: Lecturers

| Field | Data Type | Size | Constraints | Description |
|-------|-----------|------|-------------|-------------|
| id | INT | 10 | PK, AI, UNSIGNED | Unique lecturer ID |
| gender | ENUM | - | NOT NULL | Lecturer's gender (Male, Female, Other) |
| dob | DATE | - | NOT NULL | Date of birth |
| id_number | VARCHAR | 50 | NOT NULL, UNIQUE | National ID number |
| department_id | INT | 11 | FK, NOT NULL | References Departments(id) |
| education_level | VARCHAR | 100 | NULL | Education qualification level |
| created_at | TIMESTAMP | - | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp |
| updated_at | TIMESTAMP | - | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last update timestamp |
| user_id | INT | 11 | FK, NULL | References Users(id) |

## Table 4: Attendance Sessions

| Field | Data Type | Size | Constraints | Description |
|-------|-----------|------|-------------|-------------|
| id | INT | 11 | PK, AI | Unique session ID |
| lecturer_id | INT | 11 | FK, NOT NULL | References Users(id) - lecturer |
| course_id | INT | 11 | FK, NOT NULL | References Courses(id) |
| option_id | INT | 11 | FK, NOT NULL | References Options(id) |
| session_date | DATE | - | NOT NULL | Date of attendance session |
| start_time | TIME | - | NOT NULL | Session start time |
| end_time | TIME | - | NULL | Session end time |
| biometric_method | ENUM | - | NOT NULL, DEFAULT 'face_recognition' | Biometric method (face_recognition, fingerprint) |

## Table 5: Attendance Records

| Field | Data Type | Size | Constraints | Description |
|-------|-----------|------|-------------|-------------|
| id | INT | 11 | PK, AI | Unique attendance record ID |
| session_id | INT | 11 | FK, NOT NULL | References Attendance_Sessions(id) |
| student_id | INT | 11 | FK, NOT NULL | References Students(id) |
| status | ENUM | - | NOT NULL | Attendance status (present, absent) |
| recorded_at | TIMESTAMP | - | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Timestamp when attendance was recorded |