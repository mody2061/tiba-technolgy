<?php
// Same settings as register.php
const DB_HOST  = '127.0.0.1';
const DB_NAME  = 'mysql';
const DB_USER  = 'root';
const DB_PASS  = '';
const DB_TABLE = '`project data`';

header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'Use POST.']);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$id       = trim($in['id'] ?? '');
$password = (string)($in['password'] ?? '');
$mode     = (($in['mode'] ?? '') === 'admin') ? 'admin' : 'student';

if ($id === '' || $password === '') out(422, ['error' => 'Enter your ID and password.']);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare('SELECT * FROM ' . DB_TABLE . ' WHERE `ID` = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Same message for a wrong ID or a wrong password
    // LEARNING ONLY: plain-text comparison.
    // For a real site use: password_verify($password, $user['Password'])
    if (!$user || !hash_equals((string)$user['Password'], $password)) {
        out(401, ['error' => 'Wrong ID or password.']);
    }

    $role = $user['role'] ?? 'student';
    if ($mode === 'admin' && $role !== 'admin')   out(403, ['error' => 'This account is not an admin account.']);
    if ($mode === 'student' && $role === 'admin') out(403, ['error' => 'Admin accounts must use the Admin tab.']);

    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
    session_regenerate_id(true);
    $_SESSION['student_id']   = $id;
    $_SESSION['student_name'] = $user['name'];
    $_SESSION['role']         = $role;

    out(200, ['ok' => true, 'name' => $user['name'], 'role' => $role]);
} catch (PDOException $e) {
    out(500, ['error' => 'Database error: ' . $e->getMessage()]);
}