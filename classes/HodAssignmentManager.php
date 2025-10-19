<?php
/**
 * HOD Assignment Manager Class
 * Handles all business logic for HOD assignments with proper validation and error handling
 *
 * @version 1.0.0
 * @author RP System Development Team
 */

class HodAssignmentManager {
    private $pdo;
    private $logger;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->logger = new Logger('logs/hod_assignment_manager.log', Logger::INFO);
    }

    /**
     * Get all lecturers with optional department filtering
     *
     * @param int|null $departmentId Filter by department ID
     * @return array Array of lecturer data
     * @throws Exception If database query fails
     */
    public function getLecturers($departmentId = null) {
        try {
            $whereClause = "WHERE u.role IN ('lecturer', 'hod')";
            $params = [];

            if ($departmentId !== null) {
                $departmentId = filter_var($departmentId, FILTER_VALIDATE_INT);
                if ($departmentId === false || $departmentId <= 0) {
                    throw new InvalidArgumentException('Invalid department ID provided');
                }
                $whereClause .= " AND l.department_id = ?";
                $params[] = $departmentId;
            }

            $stmt = $this->pdo->prepare("
                SELECT l.id, u.first_name, u.last_name, u.email, u.role, l.department_id,
                    (u.first_name || ' ' || u.last_name) as full_name,
                    u.username, u.status, u.created_at, u.updated_at,
                    d.name as department_name,
                    hd.name as hod_department_name
                FROM lecturers l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN departments d ON l.department_id = d.id
                LEFT JOIN departments hd ON hd.hod_id = l.id
                {$whereClause}
                ORDER BY u.first_name, u.last_name
            ");

            $stmt->execute($params);
            $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Validate data integrity
            foreach ($lecturers as &$lecturer) {
                if (empty($lecturer['username']) && !empty($lecturer['email'])) {
                    $lecturer['data_integrity'] = 'warning';
                    $lecturer['data_integrity_message'] = 'User account not found';
                }
            }

            $this->logger->info('HOD Manager', 'Retrieved lecturers', [
                'count' => count($lecturers),
                'department_filter' => $departmentId
            ]);

            return $lecturers;

        } catch (PDOException $e) {
            $this->logger->error('HOD Manager', 'Failed to get lecturers', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId
            ]);
            throw new Exception('Database error while retrieving lecturers');
        }
    }

    /**
     * Get all departments with HOD assignment status
     *
     * @return array Array of department data with assignment status
     * @throws Exception If database query fails
     */
    public function getDepartments() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT d.id, d.name, d.hod_id,
                    u.first_name AS hod_first_name,
                    u.last_name AS hod_last_name,
                    CONCAT(u.first_name, ' ', u.last_name) as hod_name,
                    u.email AS hod_email,
                    u.role AS hod_role,
                    CASE
                        WHEN d.hod_id IS NULL THEN 'unassigned'
                        WHEN d.hod_id IS NOT NULL AND l.id IS NULL THEN 'invalid'
                        WHEN d.hod_id IS NOT NULL AND u.role != 'hod' THEN 'invalid_role'
                        ELSE 'assigned'
                    END as assignment_status
                FROM departments d
                LEFT JOIN lecturers l ON d.hod_id = l.id
                LEFT JOIN users u ON l.user_id = u.id
                ORDER BY d.name
            ");

            $stmt->execute();
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add data integrity warnings
            $integrityIssues = 0;
            foreach ($departments as &$dept) {
                if ($dept['assignment_status'] === 'invalid' || $dept['assignment_status'] === 'invalid_role') {
                    $integrityIssues++;
                    $dept['data_integrity'] = 'warning';
                    $dept['data_integrity_message'] = $dept['assignment_status'] === 'invalid' ?
                        'HOD ID exists but lecturer not found' :
                        'Assigned lecturer is not marked as HOD';
                }
            }

            $this->logger->info('HOD Manager', 'Retrieved departments', [
                'count' => count($departments),
                'integrity_issues' => $integrityIssues
            ]);

            return $departments;

        } catch (PDOException $e) {
            $this->logger->error('HOD Manager', 'Failed to get departments', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Database error while retrieving departments');
        }
    }

    /**
     * Get assignment statistics
     *
     * @return array Statistics data
     * @throws Exception If database query fails
     */
    public function getAssignmentStats() {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM departments");
            $stmt->execute();
            $totalDepts = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL");
            $stmt->execute();
            $assignedDepts = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM lecturers l LEFT JOIN users u ON l.user_id = u.id WHERE u.role IN ('lecturer', 'hod') AND u.id IS NOT NULL");
            $stmt->execute();
            $totalLecturers = $stmt->fetchColumn();

            $stats = [
                'total_departments' => $totalDepts,
                'assigned_departments' => $assignedDepts,
                'unassigned_departments' => $totalDepts - $assignedDepts,
                'total_lecturers' => $totalLecturers
            ];

            $this->logger->info('HOD Manager', 'Retrieved assignment statistics', $stats);

            return $stats;

        } catch (PDOException $e) {
            $this->logger->error('HOD Manager', 'Failed to get assignment stats', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Database error while retrieving statistics');
        }
    }

    /**
     * Assign HOD to department with comprehensive validation
     *
     * @param int $departmentId Department ID
     * @param int|null $hodId Lecturer ID (null to remove assignment)
     * @return array Result data
     * @throws Exception If validation fails or database error occurs
     */
    public function assignHod($departmentId, $hodId) {
        // Validate inputs
        $departmentId = filter_var($departmentId, FILTER_VALIDATE_INT);
        if ($departmentId === false || $departmentId <= 0) {
            throw new InvalidArgumentException('Invalid department ID provided');
        }

        if ($hodId !== null) {
            $hodId = filter_var($hodId, FILTER_VALIDATE_INT);
            if ($hodId === false || $hodId < 0) {
                throw new InvalidArgumentException('Invalid HOD ID provided');
            }
        }

        $this->pdo->beginTransaction();

        try {
            // Verify department exists
            $department = $this->validateDepartmentExists($departmentId);

            // Check if this is actually a change
            $isSameAssignment = $this->isSameAssignment($department['hod_id'], $hodId);

            if ($isSameAssignment) {
                $this->pdo->rollBack();
                return [
                    'status' => 'info',
                    'message' => 'No changes made - same HOD assignment already exists'
                ];
            }

            if ($hodId) {
                // Validate lecturer and handle assignment
                $lecturer = $this->validateLecturerExists($hodId);
                $this->handleLecturerReassignment($hodId, $departmentId, $lecturer);
                $userId = $this->ensureUserAccount($lecturer);
                $this->updateDepartmentHod($departmentId, $hodId);

                $action = 'assigned';
                $hodName = "{$lecturer['first_name']} {$lecturer['last_name']}";
            } else {
                // Remove HOD assignment
                $this->updateDepartmentHod($departmentId, null);
                $action = 'removed';
                $hodName = 'None';
            }

            $this->pdo->commit();

            $result = [
                'status' => 'success',
                'message' => "Successfully assigned",
                'details' => [
                    'department' => $department['name'],
                    'hod_name' => $hodName,
                    'action' => $action,
                    'previous_hod_id' => $department['hod_id'],
                    'new_hod_id' => $hodId,
                    'department_id' => $departmentId
                ]
            ];

            $this->logger->info('HOD Manager', 'HOD assignment completed', [
                'action' => $action,
                'department' => $department['name'],
                'hod_name' => $hodName,
                'department_id' => $departmentId,
                'hod_id' => $hodId
            ]);

            return $result;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('HOD Manager', 'HOD assignment failed', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId,
                'hod_id' => $hodId
            ]);
            throw $e;
        }
    }

    /**
     * Validate that a department exists
     *
     * @param int $departmentId Department ID
     * @return array Department data
     * @throws Exception If department not found
     */
    private function validateDepartmentExists($departmentId) {
        $stmt = $this->pdo->prepare("SELECT id, name, hod_id FROM departments WHERE id = ?");
        $stmt->execute([$departmentId]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$department) {
            throw new Exception('Department not found in database');
        }

        return $department;
    }

    /**
     * Validate that a lecturer exists
     *
     * @param int $hodId Lecturer ID
     * @return array Lecturer data
     * @throws Exception If lecturer not found
     */
    private function validateLecturerExists($hodId) {
        $stmt = $this->pdo->prepare("
            SELECT l.id, u.first_name, u.last_name, u.email, u.role, l.department_id
            FROM lecturers l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.id = ?
        ");
        $stmt->execute([$hodId]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturer) {
            throw new Exception('Lecturer not found in database');
        }

        return $lecturer;
    }

    /**
     * Check if the assignment is the same as current
     *
     * @param mixed $currentHodId Current HOD ID
     * @param mixed $newHodId New HOD ID
     * @return bool True if same assignment
     */
    private function isSameAssignment($currentHodId, $newHodId) {
        return ($currentHodId == $newHodId) ||
               ($currentHodId === null && $newHodId === null) ||
               ($currentHodId === null && empty($newHodId)) ||
               (empty($currentHodId) && $newHodId === null);
    }

    /**
     * Handle lecturer reassignment if already HOD elsewhere
     *
     * @param int $hodId Lecturer ID
     * @param int $departmentId Target department ID
     * @param array $lecturer Lecturer data
     */
    private function handleLecturerReassignment($hodId, $departmentId, $lecturer) {
        if ($lecturer['role'] === 'hod') {
            $stmt = $this->pdo->prepare("SELECT name FROM departments WHERE hod_id = ? AND id != ?");
            $stmt->execute([$hodId, $departmentId]);
            $otherDept = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($otherDept) {
                // Remove from previous department
                $stmt = $this->pdo->prepare("UPDATE departments SET hod_id = NULL WHERE hod_id = ?");
                $stmt->execute([$hodId]);

                $this->logger->info('HOD Manager', 'Removed lecturer from previous department', [
                    'lecturer_id' => $hodId,
                    'previous_department' => $otherDept['name']
                ]);
            }
        }
    }

    /**
     * Ensure user account exists and is set as HOD
     *
     * @param array $lecturer Lecturer data
     * @return int User ID
     */
    private function ensureUserAccount($lecturer) {
        // Check if user account already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$lecturer['email']]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            // Update existing user to HOD role
            $userId = $existingUser['id'];
            $stmt = $this->pdo->prepare("
                UPDATE users SET role = 'hod', status = 'active', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId]);

            $this->logger->info('HOD Manager', 'Updated existing user to HOD role', [
                'user_id' => $userId
            ]);
        } else {
            // Create new user account
            $username = $this->generateUniqueUsername($lecturer['first_name'], $lecturer['last_name']);
            $defaultPassword = password_hash('Welcome123!', PASSWORD_DEFAULT);

            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password, role, status, created_at)
                VALUES (?, ?, ?, 'hod', 'active', NOW())
            ");
            $stmt->execute([$username, $lecturer['email'], $defaultPassword]);

            $userId = $this->pdo->lastInsertId();

            $this->logger->info('HOD Manager', 'Created new HOD user account', [
                'username' => $username,
                'user_id' => $userId
            ]);
        }

        // Verify the user account
        $stmt = $this->pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $verifyUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verifyUser || $verifyUser['role'] !== 'hod') {
            throw new Exception('User account verification failed');
        }

        return $userId;
    }

    /**
     * Generate a unique username
     *
     * @param string $firstName First name
     * @param string $lastName Last name
     * @return string Unique username
     */
    private function generateUniqueUsername($firstName, $lastName) {
        $baseUsername = strtolower($firstName . '.' . $lastName);
        $username = $baseUsername;
        $counter = 1;

        while (true) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if (!$stmt->fetch()) {
                break; // Username is available
            }

            $username = $baseUsername . $counter;
            $counter++;

            if ($counter > 100) {
                throw new Exception('Unable to generate unique username');
            }
        }

        return $username;
    }

    /**
     * Update department HOD assignment
     *
     * @param int $departmentId Department ID
     * @param int|null $hodId HOD ID (null to remove)
     */
    private function updateDepartmentHod($departmentId, $hodId) {
        $stmt = $this->pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
        $stmt->execute([$hodId ?: null, $departmentId]);

        // Verify the update
        $stmt = $this->pdo->prepare("SELECT hod_id FROM departments WHERE id = ?");
        $stmt->execute([$departmentId]);
        $updatedDept = $stmt->fetch(PDO::FETCH_ASSOC);

        if (($updatedDept['hod_id'] ?? null) != $hodId) {
            throw new Exception('Department update verification failed');
        }
    }

    /**
     * Get data integrity issues
     *
     * @return array Array of departments with integrity issues
     */
    public function getDataIntegrityIssues() {
        try {
            $departments = $this->getDepartments();
            return array_filter($departments, function($dept) {
                return isset($dept['data_integrity']) && $dept['data_integrity'] === 'warning';
            });
        } catch (Exception $e) {
            $this->logger->error('HOD Manager', 'Failed to get data integrity issues', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Fix data integrity issues by clearing invalid HOD assignments
     *
     * @return array Results of the fix operation
     */
    public function fixDataIntegrityIssues() {
        $issues = $this->getDataIntegrityIssues();
        $fixed = 0;
        $errors = 0;

        foreach ($issues as $dept) {
            try {
                $this->assignHod($dept['id'], null);
                $fixed++;
            } catch (Exception $e) {
                $this->logger->error('HOD Manager', 'Failed to fix integrity issue', [
                    'department_id' => $dept['id'],
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        return [
            'total_issues' => count($issues),
            'fixed' => $fixed,
            'errors' => $errors
        ];
    }
}
?>