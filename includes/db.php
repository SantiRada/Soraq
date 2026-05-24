<?php
// ─────────────────────────────────────────────
// includes/db.php  –  PDO singleton
// ─────────────────────────────────────────────

require_once __DIR__ . '/../config/database.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                die('DB connection failed: ' . $e->getMessage());
            }
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'Database unavailable']));
        }
    }
    return $pdo;
}

// Shorthand execute
function dbq(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

// Fetch single row
function dbrow(string $sql, array $params = []): ?array {
    $row = dbq($sql, $params)->fetch();
    return $row ?: null;
}

// Fetch all rows
function dbrows(string $sql, array $params = []): array {
    return dbq($sql, $params)->fetchAll();
}

// Insert + return last insert id
function dbinsert(string $table, array $data): int|string {
    $cols   = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
    $places = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
    dbq("INSERT INTO `$table` ($cols) VALUES ($places)", $data);
    return db()->lastInsertId();
}

// Update by id
function dbupdate(string $table, array $data, string $where, array $whereParams = []): int {
    $sets = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($data)));
    return dbq("UPDATE `$table` SET $sets WHERE $where", array_merge($data, $whereParams))
        ->rowCount();
}
