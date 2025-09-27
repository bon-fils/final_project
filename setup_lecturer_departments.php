<?php
// Setup script to assign departments to lecturers and add sample courses
require_once 'config.php';

echo "=== SETTING UP LECTURER DEPARTMENTS AND SAMPLE DATA ===\n\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=rp_attendance_system", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. First, let's see what departments exist
    echo "1. Checking existing departments...\n";
    $dept_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($departments)) {
        echo "❌ No departments found. Please create departments first.\n";
        exit;
    }

    echo "Found " . count($departments) . " departments:\n";
    foreach ($departments as $dept) {
        echo "  - ID {$dept['id']}: {$dept['name']}\n";
    }

    // 2. Check existing lecturers
    echo "\n2. Checking existing lecturers...\n";
    $lecturer_stmt = $pdo->query("SELECT id, first_name, last_name, email, department_id FROM lecturers ORDER BY id");
    $lecturers = $lecturer_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($lecturers)) {
        echo "❌ No lecturers found. Please create lecturer accounts first.\n";
        exit;
    }

    echo "Found " . count($lecturers) . " lecturers:\n";
    foreach ($lecturers as $lecturer) {
        $dept_name = $lecturer['department_id'] ? "Department ID: {$lecturer['department_id']}" : "No department assigned";
        echo "  - ID {$lecturer['id']}: {$lecturer['first_name']} {$lecturer['last_name']} ({$lecturer['email']}) - {$dept_name}\n";
    }

    // 3. Assign departments to lecturers who don't have one
    echo "\n3. Assigning departments to lecturers...\n";
    foreach ($lecturers as $lecturer) {
        if (!$lecturer['department_id']) {
            // Assign Creative Arts department (ID: 4) to the first lecturer
            $assign_stmt = $pdo->prepare("UPDATE lecturers SET department_id = ? WHERE id = ?");
            $assign_stmt->execute([4, $lecturer['id']]); // Creative Arts department
            echo "✅ Assigned Creative Arts department to lecturer ID {$lecturer['id']}\n";
        }
    }

    // 4. Add sample courses for the Creative Arts department
    echo "\n4. Adding sample courses for Creative Arts department...\n";

    $sample_courses = [
        ['course_code' => 'CA101', 'name' => 'Introduction to Creative Arts', 'department_id' => 4, 'credits' => 3, 'duration_hours' => 45],
        ['course_code' => 'CA102', 'name' => 'Digital Art Fundamentals', 'department_id' => 4, 'credits' => 3, 'duration_hours' => 45],
        ['course_code' => 'CA201', 'name' => 'Advanced Drawing Techniques', 'department_id' => 4, 'credits' => 4, 'duration_hours' => 60],
        ['course_code' => 'CA202', 'name' => 'Color Theory and Application', 'department_id' => 4, 'credits' => 3, 'duration_hours' => 45],
        ['course_code' => 'CA301', 'name' => 'Portfolio Development', 'department_id' => 4, 'credits' => 4, 'duration_hours' => 60],
        ['course_code' => 'CA302', 'name' => 'Art History and Criticism', 'department_id' => 4, 'credits' => 3, 'duration_hours' => 45],
    ];

    foreach ($sample_courses as $course) {
        try {
            $insert_stmt = $pdo->prepare("
                INSERT INTO courses (course_code, name, department_id, credits, duration_hours, status)
                VALUES (?, ?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE name = name
            ");
            $insert_stmt->execute([
                $course['course_code'],
                $course['name'],
                $course['department_id'],
                $course['credits'],
                $course['duration_hours']
            ]);
            echo "✅ Added course: {$course['course_code']} - {$course['name']}\n";
        } catch (Exception $e) {
            echo "⚠️  Course {$course['course_code']} already exists or error: " . $e->getMessage() . "\n";
        }
    }

    // 5. Add sample options for Creative Arts department
    echo "\n5. Adding sample options for Creative Arts department...\n";

    $sample_options = [
        ['name' => 'Visual Arts', 'department_id' => 4],
        ['name' => 'Digital Media', 'department_id' => 4],
        ['name' => 'Performing Arts', 'department_id' => 4],
    ];

    foreach ($sample_options as $option) {
        try {
            $insert_stmt = $pdo->prepare("
                INSERT INTO options (name, department_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE name = name
            ");
            $insert_stmt->execute([$option['name'], $option['department_id']]);
            echo "✅ Added option: {$option['name']}\n";
        } catch (Exception $e) {
            echo "⚠️  Option {$option['name']} already exists or error: " . $e->getMessage() . "\n";
        }
    }

    // 6. Add sample students for Creative Arts department
    echo "\n6. Adding sample students for Creative Arts department...\n";

    $sample_students = [
        ['reg_no' => 'CA2024001', 'first_name' => 'Alice', 'last_name' => 'Johnson', 'email' => 'alice.johnson@student.rp.edu', 'option_id' => 1, 'year_level' => 'Year 1', 'department_id' => 4, 'sex' => 'Female', 'telephone' => '+250123456789'],
        ['reg_no' => 'CA2024002', 'first_name' => 'Bob', 'last_name' => 'Smith', 'email' => 'bob.smith@student.rp.edu', 'option_id' => 1, 'year_level' => 'Year 1', 'department_id' => 4, 'sex' => 'Male', 'telephone' => '+250123456790'],
        ['reg_no' => 'CA2024003', 'first_name' => 'Carol', 'last_name' => 'Williams', 'email' => 'carol.williams@student.rp.edu', 'option_id' => 2, 'year_level' => 'Year 2', 'department_id' => 4, 'sex' => 'Female', 'telephone' => '+250123456791'],
        ['reg_no' => 'CA2024004', 'first_name' => 'David', 'last_name' => 'Brown', 'email' => 'david.brown@student.rp.edu', 'option_id' => 2, 'year_level' => 'Year 2', 'department_id' => 4, 'sex' => 'Male', 'telephone' => '+250123456792'],
        ['reg_no' => 'CA2024005', 'first_name' => 'Emma', 'last_name' => 'Davis', 'email' => 'emma.davis@student.rp.edu', 'option_id' => 3, 'year_level' => 'Year 3', 'department_id' => 4, 'sex' => 'Female', 'telephone' => '+250123456793'],
    ];

    foreach ($sample_students as $student) {
        try {
            $insert_stmt = $pdo->prepare("
                INSERT INTO students (reg_no, first_name, last_name, email, option_id, year_level, department_id, sex, telephone)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE first_name = first_name
            ");
            $insert_stmt->execute([
                $student['reg_no'],
                $student['first_name'],
                $student['last_name'],
                $student['email'],
                $student['option_id'],
                $student['year_level'],
                $student['department_id'],
                $student['sex'],
                $student['telephone']
            ]);
            echo "✅ Added student: {$student['reg_no']} - {$student['first_name']} {$student['last_name']}\n";
        } catch (Exception $e) {
            echo "⚠️  Student {$student['reg_no']} already exists or error: " . $e->getMessage() . "\n";
        }
    }

    // 7. Verify the setup
    echo "\n7. Verifying setup...\n";

    // Check updated lecturers
    $updated_lecturers = $pdo->query("SELECT id, first_name, last_name, department_id FROM lecturers WHERE department_id IS NOT NULL ORDER BY id");
    $updated_lecturers = $updated_lecturers->fetchAll(PDO::FETCH_ASSOC);

    echo "✅ Lecturers with assigned departments:\n";
    foreach ($updated_lecturers as $lecturer) {
        echo "  - ID {$lecturer['id']}: {$lecturer['first_name']} {$lecturer['last_name']} (Department ID: {$lecturer['department_id']})\n";
    }

    // Check courses
    $courses_count = $pdo->query("SELECT COUNT(*) as total FROM courses WHERE department_id = 4");
    $courses_count = $courses_count->fetch()['total'];
    echo "✅ Courses in Creative Arts department: {$courses_count}\n";

    // Check students
    $students_count = $pdo->query("SELECT COUNT(*) as total FROM students WHERE option_id IN (SELECT id FROM options WHERE department_id = 4)");
    $students_count = $students_count->fetch()['total'];
    echo "✅ Students in Creative Arts department: {$students_count}\n";

    echo "\n=== SETUP COMPLETE ===\n";
    echo "✅ Lecturers now have department assignments\n";
    echo "✅ Sample courses added for Creative Arts department\n";
    echo "✅ Sample options added for Creative Arts department\n";
    echo "✅ Sample students added for Creative Arts department\n";
    echo "\nYou can now test the lecturer dashboard and attendance session functionality!\n";

} catch (Exception $e) {
    echo "❌ Error during setup: " . $e->getMessage() . "\n";
    echo "Please check your database connection and ensure all required tables exist.\n";
}