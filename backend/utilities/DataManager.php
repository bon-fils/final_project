<?php
/**
 * Data Management Utility
 * Handles data export, import, backup, and migration operations
 * Version: 2.0 - Enhanced with security and performance features
 */

class DataManager {
    private $db;
    private $logger;
    private $exportFormats = ['csv', 'json', 'xml', 'sql'];
    private $maxExportSize = 50 * 1024 * 1024; // 50MB

    public function __construct($pdo, $logger = null) {
        $this->db = $pdo;
        $this->logger = $logger;
    }

    /**
     * Export data to various formats
     */
    public function exportData($table, $format = 'csv', $conditions = [], $filename = null) {
        if (!in_array($format, $this->exportFormats)) {
            throw new Exception("Unsupported export format: {$format}");
        }

        // Get data
        $data = $this->getTableData($table, $conditions);

        if (empty($data)) {
            throw new Exception("No data found for export");
        }

        // Generate filename if not provided
        if (!$filename) {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "{$table}_export_{$timestamp}.{$format}";
        }

        // Export based on format
        switch ($format) {
            case 'csv':
                return $this->exportToCSV($data, $filename);
            case 'json':
                return $this->exportToJSON($data, $filename);
            case 'xml':
                return $this->exportToXML($data, $filename, $table);
            case 'sql':
                return $this->exportToSQL($data, $filename, $table);
            default:
                throw new Exception("Export format not implemented: {$format}");
        }
    }

    /**
     * Import data from various formats
     */
    public function importData($table, $format = 'csv', $filePath, $options = []) {
        if (!file_exists($filePath)) {
            throw new Exception("Import file not found: {$filePath}");
        }

        if (!in_array($format, $this->exportFormats)) {
            throw new Exception("Unsupported import format: {$format}");
        }

        // Validate file size
        if (filesize($filePath) > $this->maxExportSize) {
            throw new Exception("Import file too large. Maximum size: " . ($this->maxExportSize / 1024 / 1024) . "MB");
        }

        // Import based on format
        switch ($format) {
            case 'csv':
                return $this->importFromCSV($table, $filePath, $options);
            case 'json':
                return $this->importFromJSON($table, $filePath, $options);
            case 'xml':
                return $this->importFromXML($table, $filePath, $options);
            case 'sql':
                return $this->importFromSQL($table, $filePath, $options);
            default:
                throw new Exception("Import format not implemented: {$format}");
        }
    }

    /**
     * Get table data with conditions
     */
    private function getTableData($table, $conditions = []) {
        $sql = "SELECT * FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $whereParts[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        $sql .= " ORDER BY id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export to CSV format
     */
    private function exportToCSV($data, $filename) {
        $output = fopen('php://temp', 'w');

        if (empty($data)) {
            throw new Exception("No data to export");
        }

        // Write headers
        fputcsv($output, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvData = stream_get_contents($output);
        fclose($output);

        // Save to file
        $this->saveExportFile($filename, $csvData);

        return [
            'filename' => $filename,
            'size' => strlen($csvData),
            'records' => count($data),
            'format' => 'csv'
        ];
    }

    /**
     * Export to JSON format
     */
    private function exportToJSON($data, $filename) {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->saveExportFile($filename, $jsonData);

        return [
            'filename' => $filename,
            'size' => strlen($jsonData),
            'records' => count($data),
            'format' => 'json'
        ];
    }

    /**
     * Export to XML format
     */
    private function exportToXML($data, $filename, $tableName) {
        $xml = new SimpleXMLElement('<' . $tableName . 's/>');

        foreach ($data as $row) {
            $record = $xml->addChild($tableName);

            foreach ($row as $key => $value) {
                // Skip null values or empty strings
                if ($value !== null && $value !== '') {
                    $record->addChild($key, htmlspecialchars((string)$value));
                }
            }
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        $xmlData = $dom->saveXML();
        $this->saveExportFile($filename, $xmlData);

        return [
            'filename' => $filename,
            'size' => strlen($xmlData),
            'records' => count($data),
            'format' => 'xml'
        ];
    }

    /**
     * Export to SQL format
     */
    private function exportToSQL($data, $filename, $tableName) {
        $sqlData = "-- Export of {$tableName} table\n";
        $sqlData .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

        if (empty($data)) {
            $sqlData .= "-- No data to export\n";
        } else {
            $columns = array_keys($data[0]);

            foreach ($data as $row) {
                $values = array_map(function($value) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $this->db->quote((string)$value);
                }, array_values($row));

                $sqlData .= "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
        }

        $this->saveExportFile($filename, $sqlData);

        return [
            'filename' => $filename,
            'size' => strlen($sqlData),
            'records' => count($data),
            'format' => 'sql'
        ];
    }

    /**
     * Save export file
     */
    private function saveExportFile($filename, $data) {
        $exportDir = 'exports/';

        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filepath = $exportDir . $filename;

        if (file_put_contents($filepath, $data) === false) {
            throw new Exception("Failed to save export file: {$filepath}");
        }

        // Set appropriate permissions
        chmod($filepath, 0644);

        if ($this->logger) {
            $this->logger->info('DataManager', 'Export file saved', [
                'filename' => $filename,
                'size' => strlen($data)
            ]);
        }
    }

    /**
     * Import from CSV format
     */
    private function importFromCSV($table, $filePath, $options = []) {
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new Exception("Cannot open CSV file: {$filePath}");
        }

        // Get headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception("Invalid CSV format: no headers found");
        }

        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = array_combine($headers, $row);

                // Apply transformations if specified
                if (isset($options['transform']) && is_callable($options['transform'])) {
                    $data = $options['transform']($data);
                }

                // Insert data
                $this->insertRecord($table, $data);
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Row " . ($imported + 2) . ": " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => $imported + count($errors)
        ];
    }

    /**
     * Import from JSON format
     */
    private function importFromJSON($table, $filePath, $options = []) {
        $jsonContent = file_get_contents($filePath);

        if (!$jsonContent) {
            throw new Exception("Cannot read JSON file: {$filePath}");
        }

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON format: " . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new Exception("JSON data must be an array");
        }

        $imported = 0;
        $errors = [];

        foreach ($data as $index => $row) {
            try {
                // Apply transformations if specified
                if (isset($options['transform']) && is_callable($options['transform'])) {
                    $row = $options['transform']($row);
                }

                $this->insertRecord($table, $row);
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => $imported + count($errors)
        ];
    }

    /**
     * Import from XML format
     */
    private function importFromXML($table, $filePath, $options = []) {
        $xmlContent = file_get_contents($filePath);

        if (!$xmlContent) {
            throw new Exception("Cannot read XML file: {$filePath}");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if (!$xml) {
            throw new Exception("Invalid XML format: " . implode(', ', libxml_get_errors()));
        }

        $imported = 0;
        $errors = [];

        foreach ($xml->children() as $row) {
            try {
                $data = [];

                foreach ($row->children() as $field => $value) {
                    $data[$field] = (string)$value;
                }

                // Apply transformations if specified
                if (isset($options['transform']) && is_callable($options['transform'])) {
                    $data = $options['transform']($data);
                }

                $this->insertRecord($table, $data);
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Row " . ($imported + 1) . ": " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => $imported + count($errors)
        ];
    }

    /**
     * Import from SQL format
     */
    private function importFromSQL($table, $filePath, $options = []) {
        $sqlContent = file_get_contents($filePath);

        if (!$sqlContent) {
            throw new Exception("Cannot read SQL file: {$filePath}");
        }

        // Split SQL into individual statements
        $statements = $this->parseSQLStatements($sqlContent);

        $executed = 0;
        $errors = [];

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if (empty($statement) || strpos($statement, '--') === 0) {
                continue; // Skip comments and empty lines
            }

            try {
                $this->db->exec($statement);
                $executed++;
            } catch (Exception $e) {
                $errors[] = "Statement " . ($executed + 1) . ": " . $e->getMessage();
            }
        }

        return [
            'executed' => $executed,
            'errors' => $errors,
            'total_statements' => $executed + count($errors)
        ];
    }

    /**
     * Insert record into table
     */
    private function insertRecord($table, $data) {
        // Remove null values and empty strings
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });

        if (empty($data)) {
            throw new Exception("No valid data to insert");
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * Parse SQL statements
     */
    private function parseSQLStatements($sql) {
        $statements = [];
        $lines = explode("\n", $sql);
        $currentStatement = '';
        $inString = false;
        $stringChar = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (strpos($line, '--') === 0) {
                continue;
            }

            // Handle strings
            $chars = str_split($line);
            for ($i = 0; $i < count($chars); $i++) {
                $char = $chars[$i];

                if (($char === '"' || $char === "'") && ($i === 0 || $chars[$i-1] !== '\\')) {
                    if (!$inString) {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($char === $stringChar) {
                        $inString = false;
                        $stringChar = '';
                    }
                }

                $currentStatement .= $char;
            }

            // Check if statement is complete
            if (!$inString && (substr($currentStatement, -1) === ';' || substr($line, -1) === ';')) {
                $statements[] = $currentStatement;
                $currentStatement = '';
            } else {
                $currentStatement .= "\n";
            }
        }

        // Add remaining statement if any
        if (!empty($currentStatement)) {
            $statements[] = $currentStatement;
        }

        return $statements;
    }

    /**
     * Create database backup
     */
    public function createBackup($tables = [], $filename = null) {
        if (empty($tables)) {
            // Get all tables
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (!$filename) {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "database_backup_{$timestamp}.sql";
        }

        $backup = "-- Database Backup\n";
        $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Tables: " . implode(', ', $tables) . "\n\n";

        foreach ($tables as $table) {
            $backup .= $this->backupTable($table);
        }

        $this->saveExportFile($filename, $backup);

        return [
            'filename' => $filename,
            'tables' => $tables,
            'size' => strlen($backup)
        ];
    }

    /**
     * Backup single table
     */
    private function backupTable($table) {
        $backup = "-- Backup of {$table} table\n";

        // Get table structure
        $stmt = $this->db->query("SHOW CREATE TABLE {$table}");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($createTable) {
            $backup .= "DROP TABLE IF EXISTS {$table};\n";
            $backup .= $createTable['Create Table'] . ";\n\n";
        }

        // Get table data
        $data = $this->getTableData($table);

        if (!empty($data)) {
            $columns = array_keys($data[0]);

            foreach ($data as $row) {
                $values = array_map(function($value) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $this->db->quote((string)$value);
                }, array_values($row));

                $backup .= "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
        }

        $backup .= "\n";
        return $backup;
    }

    /**
     * Validate import file
     */
    public function validateImportFile($filePath, $format) {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'metadata' => []
        ];

        if (!file_exists($filePath)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'File does not exist';
            return $validation;
        }

        $fileSize = filesize($filePath);
        $validation['metadata']['size'] = $fileSize;
        $validation['metadata']['format'] = $format;

        if ($fileSize > $this->maxExportSize) {
            $validation['valid'] = false;
            $validation['errors'][] = 'File size exceeds maximum allowed size';
        }

        if ($fileSize === 0) {
            $validation['valid'] = false;
            $validation['errors'][] = 'File is empty';
        }

        // Format-specific validation
        switch ($format) {
            case 'csv':
                $validation = array_merge($validation, $this->validateCSV($filePath));
                break;
            case 'json':
                $validation = array_merge($validation, $this->validateJSON($filePath));
                break;
            case 'xml':
                $validation = array_merge($validation, $this->validateXML($filePath));
                break;
        }

        return $validation;
    }

    /**
     * Validate CSV file
     */
    private function validateCSV($filePath) {
        $validation = ['errors' => [], 'warnings' => []];

        $handle = fopen($filePath, 'r');

        if (!$handle) {
            $validation['errors'][] = 'Cannot open CSV file';
            return $validation;
        }

        // Check headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            $validation['errors'][] = 'No headers found in CSV file';
        } else {
            $validation['metadata']['columns'] = count($headers);
            $validation['metadata']['headers'] = $headers;
        }

        // Check data rows
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            if (count($row) !== count($headers)) {
                $validation['warnings'][] = "Row {$rowCount}: Column count mismatch";
            }
        }

        $validation['metadata']['rows'] = $rowCount;
        fclose($handle);

        return $validation;
    }

    /**
     * Validate JSON file
     */
    private function validateJSON($filePath) {
        $validation = ['errors' => [], 'warnings' => []];

        $content = file_get_contents($filePath);

        if (!$content) {
            $validation['errors'][] = 'Cannot read JSON file';
            return $validation;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $validation['errors'][] = 'Invalid JSON format: ' . json_last_error_msg();
            return $validation;
        }

        if (!is_array($data)) {
            $validation['errors'][] = 'JSON data must be an array';
            return $validation;
        }

        $validation['metadata']['records'] = count($data);

        if (!empty($data)) {
            $validation['metadata']['columns'] = count($data[0]);
        }

        return $validation;
    }

    /**
     * Validate XML file
     */
    private function validateXML($filePath) {
        $validation = ['errors' => [], 'warnings' => []];

        $content = file_get_contents($filePath);

        if (!$content) {
            $validation['errors'][] = 'Cannot read XML file';
            return $validation;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if (!$xml) {
            $validation['errors'][] = 'Invalid XML format: ' . implode(', ', libxml_get_errors());
            return $validation;
        }

        $validation['metadata']['records'] = count($xml->children());

        return $validation;
    }

    /**
     * Clean up old export files
     */
    public function cleanupExports($days = 7) {
        $exportDir = 'exports/';
        $cutoffTime = strtotime("-{$days} days");

        if (!is_dir($exportDir)) {
            return 0;
        }

        $files = glob($exportDir . '*');
        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        if ($this->logger) {
            $this->logger->info('DataManager', "Cleaned up {$deleted} old export files");
        }

        return $deleted;
    }
}