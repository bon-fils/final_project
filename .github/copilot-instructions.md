# Copilot Instructions for RP Attendance System

## Project Overview

This is a PHP-based web application for managing attendance, leave requests, and reporting for an academic institution. The system uses a MySQL database and is structured as a set of PHP scripts, each handling a specific feature or dashboard.

## Architecture & Data Flow

- **Single-directory, multi-script structure:** Each major feature (admin, HOD, lecturer, student, tech) has a dedicated PHP file (e.g., `admin-dashboard.php`, `hod-dashboard.php`).
- **Database access:** All scripts use a shared configuration in `config.php` for PDO-based MySQL access. Credentials and connection logic are centralized here.
- **No framework:** The project does not use a PHP framework (e.g., Laravel, Symfony). All routing and logic are handled in individual PHP files.
- **Session management and authentication:** Likely handled in scripts such as `login.php`, `forgot-password.php`, and `reset-password.php`.
- **Reporting and logs:** Files like `admin-reports.php`, `attendance-reports.php`, and `system-logs.php` generate and display reports, likely using direct SQL queries.

## Developer Workflows

- **Local development:** Designed for XAMPP/MAMP environments. Database credentials default to `root`/no password.
- **No build step:** PHP files are interpreted directly. No compilation or asset pipeline.
- **Debugging:** Use browser-based debugging and XAMPP/MAMP logs. Errors are surfaced via exceptions (see `config.php`).
- **Database migrations:** Schema changes must be made manually in MySQL. No migration scripts detected.

## Project-Specific Conventions

- **File naming:** Each PHP file is named for its function (e.g., `register-student.php`, `leave-requests.php`).
- **Direct SQL usage:** Queries are written directly in PHP using PDO. No ORM or query builder.
- **Minimal use of includes:** Most logic is contained within single files, except for shared config.
- **Error handling:** Database errors are caught and displayed using exceptions in `config.php`.

## Integration Points

- **MySQL database:** All data is stored in `rp_attendance_system` (see `config.php`).
- **Biometric and webcam setup:** Files like `fingerprint-setup.php` and `webcam-setup.php` suggest hardware integration, likely via browser APIs or local drivers.
- **No external APIs detected:** All logic appears to be local to the server and browser.

## Key Files

- `config.php`: Centralized database configuration and connection logic.
- `login.php`, `forgot-password.php`, `reset-password.php`: Authentication and password management.
- `admin-dashboard.php`, `hod-dashboard.php`, `lecturer-dashboard.php`, `students-dashboard.php`, `tech-dashboard.php`: Main dashboards for each user role.
- `attendance-records.php`, `attendance-reports.php`, `system-logs.php`: Core reporting and logging features.

## Example Pattern

```php
// Database connection (from config.php)
$pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

---

**If any section is unclear or missing, please specify so it can be improved.**
