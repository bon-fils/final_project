<?php
session_start();
require_once "config.php";
require_once "session_check.php"; // checks if admin is logged in

// Handle AJAX requests
if(isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action'])){
    $action = $_GET['action'];

    // List departments with HoD names and programs
    if($action === 'list_departments'){
        $stmt = $pdo->query("
            SELECT d.id AS dept_id, d.name AS dept_name, d.hod_id, u.username AS hod_name
            FROM department d
            LEFT JOIN users u ON d.hod_id = u.id
            ORDER BY d.name
        ");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($departments as &$dept){
            $stmt2 = $pdo->prepare("SELECT id, name FROM programs WHERE department_id = ?");
            $stmt2->execute([$dept['dept_id']]);
            $dept['programs'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($departments);
        exit;
    }

    // Add new department
    if($action === 'add_department' && $_SERVER['REQUEST_METHOD'] === 'POST'){
        $name = trim($_POST['department_name'] ?? '');
        $hod_id = $_POST['hod_id'] ?: null;
        $programs = $_POST['programs'] ?? [];

        if($name){
            $stmt = $pdo->prepare("INSERT INTO department (name, hod_id) VALUES (?, ?)");
            $stmt->execute([$name, $hod_id]);
            $dept_id = $pdo->lastInsertId();

            if(!empty($programs)){
                $stmt2 = $pdo->prepare("INSERT INTO programs (name, department_id) VALUES (?, ?)");
                foreach($programs as $prog){
                    if(trim($prog)) $stmt2->execute([trim($prog), $dept_id]);
                }
            }
            echo json_encode(['status'=>'success','message'=>'Department added successfully']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Department name required']);
        }
        exit;
    }

    // Edit department
    if($action === 'edit_department' && $_SERVER['REQUEST_METHOD'] === 'POST'){
        $dept_id = $_POST['department_id'] ?? 0;
        $name = trim($_POST['department_name'] ?? '');
        $hod_id = $_POST['hod_id'] ?: null;

        if($dept_id && $name){
            $stmt = $pdo->prepare("UPDATE department SET name=?, hod_id=? WHERE id=?");
            $stmt->execute([$name, $hod_id, $dept_id]);
            echo json_encode(['status'=>'success','message'=>'Department updated']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Invalid data']);
        }
        exit;
    }

    // Add program to existing department
    if($action === 'add_program' && $_SERVER['REQUEST_METHOD'] === 'POST'){
        $dept_id = $_POST['department_id'] ?? 0;
        $program_name = trim($_POST['program_name'] ?? '');
        if($dept_id && $program_name){
            $stmt = $pdo->prepare("INSERT INTO programs (name, department_id) VALUES (?, ?)");
            $stmt->execute([$program_name, $dept_id]);
            echo json_encode(['status'=>'success','message'=>'Program added']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Invalid data']);
        }
        exit;
    }

    // Delete department
    if($action === 'delete_department' && isset($_POST['department_id'])){
        $dept_id = $_POST['department_id'];
        $stmt = $pdo->prepare("DELETE FROM programs WHERE department_id = ?");
        $stmt->execute([$dept_id]);
        $stmt2 = $pdo->prepare("DELETE FROM department WHERE id = ?");
        $stmt2->execute([$dept_id]);
        echo json_encode(['status'=>'success','message'=>'Department deleted']);
        exit;
    }

    // Delete program
    if($action === 'delete_program' && isset($_POST['program_id'])){
        $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->execute([$_POST['program_id']]);
        echo json_encode(['status'=>'success','message'=>'Program deleted']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Departments | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body{font-family:'Segoe UI',sans-serif;background:#f5f7fa;margin:0;}
.sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:#003366;color:#fff;padding-top:20px;overflow-y:auto;}
.sidebar a{display:block;padding:12px 20px;color:#fff;text-decoration:none;}
.sidebar a:hover{background:#0059b3;}
.topbar{margin-left:250px;background:#fff;padding:10px 30px;border-bottom:1px solid #ddd;}
.main-content{margin-left:250px;padding:30px;}
.card{border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
.footer{text-align:center;margin-left:250px;padding:15px;font-size:0.9rem;color:#666;background:#f0f0f0;}
@media(max-width:768px){.sidebar,.topbar,.main-content,.footer{margin-left:0 !important;width:100%;}.sidebar{display:none;}}
</style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-4">
      <img src="RP_Logo.jpeg" width="100">
      <h5 class="fw-bold">Admin</h5>
      <hr style="border-color:#ffffff66;">
    </div>
    <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
    <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
    <a href="admin-reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Manage Departments & Programs</h5>
    <span>Admin Panel</span>
</div>

<div class="main-content">
<div class="card p-4">
    <div class="d-flex justify-content-between mb-3">
        <h5 class="fw-semibold">Departments</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
            <i class="fas fa-plus me-1"></i> Add Department
        </button>
    </div>

    <table class="table table-hover table-bordered align-middle" id="deptTable">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Department Name</th>
                <th>HoD</th>
                <th>Programs</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
</div>

<!-- Add/Edit Department Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog">
<form class="modal-content" id="addDeptForm">
<div class="modal-header">
    <h5 class="modal-title">Add / Edit Department</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <input type="hidden" name="department_id" id="deptId">
    <div class="mb-3">
        <label class="form-label">Department Name</label>
        <input type="text" class="form-control" name="department_name" id="deptName" required>
    </div>
    <div class="mb-3">
        <label class="form-label">HoD</label>
        <select class="form-select" name="hod_id" id="hodSelect">
            <option value="">-- Select HoD --</option>
            <?php
            $stmt = $pdo->query("SELECT id, username FROM users WHERE role='lecturer' ORDER BY username");
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                echo "<option value='{$row['id']}'>{$row['username']}</option>";
            }
            ?>
        </select>
    </div>
    <div class="mb-2">
        <label class="form-label">Programs</label>
        <div id="programList">
            <div class="input-group mb-2">
                <input type="text" name="programs[]" class="form-control" placeholder="Program Name" required>
                <button type="button" class="btn btn-outline-danger remove-program"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <button type="button" id="addProgramBtn" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> Add More</button>
    </div>
</div>
<div class="modal-footer">
    <button type="submit" class="btn btn-primary">Save</button>
</div>
</form>
</div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Admin Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function loadDepartments(){
    $.getJSON('manage-departments.php',{ajax:'1',action:'list_departments'},function(data){
        const tbody = $('#deptTable tbody').empty();
        data.forEach((dept,i)=>{
            let programs = dept.programs.length ? '<ul class="mb-0">'+dept.programs.map(p=>'<li>'+p.name+' <button class="btn btn-sm btn-danger delete-prog" data-id="'+p.id+'"><i class="fas fa-times"></i></button></li>').join('')+'</ul>':''; 
            tbody.append(`<tr>
                <td>${i+1}</td>
                <td>${dept.dept_name}</td>
                <td>${dept.hod_name ?? ''}</td>
                <td>${programs}<button class="btn btn-sm btn-success add-program-btn mt-1" data-dept="${dept.dept_id}"><i class="fas fa-plus"></i> Add Program</button></td>
                <td>
                    <button class="btn btn-sm btn-warning edit-dept" data-id="${dept.dept_id}" data-name="${dept.dept_name}" data-hod="${dept.hod_id ?? ''}"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger delete-dept" data-id="${dept.dept_id}"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`);
        });
    });
}

$(document).ready(function(){
    loadDepartments();

    $('#addDeptForm').submit(function(e){
        e.preventDefault();
        let actionUrl = $('#deptId').val() ? 'edit_department' : 'add_department';
        $.post('manage-departments.php?ajax=1&action='+actionUrl,$(this).serialize(),function(resp){
            alert(resp.message);
            if(resp.status==='success'){
                $('#addDeptModal').modal('hide');
                $('#addDeptForm')[0].reset();
                $('#deptId').val('');
                loadDepartments();
            }
        },'json');
    });

    $('#addProgramBtn').click(function(){
        $('#programList').append(`<div class="input-group mb-2">
            <input type="text" name="programs[]" class="form-control" placeholder="Program Name" required>
            <button type="button" class="btn btn-outline-danger remove-program"><i class="fas fa-times"></i></button>
        </div>`);
    });
    $(document).on('click','.remove-program',function(){ $(this).closest('.input-group').remove(); });

    $(document).on('click','.edit-dept',function(){
        $('#deptId').val($(this).data('id'));
        $('#deptName').val($(this).data('name'));
        $('#hodSelect').val($(this).data('hod'));
        $('#addDeptModal').modal('show');
    });

    $(document).on('click','.delete-dept',function(){
        if(confirm('Delete this department?')){
            const dept_id = $(this).data('id');
            $.post('manage-departments.php?ajax=1&action=delete_department',{department_id:dept_id},function(resp){
                alert(resp.message);
                loadDepartments();
            },'json');
        }
    });

    $(document).on('click','.add-program-btn',function(){
        $('#programDeptId').val($(this).data('dept'));
        $('#addProgramModal').modal('show');
    });

    $('#addProgramForm').submit(function(e){
        e.preventDefault();
        $.post('manage-departments.php?ajax=1&action=add_program',$(this).serialize(),function(resp){
            alert(resp.message);
            if(resp.status==='success'){
                $('#addProgramModal').modal('hide');
                $('#addProgramForm')[0].reset();
                loadDepartments();
            }
        },'json');
    });

    $(document).on('click','.delete-prog',function(){
        if(confirm('Delete this program?')){
            const prog_id = $(this).data('id');
            $.post('manage-departments.php?ajax=1&action=delete_program',{program_id:prog_id},function(resp){
                alert(resp.message);
                loadDepartments();
            },'json');
        }
    });
});
</script>
</body>
</html>
