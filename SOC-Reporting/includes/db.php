<?php
/**
 * SOC Reporting System - Database Helper
 * PDO-based connections with prepared statements only.
 */

require_once __DIR__ . '/../config/database.php';

class Database {
    private static $connections = [];

    /**
     * Get a PDO connection for the specified database.
     */
    public static function getConnection(string $dbName): PDO {
        if (!isset(self::$connections[$dbName])) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, $dbName, DB_CHARSET
            );
            self::$connections[$dbName] = new PDO($dsn, DB_USER, DB_PASS, unserialize(DB_OPTIONS));
        }
        return self::$connections[$dbName];
    }

    /**
     * Get system database connection.
     */
    public static function system(): PDO {
        return self::getConnection(DB_SYSTEM);
    }

    /**
     * Get machine database connection by slot number (1-4).
     */
    public static function machine(int $slot): PDO {
        if ($slot < 1 || $slot > MACHINE_SLOTS) {
            throw new InvalidArgumentException("Invalid machine slot: $slot");
        }
        $dbName = constant('DB_MACHINE_' . $slot);
        return self::getConnection($dbName);
    }

    /**
     * Execute a prepared query and return the statement.
     */
    public static function query(PDO $pdo, string $sql, array $params = []): PDOStatement {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row.
     */
    public static function fetchOne(PDO $pdo, string $sql, array $params = []): ?array {
        $stmt = self::query($pdo, $sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch all rows.
     */
    public static function fetchAll(PDO $pdo, string $sql, array $params = []): array {
        $stmt = self::query($pdo, $sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert a row and return the last insert ID.
     */
    public static function insert(PDO $pdo, string $table, array $data): string {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        self::query($pdo, $sql, array_values($data));
        return $pdo->lastInsertId();
    }

    /**
     * Update rows and return affected count.
     */
    public static function update(PDO $pdo, string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(', ', array_map(fn($col) => "$col = ?", array_keys($data)));
        $sql = "UPDATE $table SET $set WHERE $where";
        $stmt = self::query($pdo, $sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    /**
     * Close all connections.
     */
    public static function closeAll(): void {
        self::$connections = [];
    }
}
