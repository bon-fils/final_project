# Database Foreign Key Constraint Fix

**Date**: October 20, 2025  
**Issue**: Foreign key constraint violation in HOD assignment

---

## 🐛 Problem Identified

**Error Message**:
```
SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`rp_attendance_system`.`departments`, CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`hod_id`) REFERENCES `users` (`id`) ON DELETE SET NULL)
```

**Root Cause**: 
The system was trying to assign a `lecturer.id` to the `departments.hod_id` field, but the foreign key constraint requires `departments.hod_id` to reference `users.id`.

## 🔍 Database Schema Analysis

The database has this structure:
- `lecturers` table: Contains lecturer records with `lecturer.id` and `lecturer.user_id`
- `users` table: Contains user accounts with `users.id`
- `departments` table: Has `hod_id` field that must reference `users.id` (not `lecturer.id`)

**The Issue**: The code was using `lecturer.id` instead of `users.id` for the HOD assignment.

---

## ✅ Solution Applied

### **File: `api/assign-hod-api.php`**

**Before (Incorrect)**:
```php
// This was trying to use lecturer.id directly
$stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
$stmt->execute([$hod_id ?: null, $department_id]); // $hod_id was lecturer.id
```

**After (Fixed)**:
```php
// Now correctly uses users.id
$user_id_to_assign = null;
if ($hod_id) {
    // We need to use the user_id, not the lecturer_id
    if (!isset($userId)) {
        throw new Exception('User ID not properly set for HOD assignment');
    }
    $user_id_to_assign = $userId; // This is users.id
}

$stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
$stmt->execute([$user_id_to_assign, $department_id]);
```

### **Key Changes Made**:

1. **Separated Lecturer ID from User ID**:
   - `$hod_id` = lecturer.id (used for lecturer selection)
   - `$userId` = users.id (used for database constraint)

2. **Updated Assignment Logic**:
   - The system now properly assigns `users.id` to `departments.hod_id`
   - Maintains the relationship between lecturer and user accounts

3. **Enhanced Logging**:
   - Added clear distinction between `lecturer_id` and `assigned_user_id`
   - Better debugging information for future issues

---

## 🧪 Testing Steps

1. **Select a Department**: Choose any department from the dropdown
2. **Select a Lecturer**: Choose a lecturer to assign as HOD
3. **Submit Assignment**: Click "Assign HOD" button
4. **Verify Success**: Should now complete without foreign key errors

---

## 🔧 Technical Details

### **Database Relationships**:
```sql
-- Correct relationship flow:
lecturers.user_id → users.id
departments.hod_id → users.id

-- The fix ensures:
SELECT lecturer.user_id FROM lecturers WHERE lecturer.id = ?
-- Then use that user_id for:
UPDATE departments SET hod_id = ? WHERE id = ?
```

### **Data Flow**:
1. User selects lecturer from dropdown (sends `lecturer.id`)
2. System looks up the corresponding `users.id` for that lecturer
3. Creates/updates user account if needed (gets `users.id`)
4. Assigns `users.id` to `departments.hod_id` (satisfies foreign key)

---

## 🎯 Result

The HOD assignment now works correctly with proper foreign key relationships:
- ✅ No more constraint violations
- ✅ Proper user account creation/updates
- ✅ Correct database relationships maintained
- ✅ Enhanced error handling and logging

The system now properly handles the relationship between lecturers, users, and department HOD assignments while maintaining database integrity.
