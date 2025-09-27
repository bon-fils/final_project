<?php
/**
 * Fix existing HOD data inconsistencies
 */

require_once "config.php";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== Fixing HOD Data Inconsistencies ===\n\n";

    // Start transaction
    $pdo->beginTransaction();

    // 1. Fix departments with invalid HOD assignments
    echo "1. Fixing invalid HOD assignments...\n";

    // Find departments with hod_id pointing to users who don't have 'hod' role
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.hod_id, u.role as user_role, l.role as lecturer_role
        FROM departments d
        LEFT JOIN users u ON d.hod_id = u.id
        LEFT JOIN lecturers l ON u.email = l.email
        WHERE d.hod_id IS NOT NULL
    ");
    $stmt->execute();
    $invalid_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invalid_assignments as $dept) {
        echo "Fixing department: {$dept['name']} (ID: {$dept['id']})\n";

        if (!$dept['user_role']) {
            echo "  ❌ No user found for HOD ID {$dept['hod_id']} - clearing assignment\n";
            $stmt = $pdo->prepare("UPDATE departments SET hod_id = NULL WHERE id = ?");
            $stmt->execute([$dept['id']]);
        } elseif ($dept['user_role'] !== 'hod') {
            echo "  ↗️ Updating user role from '{$dept['user_role']}' to 'hod'\n";
            $stmt = $pdo->prepare("UPDATE users SET role = 'hod', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$dept['hod_id']]);
        }

        if ($dept['lecturer_role'] && $dept['lecturer_role'] !== 'hod') {
            echo "  ↗️ Updating lecturer role from '{$dept['lecturer_role']}' to 'hod'\n";
            $stmt = $pdo->prepare("UPDATE lecturers SET role = 'hod', updated_at = NOW() WHERE email = (SELECT email FROM users WHERE id = ?)");
            $stmt->execute([$dept['hod_id']]);
        }
    }

    // 2. Fix lecturers who should be HODs but aren't assigned to any department
    echo "\n2. Checking for orphaned HOD roles...\n";

    $stmt = $pdo->prepare("
        SELECT l.id, l.first_name, l.last_name, l.role, u.role as user_role
        FROM lecturers l
        LEFT JOIN users u ON u.email = l.email
        WHERE l.role = 'hod'
    ");
    $stmt->execute();
    $hod_lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($hod_lecturers as $lecturer) {
        // Check if this lecturer is actually assigned as HOD to any department
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id
            WHERE u.email = ? AND d.hod_id IS NOT NULL
        ");
        $stmt->execute([$lecturer['email']]);
        $assignment_count = $stmt->fetchColumn();

        if ($assignment_count == 0) {
            echo "Found orphaned HOD: {$lecturer['first_name']} {$lecturer['last_name']}\n";
            echo "  ↘️ Setting lecturer role back to 'lecturer'\n";
            $stmt = $pdo->prepare("UPDATE lecturers SET role = 'lecturer', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$lecturer['id']]);

            if ($lecturer['user_role'] === 'hod') {
                echo "  ↘️ Setting user role back to 'lecturer'\n";
                $stmt = $pdo->prepare("UPDATE users SET role = 'lecturer', updated_at = NOW() WHERE email = ?");
                $stmt->execute([$lecturer['email']]);
            }
        }
    }

    // 3. Ensure all HOD users have corresponding lecturer records with correct roles
    echo "\n3. Ensuring HOD user accounts are properly linked...\n";

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.role, l.role as lecturer_role
        FROM users u
        LEFT JOIN lecturers l ON u.email = l.email
        WHERE u.role = 'hod'
    ");
    $stmt->execute();
    $hod_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($hod_users as $user) {
        if (!$user['lecturer_role']) {
            echo "⚠️ HOD user {$user['username']} has no lecturer record\n";
        } elseif ($user['lecturer_role'] !== 'hod') {
            echo "↗️ Fixing lecturer role for {$user['username']}\n";
            $stmt = $pdo->prepare("UPDATE lecturers SET role = 'hod', updated_at = NOW() WHERE email = ?");
            $stmt->execute([$user['email']]);
        }
    }

    $pdo->commit();

    echo "\n✅ Data fix completed!\n";

    // Run the test again to verify
    echo "\n=== Verification ===\n";
    require_once "test_hod_role_update.php";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error fixing data: " . $e->getMessage() . "\n";
}
?>