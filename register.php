<?php
// Laragon default MySQL settings. Change them if yours are different.
const DB_HOST  = '127.0.0.1';
const DB_NAME  = 'mysql';          // the database shown in HeidiSQL
const DB_USER  = 'root';
const DB_PASS  = '';
const DB_TABLE = '`project data`'; // table name has a space, so it needs backticks

header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'Use POST.']);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$name     = trim($in['name'] ?? '');
$id       = trim($in['id'] ?? '');
$email    = trim($in['email'] ?? '');
$faculty  = trim($in['faculty'] ?? '');
$password = (string)($in['password'] ?? '');

// Server-side checks (never trust the browser only)
if (mb_strlen($name) < 3)                       out(422, ['error' => 'Enter your full name.']);
if (!preg_match('/^\d{14}$/', $id))           out(422, ['error' => 'Your ID must be exactly 14 digits.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['error' => 'Enter a valid email address.']);
if (strlen($password) < 8 || !preg_match('/\d/', $password) || !preg_match('/[A-Za-z]/', $password))
    out(422, ['error' => 'Password must be 8+ characters with letters and numbers.']);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Is this ID already registered?
    $check = $pdo->prepare('SELECT 1 FROM ' . DB_TABLE . ' WHERE `ID` = ? LIMIT 1');
    $check->execute([$id]);
    if ($check->fetch()) out(409, ['error' => 'This ID is already registered.']);

    // Save the password as a secure hash, never as plain text
    // LEARNING ONLY: saved as plain text so it is visible in HeidiSQL.
    // For a real site use: $hash = password_hash($password, PASSWORD_DEFAULT);
    $hash = $password;

    // Only save faculty if the table has a `faculty` column
    $hasFaculty = (bool)$pdo->query('SHOW COLUMNS FROM ' . DB_TABLE . " LIKE 'faculty'")->fetch();

    if ($hasFaculty) {
        $sql = 'INSERT INTO ' . DB_TABLE . ' (`name`, `ID`, `Password`, `email`, `faculty`) VALUES (?, ?, ?, ?, ?)';
        $pdo->prepare($sql)->execute([$name, $id, $hash, $email, $faculty]);
    } else {
        $sql = 'INSERT INTO ' . DB_TABLE . ' (`name`, `ID`, `Password`, `email`) VALUES (?, ?, ?, ?)';
        $pdo->prepare($sql)->execute([$name, $id, $hash, $email]);
    }

    out(201, ['ok' => true]);
} catch (PDOException $e) {
    // While developing, show the real error. Hide it on a live website.
    out(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
