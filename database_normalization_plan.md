# Database Normalization Plan for RP Attendance System

## Current Issues Identified

### Redundant Columns
1. **courses table**: `name` and `course_name` columns contain duplicate data
2. **students table**: `email` column is redundant (exists in users table via user_id FK)
3. **students table**: Location fields (`cell`, `sector`, `district`, `province`) should be normalized
4. **students table**: Parent information should be in separate table

### Missing Foreign Keys
1. **lecturers table**: No direct FK to users table (only linked via email)
2. **students table**: `department_id` creates redundancy (can be derived from option)

## Normalization Changes (3NF)

### 1. Remove Redundant Columns

#### courses table
- Remove `course_name` column (duplicate of `name`)
- Keep `name` column

#### students table
- Remove `email` column (redundant with users.email via user_id)
- Remove `first_name`, `last_name` (move to users table)
- Remove `telephone` (move to users table)
- Remove `sex` (move to users table)
- Remove `photo` (move to users table)
- Remove `dob` (move to users table)

#### lecturers table
- Remove `first_name`, `last_name` (move to users table)
- Remove `phone` (move to users table)
- Remove `email` (redundant with users.email)
- Add `user_id` FK to users table

### 2. Add New Tables for Normalization

#### users table (enhanced)
Add these columns:
- `first_name` VARCHAR(100)
- `last_name` VARCHAR(100)
- `phone` VARCHAR(20)
- `gender` ENUM('Male','Female','Other')
- `photo` VARCHAR(255)
- `date_of_birth` DATE

#### locations table
```sql
CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    province VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    sector VARCHAR(100) NOT NULL,
    cell VARCHAR(100) NOT NULL,
    UNIQUE KEY unique_location (province, district, sector, cell)
);
```

#### student_locations table
```sql
CREATE TABLE student_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    location_id INT NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
);
```

#### guardians table
```sql
CREATE TABLE guardians (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    contact VARCHAR(20),
    relationship VARCHAR(50),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
```

### 3. Update Existing Tables

#### lecturers table
```sql
ALTER TABLE lecturers
ADD COLUMN user_id INT NOT NULL AFTER id,
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
DROP COLUMN first_name,
DROP COLUMN last_name,
DROP COLUMN phone,
DROP COLUMN email;
```

#### students table
```sql
ALTER TABLE students
DROP COLUMN email,
DROP COLUMN first_name,
DROP COLUMN last_name,
DROP COLUMN telephone,
DROP COLUMN sex,
DROP COLUMN photo,
DROP COLUMN dob,
DROP COLUMN cell,
DROP COLUMN sector,
DROP COLUMN district,
DROP COLUMN province,
DROP COLUMN parent_first_name,
DROP COLUMN parent_last_name,
DROP COLUMN parent_contact;
```

### 4. Migration Steps

1. **Backup current data**
2. **Add new columns to users table**
3. **Migrate data from students/lecturers to users table**
4. **Create new tables (locations, student_locations, guardians)**
5. **Migrate location and guardian data**
6. **Update lecturers table with user_id references**
7. **Remove redundant columns**
8. **Update foreign key constraints**
9. **Update application code**

### 5. Benefits of Normalization

- **Eliminates data redundancy**
- **Improves data integrity**
- **Reduces storage space**
- **Prevents update anomalies**
- **Better maintainability**
- **Clearer relationships between entities**

### 6. Code Changes Required

#### PHP Files to Update:
- `manage-users.php` - Update queries to use normalized schema
- `login.php` - Update authentication queries
- `register-student.php` - Update registration logic
- All API endpoints that access student/lecturer data
- Dashboard files that display user information

#### Key Query Changes:
- Student queries: Join with users table for personal info
- Lecturer queries: Join with users table for personal info
- Location queries: Join through student_locations → locations
- Guardian queries: Join with guardians table

This normalization will bring the database to 3NF, eliminating redundancy while maintaining all necessary relationships and data integrity.