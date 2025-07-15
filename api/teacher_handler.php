<?php
// api/teacher_handler.php
ini_set('display_errors', 1);
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
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // ดึงข้อมูลผู้ใช้งานทั้งหมดที่ role เป็น 'teacher'
            $stmt = $conn->prepare("SELECT userId, prefix, name, surname, employeeId, position, phone, email, username, role, resetStatus FROM Users WHERE role = 'teacher'");
            $stmt->execute();
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'teachers' => $teachers]);
            break;

        case 'add':
            $userData = $input['userData'];
            $newId = getUuid();

            // ตรวจสอบ username ซ้ำก่อนเพิ่ม
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
            $stmtCheck->execute([$userData['username']]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว', 409); // 409 Conflict
            }

            $sql = "INSERT INTO Users (userId, prefix, name, surname, employeeId, position, phone, email, username, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $newId, $userData['prefix'], $userData['name'], $userData['surname'], $userData['employeeId'] ?? null,
                $userData['position'], $userData['phone'] ?? null, $userData['email'] ?? null,
                $userData['username'], $userData['password'], 'teacher' // กำหนด role เป็น teacher โดยตรง
            ]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลครู/บุคลากรสำเร็จ']);
            break;

        case 'update':
            $userData = $input['userData'];
            $userId = $userData['userId'];

            // ตรวจสอบ username ซ้ำ ยกเว้น username ของตัวเอง
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Users WHERE username = ? AND userId != ?");
            $stmtCheck->execute([$userData['username'], $userId]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว', 409); // 409 Conflict
            }

            $sql = "UPDATE Users SET prefix=?, name=?, surname=?, employeeId=?, position=?, phone=?, email=?, username=?, password=?, role=? WHERE userId=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $userData['prefix'], $userData['name'], $userData['surname'], $userData['employeeId'] ?? null,
                $userData['position'], $userData['phone'] ?? null, $userData['email'] ?? null,
                $userData['username'], $userData['password'], $userData['role'], $userId // role สามารถอัปเดตได้จาก frontend
            ]);
            echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลครู/บุคลากรสำเร็จ']);
            break;

        case 'delete':
            $userId = $input['userId'];
            $stmt = $conn->prepare("DELETE FROM Users WHERE userId = ? AND role = 'teacher'");
            $stmt->execute([$userId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'ลบข้อมูลครู/บุคลากรสำเร็จ']);
            } else {
                send_json_error('ไม่พบครู/บุคลากรที่ต้องการลบหรือไม่ได้รับอนุญาต', 404);
            }
            break;
            
        case 'get_all_users': // Action to get all users regardless of role (for dropdowns etc.)
            $stmt = $conn->prepare("SELECT userId, prefix, name, surname, role FROM Users ORDER BY name ASC");
            $stmt->execute();
            $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'users' => $allUsers]);
            break;

        default:
            send_json_error('Invalid action for teacher handler.', 400);
            break;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        send_json_error('ข้อมูลซ้ำ: ชื่อผู้ใช้งานหรือเลขประจำตัวบุคลากรนี้มีอยู่ในระบบแล้ว', 409);
    } else {
        send_json_error('Database error: ' . $e->getMessage(), 500);
    }
} catch (Exception $e) {
    send_json_error('Server error: ' . $e->getMessage(), 500);
}
?>