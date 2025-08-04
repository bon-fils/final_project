<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Reports | RP Attendance System</title>

  <!-- Bootstrap & Font-Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; margin: 0; }
    .sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100vh; background:#003366; color:#fff; padding-top:20px;}
    .sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none;}
    .sidebar a:hover, .sidebar a.active { background:#0059b3;}
    .topbar   { margin-left:250px; background:#fff; padding:10px 30px; border-bottom:1px solid #ddd;}
    .main-content{ margin-left:250px; padding:30px;}
    .card   { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.05);}
    .footer { text-align:center; margin-left:250px; padding:15px; font-size:.9rem; color:#666; background:#f0f0f0;}
    @media (max-width:768px){ .sidebar,.topbar,.main-content,.footer{margin-left:0;width:100%;} .sidebar{display:none;} }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4>👩‍💼 Admin</h4>
      <hr style="border-color:#ffffff66;">
    </div>
    <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
    <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
    <a href="admin-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Reports</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Attendance Reports</h5>
    <span>Admin Panel</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">

    <!-- Stats -->
    <div class="row mb-4 g-3">
      <div class="col-md-3">
        <div class="card text-white bg-primary h-100">
          <div class="card-body d-flex align-items-center">
            <i class="fas fa-users fa-3x me-3"></i>
            <div><h6>Total Students</h6><h4 id="totalStudents">--</h4></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-white bg-success h-100">
          <div class="card-body d-flex align-items-center">
            <i class="fas fa-calendar-check fa-3x me-3"></i>
            <div><h6>Total Sessions</h6><h4 id="totalSessions">--</h4></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-white bg-info h-100">
          <div class="card-body d-flex align-items-center">
            <i class="fas fa-percent fa-3x me-3"></i>
            <div><h6>Average Attendance</h6><h4 id="avgAttendance">--%</h4></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-white bg-warning h-100">
          <div class="card-body d-flex align-items-center">
            <i class="fas fa-clock fa-3x me-3"></i>
            <div><h6>Pending Leave</h6><h4 id="pendingLeave">--</h4></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <form id="filterForm" class="row g-3 align-items-center mb-4">
      <div class="col-md-3">
        <label class="form-label">Department</label>
        <select id="departmentFilter" class="form-select">
          <option value="">Select Department</option>
          <option value="computer">Computer Engineering</option>
          <option value="electrical">Electrical Engineering</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Option</label>
        <select id="optionFilter" class="form-select" disabled>
          <option value="">Select Option</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Course</label>
        <select id="courseFilter" class="form-select" disabled>
          <option value="">Select Course</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Date Range</label>
        <input type="text" id="dateRange" class="form-control" placeholder="YYYY-MM-DD to YYYY-MM-DD">
      </div>

      <div class="col-12 d-flex justify-content-end mt-2">
        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter me-1"></i> Filter</button>
        <button type="button" class="btn btn-outline-secondary" id="resetFilters"><i class="fas fa-undo me-1"></i> Reset</button>
      </div>
    </form>

    <!-- Report Table -->
    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Student Name</th><th>Department</th><th>Option</th><th>Course</th><th>Date</th><th>Status</th><th>Method</th>
          </tr>
        </thead>
        <tbody id="reportTableBody">
          <tr>
            <td>1</td><td>Jean Mukamana</td><td>Computer Engineering</td><td>Software</td><td>Web Dev</td><td>2025-06-20</td>
            <td><span class="badge bg-success">Present</span></td><td>Face</td>
          </tr>
          <tr>
            <td>2</td><td>Eric Uwizeye</td><td>Electrical Engineering</td><td>Power</td><td>Energy Management</td><td>2025-06-20</td>
            <td><span class="badge bg-danger">Absent</span></td><td>Finger</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Export Buttons -->
    <div class="d-flex justify-content-end mt-3 gap-2">
      <button class="btn btn-success"><i class="fas fa-file-excel me-1"></i> Export Excel</button>
      <button class="btn btn-danger"><i class="fas fa-file-pdf me-1"></i> Export PDF</button>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">&copy; 2025 Rwanda Polytechnic | Admin Panel</div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    /* ---------- Dummy stats ---------- */
    document.addEventListener('DOMContentLoaded',()=>{ 
      totalStudents.textContent='560'; totalSessions.textContent='78';
      avgAttendance.textContent='85%'; pendingLeave.textContent='12';
    });

    /* ---------- Cascading Department→Option→Course ---------- */
    const map = {
      computer:{
        options:{software:'Software', networking:'Networking'},
        courses:{software:['Web Dev','Mobile Dev','AI'], networking:['Routing','Switching','Cybersecurity']}
      },
      electrical:{
        options:{power:'Power', electronics:'Electronics'},
        courses:{power:['Energy Management','Grid Systems'], electronics:['Digital Circuits','Control Systems']}
      }
    };

    const deptSel   = document.getElementById('departmentFilter');
    const optSel    = document.getElementById('optionFilter');
    const courseSel = document.getElementById('courseFilter');

    deptSel.addEventListener('change',()=>{
      const d = deptSel.value;
      optSel.innerHTML = '<option value="">Select Option</option>';
      courseSel.innerHTML = '<option value="">Select Course</option>';
      courseSel.disabled = true;

      if(d && map[d]){
        Object.entries(map[d].options).forEach(([val,txt])=>{
          optSel.insertAdjacentHTML('beforeend',`<option value="${val}">${txt}</option>`);
        });
        optSel.disabled = false;
      }else{ optSel.disabled = true; }
    });

    optSel.addEventListener('change',()=>{
      const d = deptSel.value, o = optSel.value;
      courseSel.innerHTML = '<option value="">Select Course</option>';
      if(d && o && map[d].courses[o]){
        map[d].courses[o].forEach(c=>{
          courseSel.insertAdjacentHTML('beforeend',`<option value="${c}">${c}</option>`);
        });
        courseSel.disabled = false;
      }else{ courseSel.disabled = true; }
    });

    /* ---------- Filter / Reset ---------- */
    filterForm.addEventListener('submit',e=>{
      e.preventDefault();
      alert('Apply filter (connect to backend)');
    });
    resetFilters.onclick = ()=>{
      deptSel.value=''; optSel.innerHTML='<option value="">Select Option</option>'; optSel.disabled=true;
      courseSel.innerHTML='<option value="">Select Course</option>'; courseSel.disabled=true;
      dateRange.value='';
    };
  </script>
</body>
</html>
