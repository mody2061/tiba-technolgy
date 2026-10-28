<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
function out(int $c, array $d): void { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['student_id'])) out(401, ['error' => 'Please sign in again.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'Use POST.']);

$in   = json_decode(file_get_contents('php://input'), true) ?: [];
$code = strtoupper(trim($in['code'] ?? ''));

try {
    if (!attendance_open()) out(409, ['error' => 'Attendance is closed.']);
    $valid = $code !== '' && ($code === setting('attendance_code') || $code === setting('attendance_prev'));
    if (!$valid) out(422, ['error' => 'Wrong or expired code. Scan the barcode again.']);

    $ins = db()->prepare('INSERT IGNORE INTO attendance_log (student_id, att_date, att_time) VALUES (?, CURDATE(), CURTIME())');
    $ins->execute([$_SESSION['student_id']]);
    out(200, ['name' => $_SESSION['student_name'], 'repeat' => $ins->rowCount() === 0]);
} catch (PDOException $e) {
    out(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
