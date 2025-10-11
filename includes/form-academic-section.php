<!-- Academic Information Section -->
<div class="col-md-6">

    <div class="mb-3">
        <label for="reg_no" class="form-label required-field">
            Registration Number <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" id="reg_no" name="reg_no"
               required maxlength="20" aria-required="true" aria-label="Registration Number"
               pattern="[A-Za-z0-9_-]{5,20}">
        <div class="invalid-feedback">Registration number must be 5-20 alphanumeric characters.</div>
        <div class="form-text">Example: 22RP06557</div>
    </div>

    <div class="mb-3">
        <label for="studentIdNumber" class="form-label">Student ID Number</label>
        <input type="text" class="form-control" id="studentIdNumber" name="student_id_number"
               maxlength="16" pattern="\d{16}" aria-label="Student ID Number">
        <div class="invalid-feedback">Student ID must be exactly 16 digits.</div>
        <div class="form-text">Optional: 16-digit student ID number</div>
    </div>

    <!-- Department Selection -->
    <div class="mb-4">
        <label for="department" class="form-label d-flex align-items-center justify-content-between required-field">
            <span class="d-flex align-items-center">
                <i class="fas fa-building me-2 text-primary fs-5"></i>
                <strong>Academic Department</strong>
            </span>
            <span class="badge bg-primary rounded-pill">
                <i class="fas fa-star me-1"></i>Required
            </span>
        </label>
        <div class="input-group input-group-lg shadow-sm">
            <span class="input-group-text bg-primary text-white border-primary">
                <i class="fas fa-university fa-lg"></i>
            </span>
            <select class="form-select form-select-lg border-primary" id="department" name="department_id"
                    required aria-required="true" aria-label="Department"
                    aria-describedby="departmentHelp">
                <option value="">🎓 Select Your Academic Department</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['id']) ?>">
                        📚 <?= htmlspecialchars($dept['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="input-group-text bg-light">
                <i class="fas fa-chevron-down text-muted"></i>
            </span>
        </div>
        <div class="form-text mt-2" id="departmentHelp">
            <div class="d-flex align-items-center">
                <i class="fas fa-lightbulb text-warning me-2"></i>
                <small class="text-muted fw-medium">
                    Choose your academic department to unlock available programs and specializations
                </small>
            </div>
        </div>
    </div>

    <!-- Program Selection -->
    <div class="mb-4">
        <label for="option" class="form-label d-flex align-items-center justify-content-between required-field">
            <span class="d-flex align-items-center">
                <i class="fas fa-graduation-cap me-2 text-success fs-5"></i>
                <strong>Program/Specialization</strong>
            </span>
            <span class="badge bg-success rounded-pill">
                <i class="fas fa-star me-1"></i>Required
            </span>
        </label>
        <div class="input-group input-group-lg shadow-sm">
            <span class="input-group-text bg-success text-white border-success">
                <i class="fas fa-book-open fa-lg"></i>
            </span>
            <select class="form-select form-select-lg border-success" id="option" name="option_id"
                    required aria-required="true" aria-label="Program"
                    aria-describedby="programHelp">
                <option value="">📚 Loading programs from database...</option>
                <?php
                // Load all programs initially from database
                try {
                    $pdo = new PDO("mysql:host=localhost;dbname=rp_attendance_system;charset=utf8mb4", "root", "");
                    $stmt = $pdo->query("
                        SELECT o.id, o.name, d.name as dept_name
                        FROM options o
                        JOIN departments d ON o.department_id = d.id
                        WHERE o.status = 'active'
                        ORDER BY d.name, o.name
                    ");
                    $allPrograms = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($allPrograms as $program) {
                        echo "<option value=\"{$program['id']}\" data-department=\"{$program['dept_name']}\">";
                        echo "🎓 {$program['name']} ({$program['dept_name']})";
                        echo "</option>";
                    }
                } catch (Exception $e) {
                    echo "<option value=\"\">❌ Error loading programs</option>";
                }
                ?>
            </select>
            <div class="spinner-border spinner-border-sm text-success d-none program-loading ms-2"
                 role="status" aria-hidden="true">
                <span class="visually-hidden">Loading programs...</span>
            </div>
            <span class="input-group-text bg-light d-none" id="programLoadedIcon">
                <i class="fas fa-check-circle text-success"></i>
            </span>
        </div>
        <div class="form-text mt-2" id="programHelp">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle text-info me-2"></i>
                <small class="text-muted fw-medium">
                    Select your academic department above to filter available programs
                </small>
            </div>
        </div>
        <div class="mt-3">
            <div id="programCount" class="alert alert-info d-none py-2 px-3 border-0"
                 style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                <div class="d-flex align-items-center">
                    <i class="fas fa-chart-line text-info me-2 fs-5"></i>
                    <div>
                        <strong class="text-info">Program Options Available</strong><br>
                        <small id="programCountText" class="text-info-emphasis fw-medium"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label for="year_level" class="form-label required-field">
            Year Level <span class="text-danger">*</span>
        </label>
        <select class="form-control" id="year_level" name="year_level"
                required aria-required="true" aria-label="Year Level">
            <option value="">Select Year Level</option>
            <option value="1">Year 1</option>
            <option value="2">Year 2</option>
            <option value="3">Year 3</option>
        </select>
        <div class="invalid-feedback">Please select a year level.</div>
    </div>
</div>