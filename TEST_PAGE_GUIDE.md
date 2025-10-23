# 🧪 **Test Page Guide - Lecturer Attendance Reports**

## 📋 **Overview:**

Created a comprehensive diagnostic test page to verify all aspects of the lecturer attendance reports system.

**File:** `test-lecturer-reports.php`

---

## 🎯 **How to Use:**

### **Step 1: Access the Test Page**
```
http://localhost/final_project_1/test-lecturer-reports.php
```

**Requirements:**
- ✅ Must be logged in as a lecturer
- ✅ Session must be active

---

## 🔍 **What It Tests:**

### **Test 1: Session & Lecturer Information**
```
✅ Checks session data (user_id, role, lecturer_id)
✅ Verifies lecturer database record
✅ Compares session vs database lecturer_id
✅ Shows department information
```

**What to Look For:**
- ✅ Green alert = Session data is correct
- ⚠️ Yellow alert = Mismatch between session and database

---

### **Test 2: Courses Assigned to Lecturer**
```
✅ Lists all courses assigned to you
✅ Shows option_id and year for each course
✅ Counts students enrolled in each course
✅ Counts attendance sessions
✅ Shows attendance records
✅ Displays status (READY/NO SESSIONS/NO STUDENTS/EMPTY)
```

**Status Indicators:**
- 🟢 **READY** = Has students AND sessions
- 🟡 **NO SESSIONS** = Has students but no sessions
- 🟡 **NO STUDENTS** = Has sessions but no students
- 🔴 **EMPTY** = No students and no sessions

**What to Look For:**
- ❌ Red alert = No courses assigned
- ✅ Green alert = Courses found
- Check if option_id is NULL (shows in red)

---

### **Test 3: Students in Your Department**
```
✅ Lists all active students
✅ Shows option_id and year_level
✅ Displays CAST conversion (year_level → year_int)
✅ Groups by option and year
```

**What to Look For:**
- Year Level column = VARCHAR value ('1', '2', '3', '4')
- Year (INT) column = Converted value (1, 2, 3, 4)
- Should match for proper type casting

---

### **Test 4: Options/Programs in Department**
```
✅ Lists all options/programs
✅ Shows student count per option
✅ Verifies option status
```

**What to Look For:**
- Check if your courses' option_id matches existing options
- Verify students exist in those options

---

### **Test 5: API Endpoint Test**
```
✅ Tests the actual API endpoint
✅ Calls get-attendance-reports.php
✅ Shows raw JSON response
✅ Displays errors if any
```

**How to Use:**
1. Click "Test API: Get Lecturer Summary"
2. Wait for response
3. Check JSON output

**What to Look For:**
- ✅ Green button = API success
- ❌ Red button = API error
- Check JSON for courses array

---

### **Test 6: Recommendations**
```
✅ Automatically detects issues
✅ Provides SQL fix commands
✅ Shows actionable recommendations
```

**Common Issues Detected:**
- Courses missing option_id
- Courses with 0 students
- Missing lecturer assignments

---

## 📊 **Example Output:**

### **Successful Setup:**
```
Test 1: ✅ Session data is correct!

Test 2: ✅ Found 5 courses assigned to you
┌────┬─────────┬──────────────────┬─────────┬──────┬──────────┬──────────┬────────┐
│ ID │ Code    │ Course Name      │ Option  │ Year │ Students │ Sessions │ Status │
├────┼─────────┼──────────────────┼─────────┼──────┼──────────┼──────────┼────────┤
│ 11 │ ICT101  │ Intro to IT      │ IT (17) │ 1    │ 0        │ 4        │ NO STU │
│ 13 │ ICT301  │ Database Systems │ IT (17) │ 3    │ 6        │ 0        │ NO SES │
└────┴─────────┴──────────────────┴─────────┴──────┴──────────┴──────────┴────────┘

Test 3: ✅ Found 7 active students in your department

Test 4: ✅ Options listed with student counts

Test 5: ✅ API Test Successful

Test 6: ✅ All checks passed!
```

---

### **Setup with Issues:**
```
Test 1: ⚠️ Warning: Session lecturer_id doesn't match database!

Test 2: ❌ ERROR: No courses assigned to lecturer ID 1
Fix: UPDATE courses SET lecturer_id = 1 WHERE department_id = 7 AND lecturer_id IS NULL;

Test 3: ✅ Found 7 students

Test 6: ⚠️ Issues Found:
- 3 courses missing option_id
- 2 courses have 0 students enrolled

Recommended Fixes:
UPDATE courses SET option_id = 17 WHERE department_id = 7 AND option_id IS NULL;
```

---

## 🔧 **Common Issues & Fixes:**

### **Issue 1: No Courses Assigned**
```
❌ ERROR: No courses assigned to lecturer ID X

Fix:
UPDATE courses 
SET lecturer_id = X 
WHERE department_id = Y AND lecturer_id IS NULL;
```

---

### **Issue 2: Courses Missing option_id**
```
⚠️ 3 courses missing option_id (shows NULL in red)

Fix:
UPDATE courses 
SET option_id = 17 
WHERE department_id = 7 AND option_id IS NULL;
```

---

### **Issue 3: 0 Students Enrolled**
```
⚠️ Course shows 0 students

Possible Causes:
1. No students in that option/year combination
2. option_id mismatch
3. year_level type mismatch

Check:
SELECT * FROM students 
WHERE option_id = 17 AND year_level = '3';
```

---

### **Issue 4: Session Mismatch**
```
⚠️ Session lecturer_id doesn't match database

Fix: Re-login or check session_check.php
```

---

## 📋 **Workflow:**

### **Step 1: Run Test Page**
```
1. Login as lecturer
2. Go to test-lecturer-reports.php
3. Review all 6 tests
```

### **Step 2: Identify Issues**
```
1. Look for red/yellow alerts
2. Check status badges (EMPTY, NO STUDENTS, etc.)
3. Review Test 6 recommendations
```

### **Step 3: Apply Fixes**
```
1. Copy SQL commands from recommendations
2. Run in phpMyAdmin or MySQL client
3. Refresh test page
4. Verify fixes
```

### **Step 4: Test API**
```
1. Click "Test API" button
2. Check JSON response
3. Verify courses array is populated
```

### **Step 5: Go to Reports**
```
1. Click "Go to Reports Page" button
2. Apply filters
3. Verify data displays correctly
```

---

## 🎯 **Success Criteria:**

### **All Tests Should Show:**
- ✅ Session data correct
- ✅ At least 1 course assigned
- ✅ All courses have option_id
- ✅ Students exist for at least one course
- ✅ API returns valid JSON
- ✅ No critical issues in recommendations

---

## 📁 **Files:**

- ✅ `test-lecturer-reports.php` - Test page
- ✅ `TEST_PAGE_GUIDE.md` - This guide
- ✅ `check_lecturer_courses.txt` - SQL diagnostic commands
- ✅ `FIX_NO_COURSES_FOUND.md` - Fix guide

---

## 🔍 **Debugging Tips:**

### **Check PHP Error Log:**
```bash
# The test page logs debug info
tail -f /path/to/php_error.log

# Look for:
# "getLecturerSummary called with lecturer_id: X"
# "Found Y courses for lecturer X"
# "Course Z: option_id=17, year=3, students found=6"
```

### **Check Browser Console:**
```javascript
// Open browser DevTools (F12)
// Check Console tab for JavaScript errors
// Check Network tab for API responses
```

### **Direct Database Check:**
```sql
-- Verify lecturer exists
SELECT * FROM lecturers WHERE user_id = ?;

-- Verify courses assigned
SELECT * FROM courses WHERE lecturer_id = ?;

-- Verify students exist
SELECT * FROM students WHERE option_id = 17 AND year_level = '3';
```

---

## 🎉 **Summary:**

The test page provides:
- ✅ Complete diagnostic of all components
- ✅ Visual status indicators
- ✅ Automatic issue detection
- ✅ SQL fix commands
- ✅ API testing
- ✅ Actionable recommendations

**Use this page to verify your setup before using the actual reports page!**
