# Courses Table Updates Summary

## Overview
This document summarizes the updates made to the `courses` table in the RP Attendance System database dump file `rp_attendance_system (6).sql`.

## Changes Made

### 1. Table Structure Updates

#### **Added `year` Field:**
```sql
`year` int(11) NOT NULL DEFAULT 1
```
- **Purpose**: To categorize courses by academic year
- **Default**: Year 1
- **Required**: All courses must have a year assigned

#### **Set `lecturer_id` to NULL:**
- **All lecturer_id values**: Changed from specific IDs to NULL
- **Purpose**: Unassign all courses from lecturers for flexible assignment
- **Impact**: Courses can now be assigned to any lecturer dynamically

### 2. Course Year Distribution

#### **Year 1 Courses (Foundation/Basic):**
- ICT101 - Introduction to Information Technology
- CIV101 - Introduction to Civil Engineering
- CA101 - Introduction to Creative Arts
- CA102 - Digital Art Fundamentals

#### **Year 2 Courses (Intermediate):**
- ICT201 - Programming Fundamentals
- CIV201 - Structural Analysis
- CA201 - Advanced Drawing Techniques
- CA202 - Color Theory and Application

#### **Year 3 Courses (Advanced):**
- ICT301 - Database Management Systems
- CIV301 - Construction Materials
- CA301 - Portfolio Development
- CA302 - Art History and Criticism

#### **Year 4 Courses (Specialized/BTEC):**
- ICT401 - Web Development
- ICT501 - Network Administration
- CIV401 - Project Management
- CIV501 - Environmental Engineering

### 3. Updated Table Structure

```sql
CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `option_id` int(11) DEFAULT NULL,
  `lecturer_id` int(11) DEFAULT NULL,  -- Now NULL for all courses
  `year` int(11) NOT NULL DEFAULT 1,   -- NEW: Academic year
  `credits` int(11) NOT NULL DEFAULT 0,
  `duration_hours` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4. Database Indexes

#### **Added Index:**
```sql
ADD KEY `idx_courses_year` (`year`);
```
- **Purpose**: Performance optimization for year-based queries
- **Usage**: Filtering courses by academic year

### 5. Course Distribution Summary

| Department | Year 1 | Year 2 | Year 3 | Year 4 | Total |
|------------|--------|--------|--------|--------|-------|
| ICT | 1 | 1 | 1 | 2 | 5 |
| Civil Engineering | 1 | 1 | 1 | 2 | 5 |
| Creative Arts | 2 | 2 | 2 | 0 | 6 |
| **Total** | **4** | **4** | **4** | **4** | **16** |

## Benefits Achieved

### **Academic Structure:**
- ✅ **Clear Year Progression**: Courses properly distributed across 4 years
- ✅ **BTEC Integration**: Year 4 represents specialized/BTEC level courses
- ✅ **Department Balance**: Fair distribution across all departments

### **Flexibility:**
- ✅ **Lecturer Unassignment**: All courses now available for dynamic assignment
- ✅ **Year-Based Filtering**: Easy to query courses by academic year
- ✅ **Scalable Structure**: Easy to add more courses or years

### **Performance:**
- ✅ **Indexed Year Field**: Fast queries for year-based course selection
- ✅ **Optimized Structure**: Better database performance

## Query Examples

### **Get Courses by Year:**
```sql
SELECT * FROM courses WHERE year = 1 AND department_id = 7;
```

### **Get Available Courses (unassigned):**
```sql
SELECT * FROM courses WHERE lecturer_id IS NULL AND status = 'active';
```

### **Get Courses by Department and Year:**
```sql
SELECT c.*, d.name as department_name
FROM courses c
JOIN departments d ON c.department_id = d.id
WHERE c.year = 2 AND d.id = 7
ORDER BY c.name;
```

## Migration Notes

### **For Existing Systems:**
1. **Backup** current database before import
2. **Update** course assignment logic to handle NULL lecturer_id
3. **Modify** course selection queries to include year filtering
4. **Update** attendance session creation to consider course years

### **Application Code Changes:**
- Update course listing to show year information
- Modify lecturer assignment functionality
- Add year-based course filtering in UI
- Update attendance session course selection

## Version
- **Database Version**: 2.3 (Courses Updated)
- **Courses Updated**: 16 courses across 4 years
- **Lecturer Assignments**: All cleared (NULL)
- **Last Updated**: 2025-10-07

---

**Status**: ✅ **COURSES TABLE UPDATED** - All courses now have year assignments and are unassigned from lecturers for flexible management.