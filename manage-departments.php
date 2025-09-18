<?php
session_start();
require_once "config.php";
require_once "session_check.php"; // ensure admin access

function jsonResponse($status, $message, $extra = []) {
    echo json_encode(array_merge(['status'=>$status,'message'=>$message],$extra));
    exit;
}

// --- AJAX Actions ---
if(isset($_GET['ajax']) && $_GET['ajax']==='1' && isset($_GET['action'])){
    $action = $_GET['action'];

    if($action==='list_departments'){
        $stmt=$pdo->query("
            SELECT d.id AS dept_id,d.name AS dept_name,d.hod_id,u.username AS hod_name
            FROM departments d
            LEFT JOIN users u ON d.hod_id=u.id
            ORDER BY d.name
        ");
        $departments=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($departments as &$dept){
            $stmt2=$pdo->prepare("SELECT id,name FROM programs WHERE department_id=?");
            $stmt2->execute([$dept['dept_id']]);
            $dept['programs']=$stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($departments); exit;
    }

    if($action==='add_department' && $_SERVER['REQUEST_METHOD']==='POST'){
        $name=trim($_POST['department_name']??'');
        $hod_id=!empty($_POST['hod_id'])?intval($_POST['hod_id']):null;
        $programs=$_POST['programs']??[];
        if($name==='') jsonResponse('error','Department name required');
        $chk=$pdo->prepare("SELECT COUNT(*) FROM departments WHERE name=?");
        $chk->execute([$name]);
        if($chk->fetchColumn()>0) jsonResponse('error','Department already exists');
        $stmt=$pdo->prepare("INSERT INTO departments(name,hod_id) VALUES(?,?)");
        $stmt->execute([$name,$hod_id]);
        $dept_id=$pdo->lastInsertId();
        if(!empty($programs)){
            $stmt2=$pdo->prepare("INSERT INTO programs(name,department_id) VALUES(?,?)");
            foreach($programs as $prog){ if(trim($prog)) $stmt2->execute([trim($prog),$dept_id]); }
        }
        jsonResponse('success','Department added');
    }

    if($action==='edit_department' && $_SERVER['REQUEST_METHOD']==='POST'){
        $dept_id=intval($_POST['department_id']??0);
        $name=trim($_POST['department_name']??'');
        $hod_id=!empty($_POST['hod_id'])?intval($_POST['hod_id']):null;
        if(!$dept_id||$name==='') jsonResponse('error','Invalid data');
        $stmt=$pdo->prepare("UPDATE departments SET name=?,hod_id=? WHERE id=?");
        $stmt->execute([$name,$hod_id,$dept_id]);
        jsonResponse('success','Department updated');
    }

    if($action==='add_program' && $_SERVER['REQUEST_METHOD']==='POST'){
        $dept_id=intval($_POST['department_id']??0);
        $program_name=trim($_POST['program_name']??'');
        if(!$dept_id||$program_name==='') jsonResponse('error','Invalid data');
        $chk=$pdo->prepare("SELECT COUNT(*) FROM programs WHERE name=? AND department_id=?");
        $chk->execute([$program_name,$dept_id]);
        if($chk->fetchColumn()>0) jsonResponse('error','Program already exists in this department');
        $stmt=$pdo->prepare("INSERT INTO programs(name,department_id) VALUES(?,?)");
        $stmt->execute([$program_name,$dept_id]);
        jsonResponse('success','Program added');
    }

    if($action==='delete_department' && $_SERVER['REQUEST_METHOD']==='POST'){
        $dept_id=intval($_POST['department_id']??0);
        if(!$dept_id) jsonResponse('error','Invalid department ID');
        $pdo->beginTransaction();
        try{
            $pdo->prepare("DELETE FROM programs WHERE department_id=?")->execute([$dept_id]);
            $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$dept_id]);
            $pdo->commit();
            jsonResponse('success','Department deleted');
        }catch(Exception $e){
            $pdo->rollBack();
            jsonResponse('error','Failed to delete department');
        }
    }

    if($action==='delete_program' && $_SERVER['REQUEST_METHOD']==='POST'){
        $prog_id=intval($_POST['program_id']??0);
        if(!$prog_id) jsonResponse('error','Invalid program ID');
        $pdo->prepare("DELETE FROM programs WHERE id=?")->execute([$prog_id]);
        jsonResponse('success','Program deleted');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Departments Management</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<style>
body{background:#f8f9fa;font-family:'Segoe UI',sans-serif;}
h2{font-weight:600;margin-bottom:20px;}
.card{border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.08);}
.card h5{font-size:1.1rem;margin-bottom:12px;}
.program-list{list-style:none;padding-left:0;}
.program-list li{padding:6px 10px;margin:4px 0;background:#eef3f9;border-radius:4px;display:flex;justify-content:space-between;align-items:center;}
.program-list button{padding:2px 6px;font-size:0.8rem;}
#addDepartmentForm{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.08);}
.alert{margin-top:10px;}
</style>
</head>
<body class="container py-4">

<h2>Departments & Programs</h2>
<div id="alertBox"></div>

<!-- Add Department Form -->
<form id="addDepartmentForm" class="mb-4">
  <input type="hidden" name="department_id" id="deptId">
  <div class="mb-3">
    <label class="form-label">Department Name</label>
    <input type="text" name="department_name" id="deptName" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Head of Department</label>
    <select name="hod_id" id="hodId" class="form-control">
      <option value="">-- Select HoD --</option>
      <?php
      $hods=$pdo->query("SELECT id,username FROM users WHERE role='hod' ORDER BY username")->fetchAll();
      foreach($hods as $hod){ echo "<option value='{$hod['id']}'>{$hod['username']}</option>"; }
      ?>
    </select>
  </div>
  <div id="programsWrapper" class="mb-3">
    <label class="form-label">Programs</label>
    <input type="text" name="programs[]" class="form-control mb-2" placeholder="Program name">
  </div>
  <button type="button" id="addProgramField" class="btn btn-outline-secondary btn-sm mb-3">+ Add Another Program</button><br>
  <button type="submit" class="btn btn-primary">Save Department</button>
  <button type="button" id="cancelEdit" class="btn btn-secondary d-none">Cancel Edit</button>
</form>

<!-- Departments List -->
<div id="departmentsList"></div>

<script>
$(function(){
  function showAlert(type,msg){$("#alertBox").html(`<div class="alert alert-${type}">${msg}</div>`);setTimeout(()=>$("#alertBox").html(""),3000);}
  function loadDepartments(){
    $.get("?ajax=1&action=list_departments",function(data){
      let html="";
      data.forEach(d=>{
        html+=`<div class="card mb-3"><div class="card-body">
          <h5>${d.dept_name} <small class="text-muted">(HoD: ${d.hod_name??'Not Assigned'})</small>
            <button class="btn btn-sm btn-outline-warning editDept float-end" data-id="${d.dept_id}" data-name="${d.dept_name}" data-hod="${d.hod_id??''}"><i class="bi bi-pencil"></i> Edit</button>
          </h5>
          <ul class="program-list">`;
        d.programs.forEach(p=>{
          html+=`<li>${p.name}<button class="btn btn-danger btn-sm deleteProgram" data-id="${p.id}">Delete</button></li>`;
        });
        html+=`</ul>
          <div class="input-group mb-2">
            <input type="text" class="form-control form-control-sm addProgramInput" placeholder="New program">
            <button class="btn btn-success btn-sm addProgramBtn" data-id="${d.dept_id}">+ Add Program</button>
          </div>
          <button class="btn btn-danger btn-sm deleteDept" data-id="${d.dept_id}">Delete Department</button>
        </div></div>`;
      });
      $("#departmentsList").html(html);
    },"json");
  }
  loadDepartments();

  $("#addDepartmentForm").submit(function(e){
    e.preventDefault();
    let action=$("#deptId").val()?"edit_department":"add_department";
    $.post("?ajax=1&action="+action,$(this).serialize(),function(res){
      if(res.status==="success"){showAlert("success",res.message);$("#addDepartmentForm")[0].reset();$("#deptId").val("");$("#cancelEdit").addClass("d-none");loadDepartments();}
      else showAlert("danger",res.message);
    },"json");
  });

  $("#addProgramField").click(function(){$("#programsWrapper").append(`<input type="text" name="programs[]" class="form-control mb-2" placeholder="Program name">`);});
  $(document).on("click",".addProgramBtn",function(){
    let id=$(this).data("id"),name=$(this).siblings(".addProgramInput").val();if(!name)return;
    $.post("?ajax=1&action=add_program",{department_id:id,program_name:name},function(res){if(res.status==="success"){showAlert("success",res.message);loadDepartments();}else showAlert("danger",res.message);},"json");
  });
  $(document).on("click",".deleteDept",function(){if(!confirm("Delete this department and its programs?"))return;let id=$(this).data("id");$.post("?ajax=1&action=delete_department",{department_id:id},function(res){if(res.status==="success"){showAlert("success",res.message);loadDepartments();}else showAlert("danger",res.message);},"json");});
  $(document).on("click",".deleteProgram",function(){if(!confirm("Delete this program?"))return;let id=$(this).data("id");$.post("?ajax=1&action=delete_program",{program_id:id},function(res){if(res.status==="success"){showAlert("success",res.message);loadDepartments();}else showAlert("danger",res.message);},"json");});
  $(document).on("click",".editDept",function(){
    $("#deptId").val($(this).data("id"));
    $("#deptName").val($(this).data("name"));
    $("#hodId").val($(this).data("hod"));
    $("#cancelEdit").removeClass("d-none");
    $('html,body').animate({scrollTop:0},200);
  });
  $("#cancelEdit").click(function(){$("#addDepartmentForm")[0].reset();$("#deptId").val("");$(this).addClass("d-none");});
});
</script>
</body>
</html>
