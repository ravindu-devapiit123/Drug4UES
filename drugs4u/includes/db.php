<?php
require_once __DIR__ . '/config.php';

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function query(string $sql, string $types = '', ...$params): mysqli_result|bool {
    $db  = getDB();
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error . " | SQL: $sql");
        return false;
    }
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result !== false ? $result : true;
}

function fetchAll(string $sql, string $types = '', ...$params): array {
    $res = query($sql, $types, ...$params);
    if (!$res || $res === true) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

function fetchOne(string $sql, string $types = '', ...$params): ?array {
    $res = query($sql, $types, ...$params);
    if (!$res || $res === true) return null;
    $row = $res->fetch_assoc();
    return $row ?: null;
}

function execute(string $sql, string $types = '', ...$params): int {
    $db   = getDB();
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->affected_rows;
}

function lastInsertId(): int {
    return getDB()->insert_id;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}
