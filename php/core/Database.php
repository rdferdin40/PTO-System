<?php
/**
 * Database Connection Class
 * PDO-based database wrapper with connection pooling
 */

class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    /**
     * Connect to database
     */
    public static function connect(array $config): void
    {
        self::$config = $config;

        $dsn = sprintf(
            '%s:host=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get PDO connection instance
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            throw new Exception('Database not connected. Call Database::connect() first.');
        }

        return self::$connection;
    }

    /**
     * Execute a query and return statement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Fetch single row
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Insert and return last insert ID
     */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }

        self::query($sql, $params);
        return (int) self::getConnection()->lastInsertId();
    }

    /**
     * Update records
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        $params = [];

        foreach ($data as $key => $value) {
            $setParts[] = $key . ' = :' . $key;
            $params[':' . $key] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = array_merge($params, $whereParams);

        return self::query($sql, $params)->rowCount();
    }

    /**
     * Delete records
     */
    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): void
    {
        self::getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): void
    {
        self::getConnection()->rollBack();
    }

    /**
     * Check if in transaction
     */
    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }
}
