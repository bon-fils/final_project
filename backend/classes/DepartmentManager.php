<?php
/**
 * Department Management Class
 * Rwanda Polytechnic Attendance System
 * Handles all department and program related operations
 */

require_once __DIR__ . '/DatabaseManager.php';
require_once __DIR__ . '/ValidationManager.php';
require_once __DIR__ . '/Logger.php';

class DepartmentManager {
    private $db;
    private $validator;
    private $logger;

    public function __construct($pdo = null) {
        $this->logger = new Logger();
        if ($pdo) {
            $this->db = DatabaseManager::getInstance($pdo, $this->logger)->getConnection();
        } else {
            // For backward compatibility, try to get existing instance
            try {
                $this->db = DatabaseManager::getInstance()->getConnection();
            } catch (Exception $e) {
                throw new Exception('DatabaseManager not initialized. PDO connection required.');
            }
        }
        $this->validator = new ValidationManager();
    }

    /**
     * Get all departments with their programs and HoD information
     */
    public function getAllDepartments() {
        try {
            $query = "SELECT d.id AS dept_id, d.name AS dept_name, d.hod_id,
                             COALESCE(u.username, 'Not Assigned') AS hod_name
                      FROM departments d
                      LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
                      ORDER BY d.name ASC";

            $departments = $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);

            foreach ($departments as &$dept) {
                $stmt = $this->db->prepare("SELECT id, name, department_id, status,
                                                  created_at,
                                                  COALESCE(status, 'active') AS display_status
                                           FROM options WHERE department_id = ? ORDER BY name");
                $stmt->execute([$dept['dept_id']]);
                $dept['programs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Add program count for each department
                $dept['program_count'] = count($dept['programs']);
                $dept['active_programs'] = count(array_filter($dept['programs'], function($p) {
                    return $p['status'] === 'active';
                }));
            }

            $this->logger->info("DepartmentManager", "Retrieved " . count($departments) . " departments with detailed program info");
            return ['success' => true, 'data' => $departments];

        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Database error retrieving departments: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve departments'];
        }
    }

    /**
     * Get detailed program information
     */
    public function getProgramDetails($programId) {
        try {
            $stmt = $this->db->prepare("SELECT o.id, o.name, o.department_id, o.status, o.created_at,
                                              d.name AS department_name, d.id AS dept_id
                                       FROM options o
                                       JOIN departments d ON o.department_id = d.id
                                       WHERE o.id = ?");
            $stmt->execute([$programId]);
            $program = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$program) {
                return ['success' => false, 'message' => 'Program not found'];
            }

            return ['success' => true, 'data' => $program];

        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Error retrieving program details: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve program details'];
        }
    }

    /**
     * Update program status
     */
    public function updateProgramStatus($programId, $status) {
        try {
            $validStatuses = ['active', 'inactive'];
            if (!in_array($status, $validStatuses)) {
                return ['success' => false, 'message' => 'Invalid status'];
            }

            $stmt = $this->db->prepare("UPDATE options SET status = ? WHERE id = ?");
            $stmt->execute([$status, $programId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Program not found'];
            }

            $this->logger->info("DepartmentManager", "Updated program $programId status to $status");
            return ['success' => true, 'message' => 'Program status updated successfully'];

        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Error updating program status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update program status'];
        }
    }

    /**
     * Create a new department with programs
     */
    public function createDepartment($name, $hodId = null, $programs = []) {
        try {
            // Validate input
            $validation = $this->validator->validateDepartmentData($name, $hodId, $programs);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            // Check for duplicate names
            if ($this->isDepartmentNameExists($name)) {
                return ['success' => false, 'message' => 'Department name already exists'];
            }

            $this->db->beginTransaction();

            // Insert department
            $stmt = $this->db->prepare("INSERT INTO departments (name, hod_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $hodId]);
            $deptId = $this->db->lastInsertId();

            // Insert programs
            $programsAdded = 0;
            if (!empty($programs)) {
                $stmt = $this->db->prepare("INSERT INTO options (name, department_id, status) VALUES (?, ?, 'active')");
                foreach ($programs as $program) {
                    $program = trim($program);
                    if (!empty($program)) {
                        $stmt->execute([$program, $deptId]);
                        $programsAdded++;
                    }
                }
            }

            $this->db->commit();
            $this->logger->info("DepartmentManager", "Created department: $name with $programsAdded programs");

            return [
                'success' => true,
                'message' => 'Department created successfully',
                'data' => [
                    'dept_id' => $deptId,
                    'dept_name' => $name,
                    'programs_count' => $programsAdded
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("DepartmentManager", "Error creating department: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create department'];
        }
    }

    /**
     * Delete a department and all its programs
     */
    public function deleteDepartment($deptId) {
        try {
            $deptId = (int)$deptId;

            // Get department info for logging
            $deptInfo = $this->getDepartmentById($deptId);
            if (!$deptInfo) {
                return ['success' => false, 'message' => 'Department not found'];
            }

            $this->db->beginTransaction();

            // Delete programs first
            $stmt = $this->db->prepare("DELETE FROM options WHERE department_id = ?");
            $stmt->execute([$deptId]);
            $programsDeleted = $stmt->rowCount();

            // Delete department
            $stmt = $this->db->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$deptId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Department not found');
            }

            $this->db->commit();
            $this->logger->info("DepartmentManager", "Deleted department: {$deptInfo['name']} with $programsDeleted programs");

            return [
                'success' => true,
                'message' => 'Department deleted successfully',
                'data' => ['programs_deleted' => $programsDeleted]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("DepartmentManager", "Error deleting department: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete department'];
        }
    }

    /**
     * Add a program to a department
     */
    public function addProgram($deptId, $programName, $status = 'active') {
        try {
            $deptId = (int)$deptId;

            // Validate input
            $validation = $this->validator->validateProgramData($programName, $status);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            // Check if department exists
            if (!$this->getDepartmentById($deptId)) {
                return ['success' => false, 'message' => 'Department not found'];
            }

            $stmt = $this->db->prepare("INSERT INTO options (name, department_id, status) VALUES (?, ?, ?)");
            $stmt->execute([$programName, $deptId, $status]);

            $this->logger->info("DepartmentManager", "Added program: $programName to department ID: $deptId");

            return [
                'success' => true,
                'message' => 'Program added successfully',
                'data' => [
                    'program_id' => $this->db->lastInsertId(),
                    'program_name' => $programName
                ]
            ];

        } catch (Exception $e) {
            $this->logger->error("DepartmentManager", "Error adding program: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add program'];
        }
    }

    /**
     * Delete a program
     */
    public function deleteProgram($progId) {
        try {
            $progId = (int)$progId;

            // First check if program exists and get its info for logging
            $programInfo = $this->getProgramById($progId);
            if (!$programInfo) {
                return ['success' => false, 'message' => 'Program not found'];
            }

            // Check if program is being used in other tables (like attendance records)
            $usageCheck = $this->checkProgramUsage($progId);
            if ($usageCheck['in_use']) {
                return [
                    'success' => false,
                    'message' => "Cannot delete program. It is currently being used in {$usageCheck['tables']}."
                ];
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM options WHERE id = ?");
            $stmt->execute([$progId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Program not found'];
            }

            $this->db->commit();
            $this->logger->info("DepartmentManager", "Deleted program: {$programInfo['name']} (ID: $progId)");

            return [
                'success' => true,
                'message' => 'Program deleted successfully'
            ];

        } catch (PDOException $e) {
            if (isset($this->db)) {
                $this->db->rollBack();
            }
            $this->logger->error("DepartmentManager", "Database error deleting program: " . $e->getMessage());

            // Check for specific database errors
            if (strpos($e->getMessage(), 'foreign key') !== false) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete program as it is being used in other records. Please contact administrator.'
                ];
            }

            return ['success' => false, 'message' => 'Failed to delete program: ' . $e->getMessage()];
        } catch (Exception $e) {
            if (isset($this->db)) {
                $this->db->rollBack();
            }
            $this->logger->error("DepartmentManager", "Error deleting program: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete program'];
        }
    }

    /**
     * Get program by ID
     */
    private function getProgramById($progId) {
        try {
            $stmt = $this->db->prepare("SELECT id, name FROM options WHERE id = ?");
            $stmt->execute([$progId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Error getting program by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if program is being used in other tables
     */
    private function checkProgramUsage($progId) {
        $tables = [];
        $inUse = false;

        try {
            // Check common tables that might reference programs
            $checkTables = [
                'attendance_records' => 'program_id',
                'courses' => 'program_id',
                'student_registrations' => 'program_id',
                'lecturer_assignments' => 'program_id'
            ];

            foreach ($checkTables as $table => $column) {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
                $stmt->execute([$progId]);
                $count = $stmt->fetchColumn();

                if ($count > 0) {
                    $tables[] = $table;
                    $inUse = true;
                }
            }

            return [
                'in_use' => $inUse,
                'tables' => implode(', ', $tables)
            ];

        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Error checking program usage: " . $e->getMessage());
            return ['in_use' => false, 'tables' => ''];
        }
    }

    /**
     * Get department statistics
     */
    public function getStatistics() {
        try {
            $stats = [];

            $stats['total_departments'] = $this->db->query("SELECT COUNT(*) FROM departments")->fetchColumn();
            $stats['assigned_hods'] = $this->db->query("SELECT COUNT(*) FROM departments WHERE hod_id IS NOT NULL")->fetchColumn();
            $stats['total_programs'] = $this->db->query("SELECT COUNT(*) FROM options")->fetchColumn();

            if ($stats['total_departments'] > 0) {
                $avg = $this->db->query("SELECT AVG(program_count) FROM (SELECT COUNT(*) as program_count FROM options GROUP BY department_id) as counts")->fetchColumn();
                $stats['avg_programs_per_dept'] = round($avg, 2);
            } else {
                $stats['avg_programs_per_dept'] = 0;
            }

            return ['success' => true, 'data' => $stats];

        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Error retrieving statistics: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve statistics'];
        }
    }

    /**
     * Update department HOD
     */
    public function updateDepartmentHod($deptId, $hodId) {
        try {
            $deptId = (int)$deptId;

            // Validate department exists
            $deptInfo = $this->getDepartmentById($deptId);
            if (!$deptInfo) {
                return ['success' => false, 'message' => 'Department not found'];
            }

            // If hodId is provided, validate constraints
            if ($hodId !== null && $hodId !== '') {
                $hodId = (int)$hodId;

                // Check if the user is assigned as lecturer to this department
                $stmt = $this->db->prepare("
                    SELECT u.id FROM users u
                    JOIN lecturers l ON u.email = l.email
                    WHERE u.id = ? AND l.department_id = ? AND u.role IN ('lecturer', 'hod')
                ");
                $stmt->execute([$hodId, $deptId]);
                if (!$stmt->fetch()) {
                    return ['success' => false, 'message' => 'Selected user is not assigned to this department'];
                }

                // Check if the user is already HOD for another department
                $stmt = $this->db->prepare("SELECT id FROM departments WHERE hod_id = ? AND id != ?");
                $stmt->execute([$hodId, $deptId]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'message' => 'Selected lecturer is already HOD for another department'];
                }
            } else {
                $hodId = null; // Unassign HOD
            }

            // Get current HOD before updating (for role management)
            $stmt = $this->db->prepare("SELECT hod_id FROM departments WHERE id = ?");
            $stmt->execute([$deptId]);
            $currentHodId = $stmt->fetchColumn();

            // Update department
            $stmt = $this->db->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
            $stmt->execute([$hodId, $deptId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'No changes made'];
            }

            // Update user roles based on HOD assignment
            if ($hodId !== null && $hodId !== '') {
                // Assigning HOD - change role to 'hod' in both users and lecturers tables
                $stmt = $this->db->prepare("UPDATE users SET role = 'hod' WHERE id = ? AND role = 'lecturer'");
                $stmt->execute([$hodId]);

                $stmt = $this->db->prepare("UPDATE lecturers SET role = 'hod' WHERE email = (SELECT email FROM users WHERE id = ?) AND role = 'lecturer'");
                $stmt->execute([$hodId]);
            }

            // Handle role change for previously assigned HOD (if different from new HOD)
            if ($currentHodId && $currentHodId != $hodId) {
                // Check if the previous HOD is still assigned to any other department
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM departments WHERE hod_id = ?");
                $stmt->execute([$currentHodId]);
                $otherHodCount = $stmt->fetchColumn();

                // If not HOD for any other department, change role back to 'lecturer' in both tables
                if ($otherHodCount == 0) {
                    $stmt = $this->db->prepare("UPDATE users SET role = 'lecturer' WHERE id = ? AND role = 'hod'");
                    $stmt->execute([$currentHodId]);

                    $stmt = $this->db->prepare("UPDATE lecturers SET role = 'lecturer' WHERE email = (SELECT email FROM users WHERE id = ?) AND role = 'hod'");
                    $stmt->execute([$currentHodId]);
                }
            }

            $action = $hodId ? 'assigned' : 'unassigned';
            $this->logger->info("DepartmentManager", "HOD $action for department: {$deptInfo['name']} (ID: $deptId)");

            return [
                'success' => true,
                'message' => 'Department HOD updated successfully',
                'data' => [
                    'dept_id' => $deptId,
                    'hod_id' => $hodId
                ]
            ];

        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Error updating department HOD: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update department HOD'];
        }
    }

    /**
     * Get available HoDs for selection (general - for new departments)
     */
    public function getAvailableHoDs() {
        try {
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE role = 'hod' ORDER BY username");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Error retrieving HoDs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get available HoDs for a specific department
     * Only lecturers/HODs assigned to the department who are not already HOD elsewhere
     */
    public function getAvailableHoDsForDepartment($deptId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.username
                FROM users u
                JOIN lecturers l ON u.email = l.email
                WHERE l.department_id = ?
                AND u.role IN ('lecturer', 'hod')
                AND u.id NOT IN (
                    SELECT hod_id FROM departments WHERE hod_id IS NOT NULL AND id != ?
                )
                ORDER BY u.username
            ");
            $stmt->execute([$deptId, $deptId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error("DepartmentManager", "Error retrieving department-specific HoDs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if department name already exists
     */
    private function isDepartmentNameExists($name) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM departments WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
        $stmt->execute([$name]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get department by ID
     */
    private function getDepartmentById($deptId) {
        $stmt = $this->db->prepare("SELECT id, name FROM departments WHERE id = ?");
        $stmt->execute([$deptId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>