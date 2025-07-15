<?php
// api/admin_handler.php - Final Production Version
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
require_once 'db_connect.php';

function send_json_error($message, $code = 500, $details = "") {
    http_response_code($code);
    $response = ["success" => false, "error" => $message];
    if (!empty($details)) { $response['details'] = $details; }
    echo json_encode($response);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // ✨ FIX: เพิ่มการดึงข้อมูล permissions
            $stmt = $conn->prepare("SELECT userId, prefix, name, surname, employeeId, position, phone, email, username, role, resetStatus, permissions FROM Users ORDER BY role ASC, name ASC");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // แปลง permissions จาก JSON string เป็น PHP array
            foreach ($users as &$user) {
                $user['permissions'] = json_decode($user['permissions'] ?? '[]') ?? [];
            }
            unset($user);

            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'add':
            $userData = $input['userData'];
            $newId = getUuid();

            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
            $stmtCheck->execute([$userData['username']]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว', 409);
            }

            // ✨ FIX: จัดการข้อมูล permissions
            $permissionsJson = isset($userData['permissions']) && is_array($userData['permissions']) ? json_encode($userData['permissions']) : null;

            $sql = "INSERT INTO Users (userId, prefix, name, surname, employeeId, position, phone, email, username, password, role, permissions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $newId, $userData['prefix'], $userData['name'], $userData['surname'], $userData['employeeId'] ?? null,
                $userData['position'], $userData['phone'] ?? null, $userData['email'] ?? null,
                $userData['username'], $userData['password'], $userData['role'], $permissionsJson
            ]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลผู้ใช้งานสำเร็จ']);
            break;

        case 'update':
            $userData = $input['userData'];
            $userId = $userData['userId'];

            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Users WHERE username = ? AND userId != ?");
            $stmtCheck->execute([$userData['username'], $userId]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว', 409);
            }

            // ✨ FIX: จัดการข้อมูล permissions
            $permissionsJson = isset($userData['permissions']) && is_array($userData['permissions']) ? json_encode($userData['permissions']) : null;
            // ล้างค่า permissions ถ้าบทบาทไม่ใช่ครู
            if ($userData['role'] !== 'teacher') {
                $permissionsJson = null;
            }

            // จัดการเรื่องรหัสผ่าน: ถ้าไม่ได้ส่งรหัสผ่านใหม่มา ก็ไม่ต้องอัปเดต
            if (!empty($userData['password'])) {
                $sql = "UPDATE Users SET prefix=?, name=?, surname=?, employeeId=?, position=?, phone=?, email=?, username=?, password=?, role=?, permissions=? WHERE userId=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $userData['prefix'], $userData['name'], $userData['surname'], $userData['employeeId'] ?? null,
                    $userData['position'], $userData['phone'] ?? null, $userData['email'] ?? null,
                    $userData['username'], $userData['password'], $userData['role'], $permissionsJson, $userId
                ]);
            } else {
                // อัปเดตทุกอย่างยกเว้นรหัสผ่าน
                $sql = "UPDATE Users SET prefix=?, name=?, surname=?, employeeId=?, position=?, phone=?, email=?, username=?, role=?, permissions=? WHERE userId=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $userData['prefix'], $userData['name'], $userData['surname'], $userData['employeeId'] ?? null,
                    $userData['position'], $userData['phone'] ?? null, $userData['email'] ?? null,
                    $userData['username'], $userData['role'], $permissionsJson, $userId
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลผู้ใช้งานสำเร็จ']);
            break;

        case 'delete':
            $userId = $input['userId'];
            $stmt = $conn->prepare("DELETE FROM Users WHERE userId = ?");
            $stmt->execute([$userId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'ลบข้อมูลผู้ใช้งานสำเร็จ']);
            } else {
                send_json_error('ไม่พบผู้ใช้งานที่ต้องการลบหรือไม่ได้รับอนุญาต', 404);
            }
            break;

        case 'reset_password':
            $payload = $input['payload'] ?? null;
            if (!$payload || !isset($payload['userId']) || !isset($payload['newPassword'])) {
                send_json_error('ข้อมูลไม่ครบถ้วนสำหรับการรีเซ็ตรหัสผ่าน', 400);
            }
            $sql = "UPDATE Users SET password = ?, resetStatus = NULL WHERE userId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$payload['newPassword'], $payload['userId']]);
            echo json_encode(['success' => true, 'message' => 'ตั้งรหัสผ่านใหม่สำเร็จ']);
            break;

        default:
            send_json_error('Invalid action for user handler.', 400);
            break;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        send_json_error('ข้อมูลซ้ำ: ' . $e->getMessage(), 409);
    } else {
        send_json_error('Database error: ' . $e->getMessage(), 500);
    }
} catch (Exception $e) {
    send_json_error('Server error: ' . $e->getMessage(), 500);
}
?>