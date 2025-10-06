# Database Normalization Plan for rp_attendance_system

## Current Issues Identified

1. **Redundant Columns:**
   - `students` table contains personal information (first_name, last_name, email, telephone, sex, photo, password) that duplicates data in the `users` table.
   - `courses` table has both `name` and `course_name` columns with identical data.
   - `students` table has `department_id` which is redundant since `option_id` already links to `options` which has `department_id`.

2. **Missing Foreign Keys:**
   - `lecturers.department_id` lacks FK to `departments.id`
   - `leave_requests.reviewed_by` lacks FK to `users.id`
   - `system_logs.user_id` lacks FK to `users.id`
   - `tech_logs.tech_id` lacks FK to `users.id`

3. **Inconsistent Role Handling:**
   - `lecturers` table has its own personal info and password, but `attendance_sessions.lecturer_id` references `users.id`. No lecturers exist in `users` table currently.

## Proposed Normalization Changes (to achieve 3NF)

### 1. Extend `users` table to include common personal fields
```sql
ALTER TABLE users
ADD COLUMN first_name VARCHAR(100),
ADD COLUMN last_name VARCHAR(100),
ADD COLUMN phone VARCHAR(20),
ADD COLUMN sex ENUM('Male','Female','Other'),
ADD COLUMN photo VARCHAR(255),
ADD COLUMN dob DATE;
```

### 2. Remove redundant columns from `students` table
```sql
ALTER TABLE students
DROP COLUMN first_name,
DROP COLUMN last_name,
DROP COLUMN email,
DROP COLUMN telephone,
DROP COLUMN sex,
DROP COLUMN photo,
DROP COLUMN password,
DROP COLUMN department_id;
```

### 3. Update `lecturers` table to reference `users`
```sql
ALTER TABLE lecturers
ADD COLUMN user_id INT(11),
ADD CONSTRAINT fk_lecturers_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
DROP COLUMN first_name,
DROP COLUMN last_name,
DROP COLUMN email,
DROP COLUMN phone,
DROP COLUMN photo,
DROP COLUMN password;
```

### 4. Remove redundant column from `courses` table
```sql
ALTER TABLE courses
DROP COLUMN course_name;
```

### 5. Add missing foreign key constraints
```sql
-- Lecturers department FK
ALTER TABLE lecturers
ADD CONSTRAINT fk_lecturers_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- Leave requests reviewed_by FK
ALTER TABLE leave_requests
ADD CONSTRAINT fk_leave_requests_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

-- System logs user_id FK
ALTER TABLE system_logs
ADD CONSTRAINT fk_system_logs_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Tech logs tech_id FK
ALTER TABLE tech_logs
ADD CONSTRAINT fk_tech_logs_tech_id FOREIGN KEY (tech_id) REFERENCES users(id) ON DELETE CASCADE;
```

## Implementation Notes

- **Data Migration Required:** Before dropping columns, data from `students` and `lecturers` personal fields must be migrated to the corresponding `users` records.
- **Lecturer Users:** Ensure all lecturers have corresponding entries in the `users` table with appropriate roles.
- **Application Updates:** PHP code will need updates to query personal info from `users` table instead of role-specific tables.
- **Testing:** After changes, test all CRUD operations to ensure FK constraints work properly.

## Expected Benefits

- Eliminates data redundancy
- Ensures referential integrity with proper FKs
- Achieves 3NF compliance
- Reduces update anomalies
- Improves data consistency

## Next Steps

1. Backup the database
2. Migrate existing data to `users` table
3. Apply the ALTER statements above
4. Update application code
5. Test thoroughly