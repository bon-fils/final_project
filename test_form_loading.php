<?php
/**
 * Test form loading with programs
 */

require_once 'config.php';
require_once 'includes/registration-data.php';

$departments = getDepartments($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Form Loading</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Test: Form Loading with Programs</h1>

        <div class="row">
            <div class="col-md-6">
                <h3>Departments Loaded: <?php echo count($departments); ?></h3>
                <select class="form-select" id="testDepartment">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>">📚 <?= htmlspecialchars($dept['name']) ?> (ID: <?= $dept['id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <h3>Programs Loaded from Database</h3>
                <select class="form-select" id="testProgram">
                    <option value="">📚 Loading programs from database...</option>
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT o.id, o.name, d.name as dept_name
                            FROM options o
                            JOIN departments d ON o.department_id = d.id
                            WHERE o.status = 'active'
                            ORDER BY d.name, o.name
                        ");
                        $allPrograms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $programCount = count($allPrograms);

                        echo "<!-- Total programs loaded: {$programCount} -->\n";

                        foreach ($allPrograms as $program) {
                            echo "<option value=\"{$program['id']}\" data-department=\"{$program['dept_name']}\">";
                            echo "🎓 {$program['name']} ({$program['dept_name']})";
                            echo "</option>\n";
                        }
                    } catch (Exception $e) {
                        echo "<option value=\"\">❌ Error: " . htmlspecialchars($e->getMessage()) . "</option>";
                    }
                    ?>
                </select>
                <div class="mt-2">
                    <small class="text-muted">
                        Programs loaded: <strong><?php echo $programCount ?? 0; ?></strong>
                    </small>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <h4>Database Status:</h4>
                    <?php
                    try {
                        $deptCount = $pdo->query("SELECT COUNT(*) FROM departments WHERE status = 'active'")->fetchColumn();
                        $optionCount = $pdo->query("SELECT COUNT(*) FROM options WHERE status = 'active'")->fetchColumn();

                        echo "<p><strong>Active Departments:</strong> $deptCount</p>";
                        echo "<p><strong>Active Programs:</strong> $optionCount</p>";

                        $mapping = $pdo->query("
                            SELECT d.name, COUNT(o.id) as count
                            FROM departments d
                            LEFT JOIN options o ON d.id = o.department_id AND o.status = 'active'
                            WHERE d.status = 'active'
                            GROUP BY d.id, d.name
                            ORDER BY d.name
                        ")->fetchAll(PDO::FETCH_ASSOC);

                        echo "<p><strong>Department-Program Mapping:</strong></p><ul>";
                        foreach ($mapping as $map) {
                            echo "<li>{$map['name']}: {$map['count']} programs</li>";
                        }
                        echo "</ul>";
                    } catch (Exception $e) {
                        echo "<p class='text-danger'>Database error: " . $e->getMessage() . "</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#testDepartment').on('change', function() {
                const deptId = $(this).val();
                const deptName = $(this).find('option:selected').text().replace('📚 ', '').replace(/ \(ID: \d+\)$/, '');
                const $program = $('#testProgram');

                if (!deptId) {
                    // Show all programs
                    $program.find('option[data-department]').show();
                    $program.find('option:first').text('🎓 Select Your Program (All Departments)');
                    return;
                }

                // Filter programs by department
                $program.find('option[data-department]').each(function() {
                    const programDept = $(this).data('department');
                    if (programDept && programDept.includes(deptName.replace(' Department', '').trim())) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

                $program.find('option:first').text(`🎓 Select Program (${deptName})`);
                $program.val(''); // Reset selection
            });
        });
    </script>
</body>
</html>