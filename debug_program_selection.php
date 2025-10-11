<?php
/**
 * Debug script for program selection issue
 */

require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Program Selection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Debug: Program Selection Issue</h1>

        <div class="row">
            <div class="col-md-6">
                <h3>Departments in Form</h3>
                <select id="debugDepartment" class="form-select">
                    <option value="">Select Department</option>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
                        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($departments as $dept) {
                            echo "<option value='{$dept['id']}'>{$dept['name']} (ID: {$dept['id']})</option>";
                        }
                    } catch (Exception $e) {
                        echo "<option value=''>Error loading departments: " . $e->getMessage() . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-6">
                <h3>Programs for Selected Department</h3>
                <select id="debugProgram" class="form-select" disabled>
                    <option value="">Select Department First</option>
                </select>
                <div id="debugOutput" class="mt-3 p-3 bg-light rounded"></div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <h3>Database Status</h3>
                <div class="alert alert-info">
                    <?php
                    try {
                        $deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
                        $optionCount = $pdo->query("SELECT COUNT(*) FROM options")->fetchColumn();

                        echo "<strong>Departments:</strong> $deptCount<br>";
                        echo "<strong>Options:</strong> $optionCount<br>";

                        $mapping = $pdo->query("
                            SELECT d.name, COUNT(o.id) as count
                            FROM departments d
                            LEFT JOIN options o ON d.id = o.department_id
                            GROUP BY d.id, d.name
                            ORDER BY d.name
                        ")->fetchAll(PDO::FETCH_ASSOC);

                        echo "<strong>Department-Program Mapping:</strong><br>";
                        foreach ($mapping as $map) {
                            echo "- {$map['name']}: {$map['count']} programs<br>";
                        }
                    } catch (Exception $e) {
                        echo "Database error: " . $e->getMessage();
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#debugDepartment').on('change', function() {
                const deptId = $(this).val();
                const $program = $('#debugProgram');
                const $output = $('#debugOutput');

                $output.html('<div class="text-info">Loading programs for department ID: ' + deptId + '...</div>');

                if (!deptId) {
                    $program.html('<option value="">Select Department First</option>').prop('disabled', true);
                    $output.html('');
                    return;
                }

                // Make API call
                $.ajax({
                    url: 'api/department-option-api.php',
                    method: 'POST',
                    data: {
                        action: 'get_options',
                        department_id: deptId,
                        csrf_token: 'debug'
                    },
                    dataType: 'json',
                    success: function(response) {
                        $output.html('<pre class="text-success">' + JSON.stringify(response, null, 2) + '</pre>');

                        if (response.success && response.data) {
                            let options = '<option value="">Select Program</option>';
                            response.data.forEach(function(program) {
                                options += `<option value="${program.id}">${program.name}</option>`;
                            });
                            $program.html(options).prop('disabled', false);
                        } else {
                            $program.html('<option value="">No programs found</option>').prop('disabled', true);
                        }
                    },
                    error: function(xhr, status, error) {
                        $output.html('<div class="text-danger">AJAX Error: ' + error + '<br>Status: ' + xhr.status + '<br>Response: ' + xhr.responseText + '</div>');
                        $program.html('<option value="">Error loading programs</option>').prop('disabled', true);
                    }
                });
            });
        });
    </script>
</body>
</html>