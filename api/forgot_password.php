<?php
// api/forgot_password.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
require_once 'db_connect.php';

// Helper function for JSON error response
function send_json_error($message, $code = 500, $details = "") {
    http_response_code($code);
    $response = ["success" => false, "error" => $message];
    if (!empty($details)) { $response['details'] = $details; }
    echo json_encode($response);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? null;

if (!$username) {
    send_json_error('Username not provided.', 400);
}

try {
    // Check if user exists first
    $checkSql = "SELECT COUNT(*) FROM Users WHERE username = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$username]);
    if ($checkStmt->fetchColumn() == 0) {
        send_json_error('ไม่พบชื่อผู้ใช้งานนี้ในระบบ', 404);
    }

    // Update resetStatus to 'pending'
    $sql = "UPDATE Users SET resetStatus = 'pending' WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username]);

    echo json_encode(['success' => true, 'message' => 'ส่งคำขอรีเซ็ตรหัสผ่านสำเร็จแล้ว ผู้ดูแลระบบจะดำเนินการให้โดยเร็วที่สุด']);

} catch (PDOException $e) {
    send_json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    send_json_error('Server error: ' . $e->getMessage(), 500);
}
?>