<?php
/**
 * Department-Option Relationship Validator
 * Provides centralized validation for department-option relationships
 * Version: 1.0
 */

class DepartmentOptionValidator {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Validate that an option belongs to a specific department
     */
    public function validateOptionBelongsToDepartment($optionId, $departmentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM options o
                INNER JOIN departments d ON o.department_id = d.id
                WHERE o.id = ? AND o.department_id = ?
            ");
            $stmt->execute([$optionId, $departmentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;

        } catch (PDOException $e) {
            error_log("Error validating option-department relationship: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all valid options for a department
     */
    public function getValidOptionsForDepartment($departmentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name
                FROM options
                WHERE department_id = ?
                ORDER BY name
            ");
            $stmt->execute([$departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error getting options for department: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate multiple option-department relationships
     */
    public function validateMultipleRelationships($relationships) {
        $results = [];

        foreach ($relationships as $relationship) {
            $optionId = $relationship['option_id'];
            $departmentId = $relationship['department_id'];

            $results[] = [
                'option_id' => $optionId,
                'department_id' => $departmentId,
                'valid' => $this->validateOptionBelongsToDepartment($optionId, $departmentId),
                'message' => $this->validateOptionBelongsToDepartment($optionId, $departmentId)
                    ? 'Valid relationship'
                    : 'Option does not belong to specified department'
            ];
        }

        return $results;
    }

    /**
     * Get department information for an option
     */
    public function getDepartmentForOption($optionId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT d.id, d.name
                FROM departments d
                INNER JOIN options o ON d.id = o.department_id
                WHERE o.id = ?
            ");
            $stmt->execute([$optionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error getting department for option: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get option information for a department
     */
    public function getOptionForDepartment($optionId, $departmentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name
                FROM options
                WHERE id = ? AND department_id = ?
            ");
            $stmt->execute([$optionId, $departmentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error getting option for department: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if department has any options
     */
    public function departmentHasOptions($departmentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM options
                WHERE department_id = ?
            ");
            $stmt->execute([$departmentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;

        } catch (PDOException $e) {
            error_log("Error checking if department has options: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get department statistics
     */
    public function getDepartmentOptionStats($departmentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_options
                FROM options
                WHERE department_id = ?
            ");
            $stmt->execute([$departmentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error getting department option stats: " . $e->getMessage());
            return ['total_options' => 0, 'active_options' => 0, 'inactive_options' => 0];
        }
    }

    /**
     * Validate department exists and is active
     */
    public function validateDepartment($departmentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM departments
                WHERE id = ?
            ");
            $stmt->execute([$departmentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;

        } catch (PDOException $e) {
            error_log("Error validating department: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate option exists and is active
     */
    public function validateOption($optionId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM options
                WHERE id = ?
            ");
            $stmt->execute([$optionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;

        } catch (PDOException $e) {
            error_log("Error validating option: " . $e->getMessage());
            return false;
        }
    }
}

// Utility functions for easy access
function validateDepartmentOptionRelationship($pdo, $optionId, $departmentId) {
    $validator = new DepartmentOptionValidator($pdo);
    return $validator->validateOptionBelongsToDepartment($optionId, $departmentId);
}

function getDepartmentOptions($pdo, $departmentId) {
    $validator = new DepartmentOptionValidator($pdo);
    return $validator->getValidOptionsForDepartment($departmentId);
}

function validateDepartment($pdo, $departmentId) {
    $validator = new DepartmentOptionValidator($pdo);
    return $validator->validateDepartment($departmentId);
}

function validateOption($pdo, $optionId) {
    $validator = new DepartmentOptionValidator($pdo);
    return $validator->validateOption($optionId);
}
?>