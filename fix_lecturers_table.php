<?php
/**
 * Fix Lecturers Table Schema
 * Adds missing columns and migrates data to normalized structure
 */

require_once "config.php";

echo "<h1>🔧 Fixing Lecturers Table Schema</h1>";

try {
    // Check current lecturers table structure
    echo "<h2>Checking Current Lecturers Table Structure...</h2>";
    $stmt = $pdo->query("DESCRIBE lecturers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    $missingColumns = [];

    // Check for missing columns
    $requiredColumns = ['user_id', 'department_id', 'id_number', 'education_level', 'gender'];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columnNames)) {
            $missingColumns[] = $col;
        }
    }

    if (!empty($missingColumns)) {
        echo "<p>Missing columns: " . implode(', ', $missingColumns) . "</p>";

        // Add missing columns
        if (!in_array('user_id', $columnNames)) {
            echo "<p>Adding user_id column...</p>";
            $pdo->exec("ALTER TABLE lecturers ADD COLUMN user_id INT NULL AFTER id");
            $pdo->exec("ALTER TABLE lecturers ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        }

        if (!in_array('department_id', $columnNames)) {
            echo "<p>Adding department_id column...</p>";
            $pdo->exec("ALTER TABLE lecturers ADD COLUMN department_id INT NULL AFTER user_id");
            $pdo->exec("ALTER TABLE lecturers ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL");
        }

        if (!in_array('id_number', $columnNames)) {
            echo "<p>Adding id_number column...</p>";
            $pdo->exec("ALTER TABLE lecturers ADD COLUMN id_number VARCHAR(50) NULL AFTER department_id");
        }

        if (!in_array('education_level', $columnNames)) {
            echo "<p>Adding education_level column...</p>";
            $pdo->exec("ALTER TABLE lecturers ADD COLUMN education_level VARCHAR(100) NULL AFTER id_number");
        }

        if (!in_array('gender', $columnNames)) {
            echo "<p>Adding gender column...</p>";
            $pdo->exec("ALTER TABLE lecturers ADD COLUMN gender ENUM('Male','Female','Other') NULL AFTER education_level");
        }

        echo "<p>✅ Columns added successfully</p>";
    } else {
        echo "<p>✅ All required columns exist</p>";
    }

    // Migrate existing lecturer data
    echo "<h2>Migrating Existing Lecturer Data...</h2>";

    // Get lecturers without user_id
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone FROM lecturers WHERE user_id IS NULL");
    $stmt->execute();
    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($lecturers)) {
        echo "<p>Found " . count($lecturers) . " lecturers to migrate</p>";

        foreach ($lecturers as $lecturer) {
            // Check if user already exists with this email
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $userStmt->execute([$lecturer['email']]);
            $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                // Update lecturer with existing user_id
                $updateStmt = $pdo->prepare("UPDATE lecturers SET user_id = ? WHERE id = ?");
                $updateStmt->execute([$existingUser['id'], $lecturer['id']]);
                echo "<p>✅ Linked lecturer {$lecturer['first_name']} {$lecturer['last_name']} to existing user</p>";
            } else {
                // Create new user
                $username = strtolower(str_replace(' ', '', $lecturer['first_name'] . $lecturer['last_name']));
                $password = password_hash('password123', PASSWORD_DEFAULT); // Default password

                $insertUser = $pdo->prepare("
                    INSERT INTO users (username, email, password, role, first_name, last_name, phone, status)
                    VALUES (?, ?, ?, 'lecturer', ?, ?, ?, 'active')
                ");
                $insertUser->execute([
                    $username,
                    $lecturer['email'],
                    $password,
                    $lecturer['first_name'],
                    $lecturer['last_name'],
                    $lecturer['phone']
                ]);

                $newUserId = $pdo->lastInsertId();

                // Update lecturer with new user_id
                $updateStmt = $pdo->prepare("UPDATE lecturers SET user_id = ? WHERE id = ?");
                $updateStmt->execute([$newUserId, $lecturer['id']]);

                echo "<p>✅ Created user account for lecturer {$lecturer['first_name']} {$lecturer['last_name']} (username: {$username})</p>";
            }
        }
    } else {
        echo "<p>✅ All lecturers already have user_id assigned</p>";
    }

    // Set default values for new columns
    echo "<h2>Setting Default Values...</h2>";

    // Set default department_id if null
    $deptStmt = $pdo->query("SELECT id FROM departments LIMIT 1");
    $defaultDept = $deptStmt->fetch(PDO::FETCH_ASSOC);

    if ($defaultDept) {
        $pdo->exec("UPDATE lecturers SET department_id = {$defaultDept['id']} WHERE department_id IS NULL");
        echo "<p>✅ Set default department for lecturers without department</p>";
    }

    // Set default education_level
    $pdo->exec("UPDATE lecturers SET education_level = 'Bachelor Degree' WHERE education_level IS NULL");
    echo "<p>✅ Set default education level</p>";

    // Set default gender
    $pdo->exec("UPDATE lecturers SET gender = 'Male' WHERE gender IS NULL");
    echo "<p>✅ Set default gender</p>";

    // Generate id_number if null
    $lecturersStmt = $pdo->query("SELECT id FROM lecturers WHERE id_number IS NULL");
    $lecturersToUpdate = $lecturersStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lecturersToUpdate as $lec) {
        $idNumber = 'LEC' . str_pad($lec['id'], 4, '0', STR_PAD_LEFT);
        $updateStmt = $pdo->prepare("UPDATE lecturers SET id_number = ? WHERE id = ?");
        $updateStmt->execute([$idNumber, $lec['id']]);
    }

    echo "<p>✅ Generated ID numbers for lecturers</p>";

    // Verify the migration
    echo "<h2>Verification...</h2>";
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM lecturers WHERE user_id IS NOT NULL");
    $count = $countStmt->fetch()['total'];
    echo "<p>✅ Total lecturers with user_id: {$count}</p>";

    echo "<h2>🎉 Migration Complete!</h2>";
    echo "<p>The lecturers table is now properly normalized and linked to the users table.</p>";
    echo "<p><strong>Default login credentials for new users:</strong></p>";
    echo "<ul>";
    echo "<li>Username: [firstname][lastname] (lowercase, no spaces)</li>";
    echo "<li>Password: password123</li>";
    echo "</ul>";
    echo "<p><a href='login.php'>Go to Login</a></p>";

} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
    echo "<p>Please check your database configuration and try again.</p>";
}
?>