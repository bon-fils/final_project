<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Departments | RP Attendance System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #003366;
            color: white;
            padding-top: 20px;
        }

        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #fff;
            text-decoration: none;
        }

        .sidebar a:hover {
            background-color: #0059b3;
        }

        .topbar {
            margin-left: 250px;
            background-color: #fff;
            padding: 10px 30px;
            border-bottom: 1px solid #ddd;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background-color: #0066cc;
            border: none;
        }

        .btn-primary:hover {
            background-color: #004b99;
        }

        .footer {
            text-align: center;
            margin-left: 250px;
            padding: 15px;
            font-size: 0.9rem;
            color: #666;
            background-color: #f0f0f0;
        }

        @media (max-width: 768px) {

            .sidebar,
            .topbar,
            .main-content,
            .footer {
                margin-left: 0 !important;
                width: 100%;
            }

            .sidebar {
                display: none;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h4>👩‍💼 Admin</h4>
            <hr style="border-color: #ffffff66;">
        </div>
        <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
        <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
        <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
        <a href="admin-reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
        <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <!-- Topbar -->
    <div class="topbar d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold">Manage Departments & Programs</h5>
        <span>Admin Panel</span>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="card p-4">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="fw-semibold">Departments</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                    <i class="fas fa-plus me-1"></i> Add Department
                </button>
            </div>

            <table class="table table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Department Name</th>
                        <th>Programs</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Sample department row -->
                    <tr>
                        <td>1</td>
                        <td>Computer Engineering</td>
                        <td>
                            <ul class="mb-0">
                                <li>Software Engineering</li>
                                <li>Networking</li>
                            </ul>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning me-1"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                    <!-- More departments will be dynamically inserted here -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDeptModal" tabindex="-1" aria-labelledby="addDeptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDeptModalLabel">Add New Department & Programs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Department Name -->
                    <div class="mb-3">
                        <label class="form-label">Department Name</label>
                        <input type="text" class="form-control" placeholder="e.g., Electrical Engineering" required>
                    </div>

                    <!-- Programs -->
                    <div class="mb-2">
                        <label class="form-label">Programs / Options</label>
                        <div id="programList">
                            <div class="input-group mb-2">
                                <input type="text" name="programs[]" class="form-control" placeholder="e.g., Power Systems" required />
                                <button type="button" class="btn btn-outline-danger remove-program">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" id="addProgramBtn" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> Add More Program
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Admin Panel
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Program Add/Remove Script -->
    <script>
        document.getElementById('addProgramBtn').addEventListener('click', function() {
            const programInput = document.createElement('div');
            programInput.classList.add('input-group', 'mb-2');
            programInput.innerHTML = `
        <input type="text" name="programs[]" class="form-control" placeholder="e.g., Control Systems" required />
        <button type="button" class="btn btn-outline-danger remove-program">
          <i class="fas fa-times"></i>
        </button>
      `;
            document.getElementById('programList').appendChild(programInput);
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-program')) {
                e.target.closest('.input-group').remove();
            }
        });
    </script>
</body>

</html>