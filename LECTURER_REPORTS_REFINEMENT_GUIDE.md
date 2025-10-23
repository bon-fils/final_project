# 📊 **Lecturer Attendance Reports - Refinement Guide**

## 🎯 **What Needs to Be Updated:**

The current `lecturer-attendance-reports.php` uses **demo/mock data**. We need to replace it with **real backend API calls**.

---

## 🔧 **Changes Required:**

### **1. Replace Demo Data Loading**

**Current (Lines 665-689):**
```javascript
const demoData = {
    departments: [...],
    courses: [...],
    students: [...]
};
```

**Replace with:**
```javascript
// Load real courses on page load
document.addEventListener('DOMContentLoaded', async function() {
    await loadLecturerCourses();
    setDefaultDates();
});

async function loadLecturerCourses() {
    const response = await fetch('api/get-lecturer-courses.php');
    const data = await response.json();
    
    if (data.status === 'success') {
        populateCourseDropdown(data.data);
    }
}
```

---

### **2. Update Generate Report Function**

**Current (Lines 724-769):**
```javascript
function generateReport() {
    // Uses demo data
    const reportData = generateDemoReportData(...);
    displayReport(reportData);
}
```

**Replace with:**
```javascript
async function generateReport() {
    const reportType = document.getElementById('reportType').value;
    const courseId = document.getElementById('courseSelect').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    // Validation
    if (reportType === 'course' && !courseId) {
        showAlert('Please select a course', 'warning');
        return;
    }
    
    // Show loading
    document.getElementById('loadingOverlay').classList.remove('d-none');
    
    try {
        const params = new URLSearchParams({
            report_type: reportType,
            course_id: courseId,
            start_date: startDate,
            end_date: endDate
        });
        
        const response = await fetch(`api/get-attendance-reports.php?${params}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            currentReportData = data.data;
            displayReport(data.data);
            showAlert('Report generated successfully!', 'success');
        } else {
            showAlert('Error: ' + data.message, 'error');
        }
    } catch (error) {
        showAlert('Failed to generate report', 'error');
    } finally {
        document.getElementById('loadingOverlay').classList.add('d-none');
    }
}
```

---

### **3. Update Display Report Function**

**Current (Lines 813-933):**
```javascript
function displayReport(data) {
    // Uses demo data structure
    data.students.forEach(student => {
        // student.attendance (demo field)
    });
}
```

**Replace with:**
```javascript
function displayReport(data) {
    const content = document.getElementById('reportContent');
    
    if (!data.students || data.students.length === 0) {
        content.innerHTML = '<div class="empty-state">No data found</div>';
        return;
    }
    
    let html = `
        <!-- Summary Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3>${data.summary.total_students}</h3>
                        <p>Total Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3>${data.summary.total_sessions}</h3>
                        <p>Total Sessions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3>${data.summary.average_attendance}%</h3>
                        <p>Average Attendance</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3>${data.summary.students_above_85}</h3>
                        <p>Above 85%</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="card">
            <div class="card-header">
                <h6>Student Attendance Report</h6>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reg No</th>
                            <th>Student Name</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Rate</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>`;
    
    data.students.forEach(student => {
        html += `
                        <tr>
                            <td>${student.reg_no}</td>
                            <td>${student.student_name}</td>
                            <td>${student.present_count}</td>
                            <td>${student.absent_count}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="me-2">${student.attendance_rate}%</span>
                                    <div class="attendance-bar">
                                        <div class="attendance-fill ${student.attendance_status}" 
                                             style="width: ${student.attendance_rate}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge bg-${getStatusColor(student.attendance_status)}">
                                    ${student.attendance_status}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" 
                                        onclick="showStudentDetails(${student.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>`;
    });
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>`;
    
    content.innerHTML = html;
}

function getStatusColor(status) {
    switch(status) {
        case 'excellent': return 'success';
        case 'good': return 'warning';
        case 'average': return 'info';
        case 'poor': return 'danger';
        default: return 'secondary';
    }
}
```

---

### **4. Update Export Functions**

**Current (Lines 992-1019):**
```javascript
function exportToCSV() {
    // Uses currentReportData with demo structure
    currentReportData.students.forEach(student => {
        csv += `"${student.name}"...`;  // Demo fields
    });
}
```

**Replace with:**
```javascript
function exportToCSV() {
    if (!currentReportData) {
        showAlert('Please generate a report first', 'warning');
        return;
    }
    
    const courseId = document.getElementById('courseSelect').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    const params = new URLSearchParams({
        format: 'csv',
        report_type: 'course',
        course_id: courseId,
        start_date: startDate,
        end_date: endDate
    });
    
    window.location.href = `api/export-attendance-report.php?${params}`;
    showAlert('CSV export started...', 'success');
}

function exportToPDF() {
    if (!currentReportData) {
        showAlert('Please generate a report first', 'warning');
        return;
    }
    
    const courseId = document.getElementById('courseSelect').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    const params = new URLSearchParams({
        format: 'pdf',
        report_type: 'course',
        course_id: courseId,
        start_date: startDate,
        end_date: endDate
    });
    
    window.open(`api/export-attendance-report.php?${params}`, '_blank');
    showAlert('PDF opened in new tab', 'success');
}
```

---

### **5. Add Student Details Modal**

**Add this function:**
```javascript
async function showStudentDetails(studentId) {
    const modal = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
    const content = document.getElementById('studentDetailsContent');
    
    content.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';
    modal.show();
    
    try {
        const courseId = document.getElementById('courseSelect').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        const params = new URLSearchParams({
            report_type: 'student',
            student_id: studentId,
            course_id: courseId,
            start_date: startDate,
            end_date: endDate
        });
        
        const response = await fetch(`api/get-attendance-reports.php?${params}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            displayStudentDetails(data.data, content);
        }
    } catch (error) {
        content.innerHTML = '<div class="alert alert-danger">Error loading details</div>';
    }
}

function displayStudentDetails(data, container) {
    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h6>Student Information</h6>
                <p><strong>Name:</strong> ${data.student.student_name}</p>
                <p><strong>Reg No:</strong> ${data.student.reg_no}</p>
                <p><strong>Email:</strong> ${data.student.email}</p>
            </div>
            <div class="col-md-6">
                <h6>Attendance Summary</h6>
                <p><strong>Total Sessions:</strong> ${data.summary.total_sessions}</p>
                <p><strong>Present:</strong> ${data.summary.present_count}</p>
                <p><strong>Rate:</strong> ${data.summary.attendance_rate}%</p>
            </div>
        </div>
        
        <h6>Attendance Records</h6>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>`;
    
    data.records.forEach(record => {
        html += `
                <tr>
                    <td>${new Date(record.session_date).toLocaleDateString()}</td>
                    <td>${record.course_name}</td>
                    <td>
                        <span class="badge bg-${record.status === 'present' ? 'success' : 'danger'}">
                            ${record.status}
                        </span>
                    </td>
                </tr>`;
    });
    
    html += `
            </tbody>
        </table>`;
    
    container.innerHTML = html;
}
```

---

## 📋 **Quick Checklist:**

- [ ] Remove demo data (lines 665-689)
- [ ] Add `loadLecturerCourses()` function
- [ ] Update `generateReport()` to use API
- [ ] Update `displayReport()` to use real data structure
- [ ] Update `exportToCSV()` to use backend API
- [ ] Update `exportToPDF()` to use backend API
- [ ] Add `showStudentDetails()` function
- [ ] Add `displayStudentDetails()` function
- [ ] Test with real data

---

## 🧪 **Testing:**

1. **Load page** → Should load lecturer's courses
2. **Select course** → Should populate dropdown
3. **Generate report** → Should fetch real data
4. **View table** → Should show actual students
5. **Click student** → Should show attendance details
6. **Export CSV** → Should download real data
7. **Export PDF** → Should open printable report

---

## ✅ **Result:**

After these changes, the reports page will:
- ✅ Load real courses from database
- ✅ Generate reports with actual attendance data
- ✅ Show accurate statistics
- ✅ Export real data to CSV/PDF
- ✅ Display student attendance history

---

**All backend APIs are ready - just need to connect the frontend!** 🎉📊
