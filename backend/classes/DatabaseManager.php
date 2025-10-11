<?php
/**
 * Advanced Database Manager
 * Provides optimized database operations with indexing and performance monitoring
 * Version: 2.0 - Enhanced with security and performance features
 */

class DatabaseManager {
    private static $instance = null;
    private $pdo;
    private $logger;
    private $queryCount = 0;
    private $queryTime = 0;
    private $slowQueryThreshold = 1.0; // seconds

    private function __construct($pdo, $logger = null) {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->initializeTables();
        $this->createIndexes();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance($pdo = null, $logger = null) {
        if (self::$instance === null) {
            if ($pdo === null) {
                throw new Exception('PDO connection required for first DatabaseManager instance');
            }
            self::$instance = new self($pdo, $logger);
        }
        return self::$instance;
    }

    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Initialize database tables
     */
    private function initializeTables() {
        $tables = [
            'users' => "
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    role ENUM('admin', 'lecturer', 'student', 'hod') NOT NULL,
                    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                    email_verified BOOLEAN DEFAULT FALSE,
                    last_login DATETIME NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_username (username),
                    INDEX idx_role (role),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                )
            ",
            'students' => "
                CREATE TABLE IF NOT EXISTS students (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNIQUE NOT NULL,
                    option_id INT NOT NULL,
                    year_level ENUM('1', '2', '3', '4') NOT NULL,
                    first_name VARCHAR(50) NOT NULL,
                    last_name VARCHAR(50) NOT NULL,
                    dob DATE NOT NULL,
                    cell VARCHAR(100) NOT NULL,
                    sector VARCHAR(100) NOT NULL,
                    province VARCHAR(100) NOT NULL,
                    parent_first_name VARCHAR(50) NOT NULL,
                    parent_last_name VARCHAR(50) NOT NULL,
                    parent_contact VARCHAR(20) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    reg_no VARCHAR(20) UNIQUE NOT NULL,
                    student_id_number VARCHAR(20) UNIQUE NOT NULL,
                    department_id INT NOT NULL,
                    telephone VARCHAR(20) NOT NULL,
                    sex ENUM('Male', 'Female') NOT NULL,
                    photo VARCHAR(255) NULL,
                    fingerprint TEXT NULL,
                    password VARCHAR(255) NOT NULL,
                    status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (department_id) REFERENCES departments(id),
                    FOREIGN KEY (option_id) REFERENCES options(id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_reg_no (reg_no),
                    INDEX idx_student_id_number (student_id_number),
                    INDEX idx_department_id (department_id),
                    INDEX idx_option_id (option_id),
                    INDEX idx_year_level (year_level),
                    INDEX idx_province (province),
                    INDEX idx_sector (sector),
                    INDEX idx_cell (cell),
                    INDEX idx_dob (dob),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                )
            ",
            'departments' => "
                CREATE TABLE IF NOT EXISTS departments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) UNIQUE NOT NULL,
                    code VARCHAR(10) UNIQUE NOT NULL,
                    description TEXT NULL,
                    hod_id INT NULL,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (hod_id) REFERENCES users(id),
                    INDEX idx_code (code),
                    INDEX idx_status (status),
                    INDEX idx_hod_id (hod_id)
                )
            ",
            'options' => "
                CREATE TABLE IF NOT EXISTS options (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    department_id INT NOT NULL,
                    description TEXT NULL,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                    INDEX idx_department_id (department_id),
                    INDEX idx_status (status)
                )
            ",
            'system_logs' => "
                CREATE TABLE IF NOT EXISTS system_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    timestamp DATETIME NOT NULL,
                    level VARCHAR(20) NOT NULL,
                    category VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    user_id INT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    session_id VARCHAR(255) NULL,
                    request_uri TEXT NULL,
                    context TEXT NULL,
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_level (level),
                    INDEX idx_category (category),
                    INDEX idx_user_id (user_id),
                    INDEX idx_session_id (session_id)
                )
            ",
            'audit_trail' => "
                CREATE TABLE IF NOT EXISTS audit_trail (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    table_name VARCHAR(50) NOT NULL,
                    record_id INT NOT NULL,
                    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
                    user_id INT NULL,
                    old_values TEXT NULL,
                    new_values TEXT NULL,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45) NULL,
                    INDEX idx_table_name (table_name),
                    INDEX idx_record_id (record_id),
                    INDEX idx_action (action),
                    INDEX idx_user_id (user_id),
                    INDEX idx_timestamp (timestamp)
                )
            "
        ];

        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                if ($this->logger) {
                    $this->logger->error('DatabaseManager', "Failed to create table {$tableName}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Create database indexes for performance
     */
    private function createIndexes() {
        $indexes = [
            'users' => [
                'CREATE INDEX IF NOT EXISTS idx_users_email_role ON users(email, role)',
                'CREATE INDEX IF NOT EXISTS idx_users_status_last_login ON users(status, last_login)',
                'CREATE INDEX IF NOT EXISTS idx_users_created_at_role ON users(created_at, role)'
            ],
            'students' => [
                'CREATE INDEX IF NOT EXISTS idx_students_email_department ON students(email, department_id)',
                'CREATE INDEX IF NOT EXISTS idx_students_reg_no_year ON students(reg_no, year_level)',
                'CREATE INDEX IF NOT EXISTS idx_students_department_year ON students(department_id, year_level)',
                'CREATE INDEX IF NOT EXISTS idx_students_status_created ON students(status, created_at)'
            ],
            'system_logs' => [
                'CREATE INDEX IF NOT EXISTS idx_logs_level_timestamp ON system_logs(level, timestamp)',
                'CREATE INDEX IF NOT EXISTS idx_logs_category_timestamp ON system_logs(category, timestamp)',
                'CREATE INDEX IF NOT EXISTS idx_logs_user_timestamp ON system_logs(user_id, timestamp)'
            ]
        ];

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $indexSql) {
                try {
                    $this->pdo->exec($indexSql);
                } catch (PDOException $e) {
                    if ($this->logger) {
                        $this->logger->warning('DatabaseManager', "Failed to create index on {$table}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Execute query with performance monitoring
     */
    public function execute($sql, $params = [], $description = '') {
        $startTime = microtime(true);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $executionTime = microtime(true) - $startTime;
            $this->queryCount++;
            $this->queryTime += $executionTime;

            // Log slow queries
            if ($executionTime > $this->slowQueryThreshold) {
                if ($this->logger) {
                    $this->logger->warning('DatabaseManager', "Slow query detected: {$executionTime}s - {$description}", [
                        'sql' => $sql,
                        'params' => $params,
                        'execution_time' => $executionTime
                    ]);
                }
            }

            return $stmt;
        } catch (PDOException $e) {
            if ($this->logger) {
                $this->logger->error('DatabaseManager', "Query failed: " . $e->getMessage(), [
                    'sql' => $sql,
                    'params' => $params,
                    'description' => $description
                ]);
            }
            throw $e;
        }
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Get single record
     */
    public function findOne($sql, $params = [], $description = '') {
        $stmt = $this->execute($sql, $params, $description);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get multiple records
     */
    public function findAll($sql, $params = [], $description = '') {
        $stmt = $this->execute($sql, $params, $description);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert record with audit trail
     */
    public function insert($table, $data, $userId = null) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->execute($sql, $values, "Insert into {$table}");

        $recordId = $this->pdo->lastInsertId();

        // Create audit trail
        $this->createAuditTrail($table, $recordId, 'INSERT', $userId, null, $data);

        return $recordId;
    }

    /**
     * Update record with audit trail
     */
    public function update($table, $data, $conditions, $conditionParams = [], $userId = null) {
        // Get old values for audit trail
        $oldData = $this->getOldValues($table, $conditions, $conditionParams);

        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $params[] = $value;
        }

        $params = array_merge($params, $conditionParams);

        $whereClause = implode(' AND ', array_map(function($key) {
            return "{$key} = ?";
        }, array_keys($conditions)));

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . "
                WHERE {$whereClause}";

        $stmt = $this->execute($sql, $params, "Update {$table}");

        // Get new values for audit trail
        $newData = $this->getNewValues($table, $conditions, $conditionParams);

        // Create audit trail
        $this->createAuditTrail($table, $conditions[array_key_first($conditions)], 'UPDATE', $userId, $oldData, $newData);

        return $stmt->rowCount();
    }

    /**
     * Delete record with audit trail
     */
    public function delete($table, $conditions, $conditionParams = [], $userId = null) {
        // Get old values for audit trail
        $oldData = $this->getOldValues($table, $conditions, $conditionParams);

        $whereClause = implode(' AND ', array_map(function($key) {
            return "{$key} = ?";
        }, array_keys($conditions)));

        $sql = "DELETE FROM {$table} WHERE {$whereClause}";

        $stmt = $this->execute($sql, $conditionParams, "Delete from {$table}");

        // Create audit trail
        $this->createAuditTrail($table, $conditions[array_key_first($conditions)], 'DELETE', $userId, $oldData, null);

        return $stmt->rowCount();
    }

    /**
     * Get old values for audit trail
     */
    private function getOldValues($table, $conditions, $conditionParams) {
        $whereClause = implode(' AND ', array_map(function($key) {
            return "{$key} = ?";
        }, array_keys($conditions)));

        $sql = "SELECT * FROM {$table} WHERE {$whereClause}";
        $stmt = $this->execute($sql, $conditionParams, "Get old values for audit");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get new values for audit trail
     */
    private function getNewValues($table, $conditions, $conditionParams) {
        $whereClause = implode(' AND ', array_map(function($key) {
            return "{$key} = ?";
        }, array_keys($conditions)));

        $sql = "SELECT * FROM {$table} WHERE {$whereClause}";
        $stmt = $this->execute($sql, $conditionParams, "Get new values for audit");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create audit trail entry
     */
    private function createAuditTrail($table, $recordId, $action, $userId, $oldValues, $newValues) {
        try {
            $this->execute(
                "INSERT INTO audit_trail (table_name, record_id, action, user_id, old_values, new_values, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $table,
                    $recordId,
                    $action,
                    $userId,
                    $oldValues ? json_encode($oldValues) : null,
                    $newValues ? json_encode($newValues) : null,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ],
                "Create audit trail"
            );
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('DatabaseManager', 'Failed to create audit trail: ' . $e->getMessage());
            }
        }
    }

    /**
     * Search with full-text capabilities
     */
    public function search($table, $searchTerm, $columns = [], $limit = 50) {
        if (empty($columns)) {
            $columns = ['*'];
        }

        $columnList = implode(', ', $columns);
        $searchPattern = '%' . $searchTerm . '%';

        $sql = "SELECT {$columnList} FROM {$table}
                WHERE " . implode(' OR ', array_map(function($col) {
                    return "{$col} LIKE ?";
                }, $columns));

        $params = array_fill(0, count($columns), $searchPattern);

        return $this->findAll($sql . " LIMIT {$limit}", $params, "Search {$table}");
    }

    /**
     * Get paginated results
     */
    public function paginate($sql, $params = [], $page = 1, $perPage = 20, $description = '') {
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countSql = preg_replace('/SELECT.*?FROM/i', 'SELECT COUNT(*) FROM', $sql);
        $totalResult = $this->findOne($countSql, $params, $description . ' - Count');
        $total = $totalResult['COUNT(*)'] ?? 0;

        // Get paginated data
        $paginatedSql = $sql . " LIMIT {$perPage} OFFSET {$offset}";
        $data = $this->findAll($paginatedSql, $params, $description . ' - Paginated');

        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1
            ]
        ];
    }

    /**
     * Backup table data
     */
    public function backupTable($table, $backupTable = null) {
        if (!$backupTable) {
            $backupTable = $table . '_backup_' . date('Y_m_d_H_i_s');
        }

        try {
            // Create backup table
            $this->execute(
                "CREATE TABLE {$backupTable} AS SELECT * FROM {$table}",
                [],
                "Create backup of {$table}"
            );

            // Log backup operation
            if ($this->logger) {
                $this->logger->info('DatabaseManager', "Table {$table} backed up to {$backupTable}");
            }

            return $backupTable;
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('DatabaseManager', 'Backup failed: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Optimize table
     */
    public function optimizeTable($table) {
        try {
            $this->execute("OPTIMIZE TABLE {$table}", [], "Optimize {$table}");

            if ($this->logger) {
                $this->logger->info('DatabaseManager', "Table {$table} optimized");
            }
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->warning('DatabaseManager', 'Table optimization failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get database statistics
     */
    public function getStats() {
        $stats = [];

        try {
            // Table sizes
            $tables = $this->findAll("SHOW TABLE STATUS");
            $stats['tables'] = count($tables);
            $stats['total_size'] = array_sum(array_column($tables, 'Data_length')) +
                                 array_sum(array_column($tables, 'Index_length'));

            // Query statistics
            $stats['query_count'] = $this->queryCount;
            $stats['total_query_time'] = $this->queryTime;
            $stats['avg_query_time'] = $this->queryCount > 0 ? $this->queryTime / $this->queryCount : 0;

            // Slow queries count
            $stats['slow_queries'] = $this->queryTime > $this->slowQueryThreshold ? 1 : 0;

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('DatabaseManager', 'Failed to get statistics: ' . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Clean up old audit trails
     */
    public function cleanupAuditTrail($days = 90) {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $this->execute(
                "DELETE FROM audit_trail WHERE timestamp < ?",
                [$cutoffDate],
                "Clean up old audit trails"
            );

            if ($this->logger) {
                $this->logger->info('DatabaseManager', "Cleaned up audit trails older than {$days} days");
            }
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('DatabaseManager', 'Audit trail cleanup failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics() {
        return [
            'query_count' => $this->queryCount,
            'total_query_time' => $this->queryTime,
            'average_query_time' => $this->queryCount > 0 ? $this->queryTime / $this->queryCount : 0,
            'slow_query_threshold' => $this->slowQueryThreshold,
            'database_stats' => $this->getStats()
        ];
    }
}