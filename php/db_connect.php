<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 8: PDO Database Connection & Configuration
 * ============================================================================
 * 
 * Features:
 * - Robust PDO (PHP Data Objects) connection to MySQL.
 * - Enforces UTF-8 multi-byte (utf8mb4) encoding.
 * - Disables emulation of prepared statements to guarantee native server-side
 *   parameterized queries (Complete SQL Injection Defense).
 * - Enables PDO::ERRMODE_EXCEPTION for reliable error catching.
 * - Provides diagnostic helper to test connection status.
 * ============================================================================
 */

// Database Configuration Constants
defined('DB_HOST')     || define('DB_HOST', 'localhost');
defined('DB_PORT')     || define('DB_PORT', '3306');
defined('DB_NAME')     || define('DB_NAME', 'studenthub_db');
defined('DB_USER')     || define('DB_USER', 'root');
defined('DB_PASS')     || define('DB_PASS', '');
defined('DB_CHARSET')  || define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a configured PDO database connection instance.
 *
 * @param bool $throwExceptions Whether to re-throw exceptions to caller
 * @return PDO|null Returns PDO instance on success, or null on failure
 * @throws PDOException
 */
function getDBConnection(bool $throwExceptions = false): ?PDO {
    static $pdoInstance = null;

    if ($pdoInstance !== null) {
        return $pdoInstance;
    }

    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Guarantee native server-side prepared statements
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdoInstance = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdoInstance;
    } catch (PDOException $e) {
        error_log("StudentHub Database Connection Error: " . $e->getMessage());

        if ($throwExceptions) {
            throw $e;
        }

        return null;
    }
}

/**
 * Diagnostic helper to test the MySQL connection and retrieve server metadata.
 *
 * @return array Diagnostic result with success flag, server info, or error details
 */
function testDBConnection(): array {
    $startTime = microtime(true);

    try {
        $pdo = getDBConnection(true);

        if ($pdo instanceof PDO) {
            $versionStmt = $pdo->query("SELECT VERSION() AS mysql_version, DATABASE() AS active_db");
            $serverInfo  = $versionStmt->fetch();
            $latencyMs   = round((microtime(true) - $startTime) * 1000, 2);

            // Fetch table list and counts
            $tablesStmt = $pdo->query("SHOW TABLES");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

            $tableStats = [];
            foreach ($tables as $table) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM `" . str_replace("`", "``", $table) . "`");
                $countStmt->execute();
                $countResult = $countStmt->fetch();
                $tableStats[$table] = (int)($countResult['total'] ?? 0);
            }

            return [
                'success'       => true,
                'message'       => 'Connected to MySQL successfully using PDO.',
                'database'      => $serverInfo['active_db'] ?? DB_NAME,
                'version'       => $serverInfo['mysql_version'] ?? 'Unknown',
                'host'          => DB_HOST . ':' . DB_PORT,
                'latency_ms'    => $latencyMs,
                'driver'        => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
                'tables'        => $tableStats,
                'prepared_mode' => 'Native Server-Side (Emulation Disabled)'
            ];
        }

        return [
            'success' => false,
            'message' => 'Unable to establish PDO connection.',
            'error'   => 'PDO returned null.'
        ];
    } catch (PDOException $e) {
        return [
            'success'    => false,
            'message'    => 'Connection failed.',
            'error_code' => $e->getCode(),
            'error'      => $e->getMessage(),
            'dsn_hint'   => sprintf("mysql:host=%s;port=%s;dbname=%s", DB_HOST, DB_PORT, DB_NAME)
        ];
    }
}
