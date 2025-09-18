<?php
error_reporting(0);
session_start();
require_once "config.php";
require_once "session_check.php"; // Ensure only logged-in admins can access
require_role(['admin']);

// Handle AJAX request for options based on department
if(isset($_POST['get_options'])){
    $dep_id = $_POST['dep_id'];
    $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ?");
    $stmt->execute([$dep_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<option selected disabled>-- Select Option --</option>';
    foreach($options as $opt){
        echo '<option value="'.$opt['id'].'">'.htmlspecialchars($opt['name']).'</option>';
    }
    exit;
}

// AJAX duplicate check
if(isset($_POST['check_duplicate'])){
    $field = $_POST['field'];
    $value = $_POST['value'];

    if(!in_array($field, ['email','reg_no','telephone'])){
        echo 'Invalid field'; exit;
    }

    if($field == 'email'){
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=?");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE $field=?");
    }

    $stmt->execute([$value]);
    $count = $stmt->fetchColumn();

    echo $count > 0 ? '1' : '0';
    exit;
}

// Handle student registration
if(isset($_POST['register_student'])){
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $reg_no = $_POST['reg_no'];
    $department_id = $_POST['department_id'];
    $option_id = $_POST['option_id'];
    $telephone = $_POST['telephone'];
    $year_level = $_POST['year_level'];
    $sex = $_POST['sex'];
    $password = '12345'; // default password

    // Handle photo upload
    $photo_path = '';
    if(isset($_FILES['photo']) && $_FILES['photo']['tmp_name']){
        $photo = $_FILES['photo']['name'];
        $photo_tmp = $_FILES['photo']['tmp_name'];
        $photo_path = "uploads/".time().'_'.$photo;
        move_uploaded_file($photo_tmp, $photo_path);
    } elseif(isset($_POST['camera_photo']) && $_POST['camera_photo'] != ''){
        // Photo captured from camera (base64)
        $data = $_POST['camera_photo'];
        list($type, $data) = explode(';', $data);
        list(, $data) = explode(',', $data);
        $data = base64_decode($data);
        $photo_path = "uploads/".time()."_camera.png";
        file_put_contents($photo_path, $data);
    }

    // Fingerprint handling (placeholder)
    $fingerprint = ""; 

    try{
        // First insert user
        $stmt = $pdo->prepare("INSERT INTO users (username,email,password,role,created_at) VALUES (?,?,?,?,NOW())");
        $stmt->execute([$first_name.' '.$last_name,$email,$password,'student']);
        $user_id = $pdo->lastInsertId();

        // Insert into students table
        $stmt = $pdo->prepare("INSERT INTO students (user_id, option_id, year_level, first_name, last_name, email, reg_no, department_id, telephone, sex, photo, fingerprint, password) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$user_id, $option_id, $year_level, $first_name, $last_name, $email, $reg_no, $department_id, $telephone, $sex, $photo_path, $fingerprint, $password]);

        $success = "Student registered successfully!";
    } catch(Exception $e){
        $error = "Error: ".$e->getMessage();
    }
}

// Fetch departments for dropdown
$stmt = $pdo->query("SELECT id, name FROM departments");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Register Student | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
body {font-family: 'Segoe UI', sans-serif; background: #eef2f7; margin:0;}
.sidebar {position: fixed; top:0; left:0; width:250px; height:100vh; background: linear-gradient(180deg, #003366 0%, #004080 100%); color:white; padding-top:20px; box-shadow:2px 0 10px rgba(0,0,0,0.1);}
.sidebar a {display:block; padding:14px 20px; color:#fff; text-decoration:none; font-weight:500; border-radius:6px; margin:5px 10px; transition:all 0.3s ease;}
.sidebar a:hover {background-color:#0066cc; padding-left:30px;}
.sidebar h4 {font-weight:bold; margin-bottom:10px;}
.topbar {margin-left:250px; background-color:#ffffff; padding:15px 30px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:900; box-shadow:0 2px 6px rgba(0,0,0,0.05);}
.main-content {margin-left:250px; padding:40px 30px;}
.card {border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,0.08); background-color:#ffffff; padding:30px; transition: transform 0.3s ease, box-shadow 0.3s ease;}
.card:hover {transform: translateY(-5px); box-shadow:0 12px 30px rgba(0,0,0,0.12);}
.form-label {font-weight:500; color:#333;}
input.form-control, select.form-select {border-radius:10px; border:1px solid #ccc; padding:10px; transition:border 0.3s ease, box-shadow 0.3s ease;}
input.form-control:focus, select.form-select:focus {border-color:#0066cc; box-shadow:0 0 6px rgba(0,102,204,0.3); outline:none;}
.btn-primary {background-color:#0066cc; border-radius:10px; font-weight:500; padding:10px 0; transition: background 0.3s ease, transform 0.2s ease;}
.btn-primary:hover {background-color:#004b99; transform:scale(1.02);}
.footer {text-align:center; margin-left:250px; padding:15px; font-size:0.9rem; color:#666; background-color:#f0f0f0;}
#camera_area {border:1px dashed #ccc; padding:10px; display:none; text-align:center; margin-bottom:10px;}
@media (max-width:768px){.sidebar,.topbar,.main-content,.footer{margin-left:0 !important;width:100%;}.sidebar{display:none;}}
</style>
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-4"><h4>👩‍💼 Admin</h4><hr style="border-color:#ffffff66;"></div>
    <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
    <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
    <a href="admin-reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
    <a href="index.html"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar">
    <h5 class="m-0 fw-bold">Register Student</h5>
    <span>Admin Panel</span>
</div>

<div class="main-content">
    <div class="card">
        <h4 class="mb-4 fw-bold text-primary"><i class="fas fa-user-plus me-2"></i> Student Registration Form</h4>

        <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" placeholder="Enter first name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" placeholder="Enter last name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter email" required>
                    <small id="email_feedback"></small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Registration Number</label>
                    <input type="text" name="reg_no" id="reg_no" class="form-control" placeholder="e.g. 22RP0000" required>
                    <small id="regno_feedback"></small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <select name="department_id" id="department" class="form-select" required>
                        <option selected disabled>-- Select Department --</option>
                        <?php foreach($departments as $dep): ?>
                            <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Option / Program</label>
                    <select name="option_id" id="option" class="form-select" required>
                        <option selected disabled>-- Select Option --</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telephone</label>
                    <input type="text" name="telephone" id="telephone" class="form-control" placeholder="Enter phone number" required>
                    <small id="telephone_feedback"></small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Year / Level</label>
                    <select name="year_level" class="form-select" required>
                        <option selected disabled>-- Select Year Level --</option>
                        <option value="1">Year 1</option>
                        <option value="2">Year 2</option>
                        <option value="3">Year 3</option>
                        <option value="4">Year 4</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sex</label>
                    <select name="sex" class="form-select" required>
                        <option selected disabled>-- Select Sex --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <!-- Photo Upload or Camera -->
                <div class="col-md-6">
                    <label class="form-label">Photo</label>
                    <input type="file" name="photo" accept="image/*" class="form-control">
                    <button type="button" class="btn btn-outline-primary mt-2" id="use_camera_btn"><i class="fas fa-camera me-2"></i> Use Camera</button>
                </div>
                <div class="col-md-6" id="camera_area">
                    <video id="video" width="100%" autoplay></video><br>
                    <button type="button" class="btn btn-success mt-2" id="capture_btn">Capture Photo</button>
                    <input type="hidden" name="camera_photo" id="camera_photo">
                </div>

                <!-- Fingerprint -->
                <div class="col-md-6">
                    <label class="form-label d-block">Fingerprint Capture</label>
                    <button type="button" class="btn btn-outline-secondary w-100"><i class="fas fa-fingerprint me-2"></i> Capture Fingerprint</button>
                </div>

                <div class="col-md-6">
                    <label class="form-label d-block invisible">Register</label>
                    <button type="submit" name="register_student" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i> Register Student</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Admin Panel</div>

<?php if(isset($success)): ?><script>alert("<?= addslashes($success) ?>");</script><?php endif; ?>

<script>
$(document).ready(function(){
    // Department -> Option
    $('#department').change(function(){
        var depId = $(this).val();
        if(depId){
            $.post('', {get_options:1, dep_id:depId}, function(data){
                $('#option').html(data);
            });
        } else {
            $('#option').html('<option selected disabled>-- Select Option --</option>');
        }
    });

    // Duplicate checks
    function checkDuplicate(field, value, feedbackId){
        if(value.length === 0) { $('#'+feedbackId).text(''); return; }
        $.post('', {check_duplicate:1, field:field, value:value}, function(data){
            if(data == '1'){ $('#'+feedbackId).text(field.replace('_',' ') + ' already exists!').css('color','red'); }
            else { $('#'+feedbackId).text(''); }
        });
    }
    $('#email').on('blur', function(){ checkDuplicate('email', $(this).val(), 'email_feedback'); });
    $('#reg_no').on('blur', function(){ checkDuplicate('reg_no', $(this).val(), 'regno_feedback'); });
    $('#telephone').on('blur', function(){ checkDuplicate('telephone', $(this).val(), 'telephone_feedback'); });

    // Camera
    let video = document.getElementById('video');
    let cameraArea = $('#camera_area');
    let cameraPhoto = $('#camera_photo');
    let stream;

    $('#use_camera_btn').click(function(){
        cameraArea.show();
        navigator.mediaDevices.getUserMedia({ video: true }).then(s => { stream = s; video.srcObject = stream; });
    });

    $('#capture_btn').click(function(){
        let canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video,0,0);
        let dataURL = canvas.toDataURL('image/png');
        cameraPhoto.val(dataURL);
        video.pause(); stream.getTracks()[0].stop();
        cameraArea.hide();
        alert('Photo captured!');
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
