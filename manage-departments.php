<?php
session_start();
require_once "config.php";
require_once "session_check.php"; // ensure admin access
require_role(['admin']);

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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Departments Management | RP Attendance System</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<style>
/* ===== ROOT VARIABLES ===== */
:root {
  --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
  --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
  --light-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  --dark-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
  --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
  --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
  --border-radius: 12px;
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== BODY & CONTAINER ===== */
body {
  background: var(--primary-gradient);
  min-height: 100vh;
  font-family: 'Segoe UI', 'Roboto', sans-serif;
  margin: 0;
  position: relative;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>');
  pointer-events: none;
  z-index: -1;
}

.container {
  max-width: 1200px;
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-heavy);
  margin: 20px auto;
  padding: 30px;
  position: relative;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

/* ===== TYPOGRAPHY ===== */
h2 {
  font-weight: 700;
  color: #333;
  margin-bottom: 30px;
  text-align: center;
  position: relative;
  font-size: 2.2rem;
}

h2::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 80px;
  height: 4px;
  background: var(--primary-gradient);
  border-radius: 2px;
}

h4 {
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 20px;
}

/* ===== CARDS ===== */
.card {
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-light);
  border: none;
  transition: var(--transition);
  margin-bottom: 20px;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(5px);
  position: relative;
  overflow: hidden;
}

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--primary-gradient);
  opacity: 0;
  transition: var(--transition);
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-medium);
}

.card:hover::before {
  opacity: 1;
}

.card h5 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #333;
  margin-bottom: 15px;
}

.card h6 {
  font-size: 1rem;
  font-weight: 600;
  color: #495057;
  margin-bottom: 10px;
}

/* ===== STATISTICS CARDS ===== */
.stats-card {
  background: rgba(255, 255, 255, 0.95);
  border: none;
  border-radius: var(--border-radius);
  transition: var(--transition);
  box-shadow: var(--shadow-light);
  position: relative;
  overflow: hidden;
}

.stats-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--primary-gradient);
}

.stats-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: var(--shadow-heavy);
}

.stats-card .card-body {
  padding: 25px;
  text-align: center;
  position: relative;
  z-index: 2;
}

.stats-card i {
  font-size: 2.5rem;
  margin-bottom: 15px;
  opacity: 0.8;
}

.stats-card h3 {
  font-size: 2.8rem;
  font-weight: 700;
  color: #333;
  margin-bottom: 5px;
  text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stats-card p {
  font-size: 0.9rem;
  color: #6c757d;
  font-weight: 500;
  margin: 0;
}

/* ===== FORM ELEMENTS ===== */
.form-section {
  background: rgba(255, 255, 255, 0.95);
  padding: 30px;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-light);
  margin-bottom: 30px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.input-group-text {
  background: var(--primary-gradient);
  color: white;
  border: none;
  border-radius: 8px 0 0 8px;
}

.program-input-group .input-group-text {
  background: var(--primary-gradient);
  color: white;
  border: none;
}

.program-input-group .form-control:focus {
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
  border-color: #667eea;
  transform: scale(1.01);
}

.form-control, .form-select {
  border-radius: 8px;
  border: 2px solid #6c757d;
  transition: var(--transition);
  font-size: 0.95rem;
  background-color: #ffffff;
  color: #495057;
}

.form-control::placeholder {
  color: #6c757d;
  opacity: 0.7;
  font-style: italic;
}

.form-control:focus, .form-select:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.3rem rgba(102, 126, 234, 0.15);
  transform: translateY(-1px);
  background-color: #ffffff;
}

.form-control:focus::placeholder {
  opacity: 0.5;
}

.form-label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 8px;
}

/* ===== PROGRAM LIST ===== */
.program-list {
  list-style: none;
  padding-left: 0;
}

.program-list li {
  padding: 12px 16px;
  margin: 8px 0;
  background: var(--light-gradient);
  border-radius: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-left: 4px solid #667eea;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.program-list li::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--primary-gradient);
  opacity: 0;
  transition: var(--transition);
}

.program-list li:hover {
  transform: translateX(5px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.program-list li:hover::before {
  opacity: 0.05;
}

.program-list button {
  padding: 6px 10px;
  font-size: 0.85rem;
  border-radius: 6px;
  transition: var(--transition);
  border: none;
  background: rgba(220, 53, 69, 0.1);
  color: #dc3545;
}

.program-list button:hover {
  background: #dc3545;
  color: white;
  transform: scale(1.05);
}

/* ===== BUTTONS ===== */
.btn {
  border-radius: 8px;
  font-weight: 600;
  padding: 10px 20px;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.btn::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  transform: translate(-50%, -50%);
  transition: var(--transition);
}

.btn:hover::before {
  width: 300px;
  height: 300px;
}

.btn-primary {
  background: var(--primary-gradient);
  border: none;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
  background: var(--primary-gradient);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-outline-secondary {
  border: 2px solid #6c757d;
  color: #6c757d;
}

.btn-outline-secondary:hover {
  background: #6c757d;
  border-color: #6c757d;
  transform: translateY(-2px);
}

.btn-success {
  background: var(--success-gradient);
  border: none;
}

.btn-danger {
  background: var(--danger-gradient);
  border: none;
}

/* ===== ALERTS ===== */
.alert {
  border-radius: 10px;
  border: none;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  position: relative;
  overflow: hidden;
}

.alert::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: var(--primary-gradient);
}

.alert-success::before { background: var(--success-gradient); }
.alert-danger::before { background: var(--danger-gradient); }
.alert-warning::before { background: var(--warning-gradient); }
.alert-info::before { background: var(--info-gradient); }

/* ===== LOADING OVERLAY ===== */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(5px);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  border-radius: var(--border-radius);
}

.loading-overlay .spinner-border {
  color: #667eea;
  width: 3rem;
  height: 3rem;
}

/* ===== DEPARTMENT CARDS ===== */
.department-card {
  transition: var(--transition);
  border-left: 4px solid #667eea;
}

.department-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-medium);
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 768px) {
  .container {
    margin: 10px;
    padding: 20px;
  }

  h2 {
    font-size: 1.8rem;
  }

  .stats-card h3 {
    font-size: 2.2rem;
  }

  .stats-card i {
    font-size: 2rem;
  }

  .d-flex.justify-content-between {
    flex-direction: column;
    gap: 15px;
  }

  .input-group {
    max-width: 100% !important;
  }

  .form-section {
    padding: 20px;
  }

  .card-body {
    padding: 20px;
  }
}

@media (max-width: 576px) {
  .btn-group {
    flex-direction: column;
  }

  .btn-group .btn {
    margin-bottom: 5px;
  }

  .program-list li {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .program-list button {
    align-self: flex-end;
  }

  h2 {
    font-size: 1.5rem;
  }

  .stats-card h3 {
    font-size: 1.8rem;
  }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card {
  animation: fadeInUp 0.6s ease-out;
}

.stats-card:nth-child(1) { animation-delay: 0.1s; }
.stats-card:nth-child(2) { animation-delay: 0.2s; }
.stats-card:nth-child(3) { animation-delay: 0.3s; }
.stats-card:nth-child(4) { animation-delay: 0.4s; }

/* ===== DEPARTMENT FORM TABLE STYLING ===== */
#departmentFormTable {
  border-radius: var(--border-radius);
  overflow: hidden;
  box-shadow: var(--shadow-light);
}

#departmentFormTable thead th {
  background: var(--primary-gradient);
  color: white;
  border: none;
  font-weight: 600;
  font-size: 1.1rem;
  padding: 1rem;
}

#departmentFormTable tbody tr {
  transition: var(--transition);
}

#departmentFormTable tbody tr:hover {
  background: rgba(102, 126, 234, 0.02);
}

#departmentFormTable .table-light {
  background: var(--light-gradient) !important;
}

#departmentFormTable .table-light td {
  border-top: 2px solid rgba(102, 126, 234, 0.1);
  font-weight: 600;
  color: var(--primary-color, #667eea);
}

#departmentFormTable td {
  border: none;
  padding: 1rem;
  vertical-align: middle;
}

#departmentFormTable td:first-child {
  font-weight: 600;
  color: #495057;
  width: 30%;
}

/* ===== PROGRAMS TABLE STYLING ===== */
#programsTable {
  border-radius: var(--border-radius);
  overflow: hidden;
  margin: 0;
}

#programsTable thead th {
  background: var(--primary-gradient);
  color: white;
  border: none;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.8rem;
  letter-spacing: 0.5px;
  padding: 0.75rem 1rem;
}

#programsTable tbody tr {
  transition: var(--transition);
}

#programsTable tbody tr:hover {
  background: rgba(102, 126, 234, 0.05);
}

#programsTable .program-row td {
  border-bottom: 1px solid #e9ecef;
  vertical-align: middle;
  padding: 0.75rem 1rem;
}

#programsTable .program-row:last-child td {
  border-bottom: none;
}

/* ===== FORM ELEMENTS IN TABLE ===== */
#departmentFormTable .form-control,
#departmentFormTable .form-select {
  border: 2px solid #e9ecef;
  border-radius: 6px;
  transition: var(--transition);
}

#departmentFormTable .form-control:focus,
#departmentFormTable .form-select:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
  transform: translateY(-1px);
}

#departmentFormTable .form-text {
  font-size: 0.85rem;
  color: #6c757d;
  margin-top: 0.25rem;
}

/* ===== ALERT IN TABLE ===== */
#departmentFormTable .alert {
  margin: 0;
  border-radius: 6px;
}

#departmentFormTable .alert ul {
  margin-bottom: 0;
  padding-left: 1.2rem;
}

#departmentFormTable .alert li {
  margin-bottom: 0.25rem;
}

/* ===== GRADIENT BACKGROUNDS ===== */
.bg-gradient-primary {
  background: var(--primary-gradient) !important;
}

.bg-gradient-success {
  background: var(--success-gradient) !important;
}

.bg-gradient-info {
  background: var(--info-gradient) !important;
}

/* ===== CARD ENHANCEMENTS ===== */
.card {
  transition: var(--transition);
  border: none !important;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium) !important;
}

.card-header {
  border-bottom: none !important;
  font-weight: 600;
}

/* ===== FORM ENHANCEMENTS ===== */
.input-group-text {
  border: 2px solid #e9ecef;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.form-control:focus, .form-select:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
  transform: translateY(-1px);
}

/* ===== TABLE ENHANCEMENTS ===== */
.table {
  border-radius: var(--border-radius);
  overflow: hidden;
}

.table thead th {
  border: none;
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  padding: 1rem;
}

.table tbody tr {
  transition: var(--transition);
}

.table tbody tr:hover {
  background: rgba(102, 126, 234, 0.05) !important;
  transform: scale(1.01);
}

.table tbody td {
  border: none;
  padding: 1rem;
  vertical-align: middle;
}

/* ===== UTILITY CLASSES ===== */
.shadow-hover {
  transition: var(--transition);
}

.shadow-hover:hover {
  box-shadow: var(--shadow-heavy) !important;
  transform: translateY(-2px);
}

.gradient-text {
  background: var(--primary-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-weight: 700;
}

.glass-effect {
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

/* ===== ANIMATIONS ===== */
@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card {
  animation: slideInUp 0.6s ease-out;
}

.card:nth-child(1) { animation-delay: 0.1s; }
.card:nth-child(2) { animation-delay: 0.2s; }
.card:nth-child(3) { animation-delay: 0.3s; }

/* ===== ALERT ENHANCEMENTS ===== */
.alert {
  border-radius: var(--border-radius);
  border: none;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  position: relative;
  overflow: hidden;
}

.alert::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: var(--primary-gradient);
}

.alert-success::before { background: var(--success-gradient); }
.alert-danger::before { background: var(--danger-gradient); }
.alert-warning::before { background: var(--warning-gradient); }
.alert-info::before { background: var(--info-gradient); }

/* ===== BUTTON ENHANCEMENTS ===== */
.btn {
  border-radius: 8px;
  font-weight: 600;
  padding: 0.5rem 1.5rem;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
  border: 2px solid transparent;
}

.btn::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  transform: translate(-50%, -50%);
  transition: var(--transition);
}

.btn:hover::before {
  width: 300px;
  height: 300px;
}

.btn-primary {
  background: var(--primary-gradient);
  border-color: #667eea;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
  background: var(--primary-gradient);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  border-color: #5a6fd8;
}

/* ===== RESPONSIVE ENHANCEMENTS ===== */
@media (max-width: 768px) {
  .card-body {
    padding: 1.5rem !important;
  }

  .btn {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
  }

  .input-group {
    flex-direction: column;
  }

  .input-group .input-group-text {
    border-radius: 8px 8px 0 0 !important;
    border-bottom: none;
  }

  .input-group .form-control {
    border-radius: 0 0 8px 8px !important;
    border-top: none;
  }
}

/* ===== ENHANCED ANIMATIONS ===== */
.pulse {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

.bounce-in {
  animation: bounceIn 0.6s ease-out;
}

@keyframes bounceIn {
  0% { transform: scale(0.3); opacity: 0; }
  50% { transform: scale(1.05); }
  70% { transform: scale(0.9); }
  100% { transform: scale(1); opacity: 1; }
}

.slide-in-left {
  animation: slideInLeft 0.5s ease-out;
}

@keyframes slideInLeft {
  from { transform: translateX(-100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* ===== SUCCESS/ERROR ANIMATIONS ===== */
.success-animation {
  animation: successPulse 0.6s ease-out;
}

@keyframes successPulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); background-color: rgba(25, 135, 84, 0.2); }
  100% { transform: scale(1); }
}

.error-animation {
  animation: errorShake 0.5s ease-in-out;
}

@keyframes errorShake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-5px); }
  75% { transform: translateX(5px); }
}

/* ===== TOOLTIP ENHANCEMENTS ===== */
.tooltip-inner {
  background: var(--primary-gradient);
  font-size: 0.875rem;
  border-radius: 6px;
}

.tooltip.bs-tooltip-top .tooltip-arrow::before {
  border-top-color: #667eea;
}

.tooltip.bs-tooltip-bottom .tooltip-arrow::before {
  border-bottom-color: #667eea;
}

/* ===== FOCUS STATES ===== */
.btn:focus, .form-control:focus, .form-select:focus {
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
  border-color: #667eea;
}

/* ===== CUSTOM SCROLLBAR ===== */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: var(--primary-gradient);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}
</style>
</head>
<body>
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h5 class="text-white mb-2">Processing Request</h5>
        <p class="text-white-50 mb-0">Please wait while we process your request...</p>
    </div>
</div>
<div class="container">

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
    <div class="d-flex align-items-center">
        <a href="admin-dashboard.php" class="btn btn-outline-primary me-3 shadow-hover" title="Back to Admin Dashboard">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
        <h2 class="mb-0 gradient-text">
            <i class="fas fa-building me-3"></i>Departments & Programs Management
        </h2>
    </div>
    <div class="d-flex gap-2">
        <div class="badge bg-primary fs-6 px-3 py-2">
            <i class="fas fa-clock me-1"></i>Live Updates
        </div>
    </div>
</div>
<!-- Alert Messages -->
<div id="alertBox" class="mb-4"></div>


<!-- Main Content Container -->
<div class="row g-4">
    <!-- Left Column - Add/Edit Form -->
    <div class="col-lg-8">
        <!-- Department Form Card -->
        <div class="card shadow-lg border-0">
            <div class="card-header bg-gradient-primary text-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-20 rounded-circle p-2 me-3">
                            <i class="fas fa-plus-circle fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Department Management</h5>
                            <small>Create or modify department information</small>
                        </div>
                    </div>
                    <button type="button" id="cancelEdit" class="btn btn-light btn-sm d-none">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                </div>
            </div>

            <div class="card-body p-4">
                <form id="addDepartmentForm">
                    <input type="hidden" name="department_id" id="deptId">

                    <!-- Basic Information Card -->
                    <div class="card border-0 bg-light mb-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="fas fa-info-circle me-2"></i>Basic Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="deptName" class="form-label fw-semibold text-dark">
                                        <i class="fas fa-building me-1 text-primary"></i>Department Name
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-primary text-white">
                                            <i class="fas fa-university"></i>
                                        </span>
                                        <input type="text" name="department_name" id="deptName"
                                               class="form-control border-start-0"
                                               placeholder="Enter department name (e.g., Computer Science Department)"
                                               required>
                                    </div>
                                    <div class="form-text text-muted">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        Enter the full official name of the department
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="hodId" class="form-label fw-semibold text-dark">
                                        <i class="fas fa-user-tie me-1 text-primary"></i>Head of Department
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-success text-white">
                                            <i class="fas fa-user-check"></i>
                                        </span>
                                        <select name="hod_id" id="hodId" class="form-select border-start-0">
                                            <option value="">-- Select Head of Department --</option>
                                            <?php
                                            $hods=$pdo->query("SELECT id,username FROM users WHERE role='hod' ORDER BY username")->fetchAll();
                                            foreach($hods as $hod){ echo "<option value='{$hod['id']}'>{$hod['username']}</option>"; }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Optional: Assign a department head from available HoDs
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Programs Management Card -->
                    <div class="card border-0 bg-light mb-4">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="fas fa-graduation-cap me-2"></i>Programs Management
                            </h6>
                            <button type="button" id="addProgramRow" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Add Program
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Programs Table -->
                            <div class="table-responsive">
                                <table class="table table-hover" id="programsTable">
                                    <thead class="table-primary">
                                        <tr>
                                            <th class="border-0">#</th>
                                            <th class="border-0">Program Name</th>
                                            <th class="border-0">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="programsTableBody">
                                        <tr class="program-row">
                                            <td class="fw-bold text-primary align-middle">1</td>
                                            <td class="align-middle">
                                                <div class="input-group">
                                                    <span class="input-group-text bg-info text-white">
                                                        <i class="fas fa-book"></i>
                                                    </span>
                                                    <input type="text" name="programs[]" class="form-control border-start-0"
                                                           placeholder="e.g., Computer Science, Mathematics, Physics"
                                                           required>
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-program-row d-none">
                                                    <i class="fas fa-trash me-1"></i>Remove
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div id="emptyProgramsState" class="text-center py-4 d-none">
                                <div class="text-muted">
                                    <i class="fas fa-graduation-cap fa-3x mb-3 text-primary opacity-50"></i>
                                    <h6>No Programs Added Yet</h6>
                                    <p class="mb-0">Click "Add Program" to start adding programs for this department</p>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-end gap-3 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary px-4" id="resetFormBtn">
                            <i class="fas fa-undo me-2"></i>Reset Form
                        </button>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Save Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column - Quick Actions -->
    <div class="col-lg-4">
        <!-- Quick Actions Card -->
        <div class="card border-0 shadow">
            <div class="card-header bg-gradient-primary text-white">
                <h6 class="mb-0 fw-bold">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="#departmentsContainer" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i>View All Departments
                    </a>
                    <button class="btn btn-outline-success" onclick="loadDepartments()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Data
                    </button>
                    <a href="admin-dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Additional Info Card -->
        <div class="card border-0 shadow mt-4">
            <div class="card-header bg-gradient-info text-white">
                <h6 class="mb-0 fw-bold">
                    <i class="fas fa-info-circle me-2"></i>Help & Tips
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-primary mb-2">
                        <i class="fas fa-plus-circle me-1"></i>Adding Departments
                    </h6>
                    <small class="text-muted">
                        Fill in the department name and optionally assign a HoD. Add multiple programs using the table format.
                    </small>
                </div>
                <div class="mb-3">
                    <h6 class="text-success mb-2">
                        <i class="fas fa-edit me-1"></i>Managing Programs
                    </h6>
                    <small class="text-muted">
                        Use the program table to add, edit, or remove programs for each department.
                    </small>
                </div>
                <div class="mb-0">
                    <h6 class="text-info mb-2">
                        <i class="fas fa-search me-1"></i>Finding Departments
                    </h6>
                    <small class="text-muted">
                        Use the search bar to quickly find departments by name or HoD.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Row -->
<div class="row g-4 mb-5">
    <div class="col-lg-3 col-md-6">
        <div class="card stats-card h-100">
            <div class="card-body text-center position-relative">
                <div class="position-absolute top-0 end-0 p-2">
                    <i class="fas fa-chart-line text-white opacity-50"></i>
                </div>
                <i class="fas fa-university fa-3x text-primary mb-3"></i>
                <h3 id="totalDepartments" class="mb-2">0</h3>
                <p class="text-muted mb-0 fw-semibold">Total Departments</p>
                <small class="text-white-50">Academic Units</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stats-card h-100">
            <div class="card-body text-center position-relative">
                <div class="position-absolute top-0 end-0 p-2">
                    <i class="fas fa-graduation-cap text-white opacity-50"></i>
                </div>
                <i class="fas fa-graduation-cap fa-3x text-success mb-3"></i>
                <h3 id="totalPrograms" class="mb-2">0</h3>
                <p class="text-muted mb-0 fw-semibold">Total Programs</p>
                <small class="text-white-50">Course Offerings</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stats-card h-100">
            <div class="card-body text-center position-relative">
                <div class="position-absolute top-0 end-0 p-2">
                    <i class="fas fa-user-check text-white opacity-50"></i>
                </div>
                <i class="fas fa-user-tie fa-3x text-info mb-3"></i>
                <h3 id="assignedHods" class="mb-2">0</h3>
                <p class="text-muted mb-0 fw-semibold">Assigned HoDs</p>
                <small class="text-white-50">Department Heads</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stats-card h-100">
            <div class="card-body text-center position-relative">
                <div class="position-absolute top-0 end-0 p-2">
                    <i class="fas fa-exclamation-triangle text-white opacity-50"></i>
                </div>
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h3 id="unassignedHods" class="mb-2">0</h3>
                <p class="text-muted mb-0 fw-semibold">Unassigned</p>
                <small class="text-white-50">Needs Attention</small>
            </div>
        </div>
    </div>
</div>

<!-- Existing Departments Section -->
<div class="card shadow-lg border-0 mt-5" id="departmentsList">
    <div class="card-header bg-gradient-success text-white">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <div class="bg-white bg-opacity-20 rounded-circle p-2 me-3">
                    <i class="fas fa-list fa-lg"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold">Department Directory</h5>
                    <small>Manage and oversee all departments</small>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <button class="btn btn-light btn-sm" id="refreshBtn">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <div class="input-group" style="max-width: 250px;">
                    <span class="input-group-text bg-light">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control"
                           placeholder="Search departments..." aria-label="Search departments">
                    <button class="btn btn-outline-light border-start-0" type="button" id="clearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body p-4">

        <!-- Departments Container -->
        <div id="departmentsContainer">
            <div class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading departments...</span>
                </div>
                <h5 class="text-muted mb-2">Loading Department Data</h5>
                <p class="text-muted mb-0">Please wait while we fetch the latest information</p>
            </div>
        </div>
    </div>
</div>

<script>
$(function(){
  function showAlert(type,msg){
    $("#alertBox").html(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`);
    setTimeout(() => $("#alertBox").html(""), 5000);
  }

  function showLoading(){
    $("#loadingOverlay").fadeIn();
  }

  function hideLoading(){
    $("#loadingOverlay").fadeOut();
  }

  function renderDepartments(data, searchTerm = ""){
    let html = "";
    let filteredData = data;

    if(searchTerm){
      filteredData = data.filter(d =>
        d.dept_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (d.hod_name && d.hod_name.toLowerCase().includes(searchTerm.toLowerCase()))
      );
    }

    // Update filtered count
    $("#filteredCount").text(filteredData.length);

    if(filteredData.length === 0){
      if(searchTerm){
        html = `<div class="text-center py-5">
          <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
            <i class="fas fa-search fa-2x text-muted"></i>
          </div>
          <h5 class="text-muted fw-bold">No departments found</h5>
          <p class="text-muted">No departments match your search term "<strong>${searchTerm}</strong>".</p>
          <button class="btn btn-outline-primary shadow-hover" onclick="$('#searchInput').val(''); renderDepartments(allDepartments);">
            <i class="fas fa-times me-2"></i>Clear Search
          </button>
        </div>`;
      } else {
        html = `<div class="text-center py-5">
          <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
            <i class="fas fa-building fa-2x text-muted"></i>
          </div>
          <h5 class="text-muted fw-bold">No departments found</h5>
          <p class="text-muted">Add your first department using the form above.</p>
          <button class="btn btn-primary shadow-hover" onclick="$('html, body').animate({scrollTop: 0}, 500);">
            <i class="fas fa-plus me-2"></i>Add Department
          </button>
        </div>`;
      }
    } else {
      filteredData.forEach((d, index) => {
        const hodText = d.hod_name
          ? `<span class="badge bg-success"><i class="fas fa-user-tie me-1"></i>${d.hod_name}</span>`
          : '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i>Not Assigned</span>';

        const cardAnimationDelay = (index * 0.1) + 's';

        html += `<div class="card mb-4 department-card" data-dept-id="${d.dept_id}" style="animation-delay: ${cardAnimationDelay};">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-2">
                  <div class="bg-primary rounded-circle p-2 me-3">
                    <i class="fas fa-university text-white"></i>
                  </div>
                  <div>
                    <h5 class="mb-1 text-primary">${d.dept_name}</h5>
                    <small class="text-muted">Department ID: ${d.dept_id}</small>
                  </div>
                </div>
                <div class="mt-2">
                  <strong class="text-muted">Head of Department:</strong> ${hodText}
                </div>
              </div>
              <div class="btn-group ms-3" role="group">
                <button class="btn btn-sm btn-outline-primary editDept shadow-hover"
                        data-id="${d.dept_id}"
                        data-name="${d.dept_name}"
                        data-hod="${d.hod_id ?? ''}"
                        title="Edit Department">
                  <i class="fas fa-edit me-1"></i>Edit
                </button>
                <button class="btn btn-sm btn-outline-danger deleteDept shadow-hover"
                        data-id="${d.dept_id}"
                        title="Delete Department">
                  <i class="fas fa-trash me-1"></i>Delete
                </button>
              </div>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="text-success mb-0">
                  <i class="fas fa-graduation-cap me-2"></i>Programs
                </h6>
                <span class="badge bg-success">${d.programs.length} Programs</span>
              </div>
              <ul class="program-list">`;
        if(d.programs.length === 0){
          html += `<li class="text-muted">
            <i class="fas fa-info-circle me-2"></i>No programs added yet
            <small class="d-block text-muted mt-1">Use the form below to add programs</small>
          </li>`;
        } else {
          d.programs.forEach(p => {
            html += `<li class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <i class="fas fa-book me-2 text-primary"></i>
                <span class="fw-medium">${p.name}</span>
                <small class="text-muted ms-2">ID: ${p.id}</small>
              </div>
              <button class="btn btn-outline-danger btn-sm deleteProgram shadow-hover"
                      data-id="${p.id}"
                      title="Delete Program">
                <i class="fas fa-times"></i>
              </button>
            </li>`;
          });
        }
        html += `</ul>
            </div>

            <div class="border-top pt-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted fw-semibold">Add New Program</small>
                <small class="text-muted">${d.programs.length} existing</small>
              </div>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-plus text-success"></i></span>
                <input type="text" class="form-control form-control-sm addProgramInput"
                       placeholder="Enter new program name (e.g., Computer Science)"
                       aria-label="New program name">
                <button class="btn btn-success btn-sm addProgramBtn shadow-hover"
                        data-id="${d.dept_id}"
                        title="Add Program">
                  <i class="fas fa-plus me-1"></i>Add Program
                </button>
              </div>
            </div>
          </div>
        </div>`;
      });
    }
    $("#departmentsContainer").html(html);
  }

  function updateStatistics(data){
    const totalDepartments = data.length;
    let totalPrograms = 0;
    let assignedHods = 0;

    data.forEach(dept => {
      totalPrograms += dept.programs.length;
      if(dept.hod_name) assignedHods++;
    });

    const unassignedHods = totalDepartments - assignedHods;

    // Animate counter updates
    animateCounter("#totalDepartments", totalDepartments);
    animateCounter("#totalPrograms", totalPrograms);
    animateCounter("#assignedHods", assignedHods);
    animateCounter("#unassignedHods", unassignedHods);

    // Update list stats
    $("#listTotalDepts").text(totalDepartments);
    $("#listTotalProgs").text(totalPrograms);
    $("#listAssignedHods").text(assignedHods);
  }

  function animateCounter(selector, targetValue) {
    const element = $(selector);
    const currentValue = parseInt(element.text()) || 0;
    $({value: currentValue}).animate({value: targetValue}, {
      duration: 800,
      easing: 'swing',
      step: function(now) {
        element.text(Math.floor(now));
      },
      complete: function() {
        element.text(targetValue);
      }
    });
  }

  function loadDepartments(){
    showLoading();
    $.get("?ajax=1&action=list_departments",function(data){
      hideLoading();
      allDepartments = data;
      updateStatistics(data);
      renderDepartments(data);
    },"json").fail(function(){
      hideLoading();
      showAlert("danger", "Failed to load departments. Please try again.");
      $("#departmentsContainer").html(`<div class="text-center py-5">
        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
        <h5 class="text-danger">Error Loading Departments</h5>
        <p class="text-muted">Please check your connection and try again.</p>
        <button class="btn btn-primary" onclick="loadDepartments()">Retry</button>
      </div>`);
    });
  }
  loadDepartments();

  $("#addDepartmentForm").submit(function(e){
    e.preventDefault();
    showLoading();
    let action = $("#deptId").val() ? "edit_department" : "add_department";
    let submitBtn = $(this).find('button[type="submit"]');
    let originalText = submitBtn.html();
    submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...').prop('disabled', true);

    $.post("?ajax=1&action=" + action, $(this).serialize(), function(res){
      hideLoading();
      submitBtn.html(originalText).prop('disabled', false);
      if(res.status === "success"){
        showAlert("success", res.message);
        $("#addDepartmentForm")[0].reset();
        $("#deptId").val("");
        $("#cancelEdit").addClass("d-none");
        loadDepartments();
      } else {
        showAlert("danger", res.message);
      }
    },"json").fail(function(){
      hideLoading();
      submitBtn.html(originalText).prop('disabled', false);
      showAlert("danger", "Failed to save department. Please try again.");
    });
  });

  // Add Program Row to Table
  $("#addProgramRow").click(function(){
    const rowCount = $("#programsTableBody .program-row").length + 1;
    const programRowHtml = `
        <tr class="program-row">
            <td class="ps-4 py-3 fw-bold text-primary">${rowCount}</td>
            <td class="py-3">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white">
                        <i class="fas fa-book"></i>
                    </span>
                    <input type="text" name="programs[]" class="form-control border-start-0"
                           placeholder="Enter program name (e.g., Computer Science, Mathematics)"
                           required>
                </div>
            </td>
            <td class="py-3">
                <button type="button" class="btn btn-outline-danger btn-sm remove-program-row"
                        title="Remove Program Row">
                    <i class="fas fa-trash me-1"></i>Remove
                </button>
            </td>
        </tr>`;

    $("#programsTableBody").append(programRowHtml);
    updateRowNumbers();
    toggleEmptyState();
  });

  // Remove Program Row from Table
  $(document).on("click", ".remove-program-row", function(){
    $(this).closest(".program-row").remove();
    updateRowNumbers();
    toggleEmptyState();
  });

  // Update row numbers after adding/removing rows
  function updateRowNumbers(){
    $("#programsTableBody .program-row").each(function(index){
      $(this).find("td:first").text(index + 1);
    });
  }

  // Toggle empty state visibility
  function toggleEmptyState(){
    const rowCount = $("#programsTableBody .program-row").length;
    if(rowCount === 0){
      $("#emptyProgramsState").removeClass("d-none");
      $("#programsTable").addClass("d-none");
    } else {
      $("#emptyProgramsState").addClass("d-none");
      $("#programsTable").removeClass("d-none");
    }
  }

  $(document).on("click",".addProgramBtn",function(){
    let id = $(this).data("id");
    let name = $(this).siblings(".addProgramInput").val().trim();
    if(!name) {
      showAlert("warning", "Please enter a program name.");
      return;
    }
    showLoading();
    let btn = $(this);
    let originalText = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin me-1"></i>').prop('disabled', true);

    $.post("?ajax=1&action=add_program", {department_id: id, program_name: name}, function(res){
      hideLoading();
      btn.html(originalText).prop('disabled', false);
      if(res.status === "success"){
        showAlert("success", res.message);
        loadDepartments();
      } else {
        showAlert("danger", res.message);
      }
    },"json").fail(function(){
      hideLoading();
      btn.html(originalText).prop('disabled', false);
      showAlert("danger", "Failed to add program. Please try again.");
    });
  });

  $(document).on("click",".deleteDept",function(){
    if(!confirm("Are you sure you want to delete this department and all its programs? This action cannot be undone.")) return;
    let id = $(this).data("id");
    showLoading();
    $.post("?ajax=1&action=delete_department", {department_id: id}, function(res){
      hideLoading();
      if(res.status === "success"){
        showAlert("success", res.message);
        loadDepartments();
      } else {
        showAlert("danger", res.message);
      }
    },"json").fail(function(){
      hideLoading();
      showAlert("danger", "Failed to delete department. Please try again.");
    });
  });

  $(document).on("click",".deleteProgram",function(){
    if(!confirm("Are you sure you want to delete this program?")) return;
    let id = $(this).data("id");
    showLoading();
    $.post("?ajax=1&action=delete_program", {program_id: id}, function(res){
      hideLoading();
      if(res.status === "success"){
        showAlert("success", res.message);
        loadDepartments();
      } else {
        showAlert("danger", res.message);
      }
    },"json").fail(function(){
      hideLoading();
      showAlert("danger", "Failed to delete program. Please try again.");
    });
  });
  $(document).on("click",".editDept",function(){
    const deptId = $(this).data("id");
    const deptName = $(this).data("name");
    const hodId = $(this).data("hod");

    // Populate basic fields
    $("#deptId").val(deptId);
    $("#deptName").val(deptName);
    $("#hodId").val(hodId);

    // Clear existing programs table and load department programs
    $("#programsTableBody").empty();
    $.get("?ajax=1&action=list_departments", function(data){
      const department = data.find(d => d.dept_id == deptId);
      if(department && department.programs.length > 0){
        department.programs.forEach(function(program, index){
          const programRowHtml = `
            <tr class="program-row">
                <td class="ps-4 py-3 fw-bold text-primary">${index + 1}</td>
                <td class="py-3">
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white">
                            <i class="fas fa-book"></i>
                        </span>
                        <input type="text" name="programs[]" class="form-control border-start-0"
                               placeholder="Enter program name" value="${program.name}" required>
                    </div>
                </td>
                <td class="py-3">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-program-row ${department.programs.length > 1 ? '' : 'd-none'}"
                            title="Remove Program Row">
                        <i class="fas fa-trash me-1"></i>Remove
                    </button>
                </td>
            </tr>`;
          $("#programsTableBody").append(programRowHtml);
        });
      } else {
        // Add empty program row if none exist
        const emptyRowHtml = `
            <tr class="program-row">
                <td class="ps-4 py-3 fw-bold text-primary">1</td>
                <td class="py-3">
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white">
                            <i class="fas fa-book"></i>
                        </span>
                        <input type="text" name="programs[]" class="form-control border-start-0"
                               placeholder="Enter program name (e.g., Computer Science, Mathematics)" required>
                    </div>
                </td>
                <td class="py-3">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-program-row d-none"
                            title="Remove Program Row">
                        <i class="fas fa-trash me-1"></i>Remove
                    </button>
                </td>
            </tr>`;
        $("#programsTableBody").append(emptyRowHtml);
      }
      toggleEmptyState();
    });

    $("#cancelEdit").removeClass("d-none");
    $('html,body').animate({scrollTop:0},300);
  });
  $("#cancelEdit").click(function(){
    $("#addDepartmentForm")[0].reset();
    $("#deptId").val("");
    $(this).addClass("d-none");

    // Reset programs table to single empty row
    $("#programsTableBody").html(`
        <tr class="program-row">
            <td class="ps-4 py-3 fw-bold text-primary">1</td>
            <td class="py-3">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white">
                        <i class="fas fa-book"></i>
                    </span>
                    <input type="text" name="programs[]" class="form-control border-start-0"
                           placeholder="Enter program name (e.g., Computer Science, Mathematics)" required>
                </div>
            </td>
            <td class="py-3">
                <button type="button" class="btn btn-outline-danger btn-sm remove-program-row d-none"
                        title="Remove Program Row">
                    <i class="fas fa-trash me-1"></i>Remove
                </button>
            </td>
        </tr>`);
    toggleEmptyState();
  });

  // Search functionality
  let allDepartments = [];
  function filterDepartments(){
    const searchTerm = $("#searchInput").val().trim();
    renderDepartments(allDepartments, searchTerm);
  }

  $("#searchInput").on("input", filterDepartments);
  $("#clearSearch").click(function(){
    $("#searchInput").val("");
    filterDepartments();
  });

  // Reset Form Button
  $("#resetFormBtn").click(function(){
    $("#addDepartmentForm")[0].reset();
    $("#deptId").val("");
    $("#cancelEdit").addClass("d-none");

    // Reset programs table to single empty row
    $("#programsTableBody").html(`
        <tr class="program-row">
            <td class="ps-4 py-3 fw-bold text-primary">1</td>
            <td class="py-3">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white">
                        <i class="fas fa-book"></i>
                    </span>
                    <input type="text" name="programs[]" class="form-control border-start-0"
                           placeholder="Enter program name (e.g., Computer Science, Mathematics)" required>
                </div>
            </td>
            <td class="py-3">
                <button type="button" class="btn btn-outline-danger btn-sm remove-program-row d-none"
                        title="Remove Program Row">
                    <i class="fas fa-trash me-1"></i>Remove
                </button>
            </td>
        </tr>`);
    toggleEmptyState();
  });

  $("#refreshBtn").click(function(){
    const btn = $(this);
    const originalHtml = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...').prop('disabled', true);
    loadDepartments();
    setTimeout(() => {
      btn.html(originalHtml).prop('disabled', false);
    }, 1000);
  });
});
</script>
</body>
</html>
