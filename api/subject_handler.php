<?php
// school-management/api/subject_handler.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
require_once 'db_connect.php';

function send_json_error($message, $code = 500) {
    http_response_code($code);
    echo json_encode(["success" => false, "error" => $message]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // ดึงข้อมูลรายวิชาทั้งหมด
            $stmt = $conn->prepare("SELECT subjectId, subjectCode, subjectName, credits, subjectType FROM Subjects ORDER BY subjectCode ASC");
            $stmt->execute();
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'subjects' => $subjects]);
            break;

        case 'add':
            $data = $input['subjectData'];
            if (empty($data['subjectCode']) || empty($data['subjectName']) || empty($data['subjectType'])) {
                send_json_error('กรุณากรอกข้อมูลรายวิชาให้ครบถ้วน', 400);
            }

            // --- START: ✨ นี่คือส่วนที่แก้ไขปัญหา ---
            // ตรวจสอบค่า 'credits' ที่ส่งมา: ถ้าไม่มีค่า หรือเป็นสตริงว่าง ให้แปลงเป็น NULL
            $credits = (isset($data['credits']) && $data['credits'] !== '') ? $data['credits'] : null;
            // --- END: ✨ นี่คือส่วนที่แก้ไขปัญหา ---

            $sql = "INSERT INTO Subjects (subjectId, subjectCode, subjectName, credits, subjectType) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            // ใช้ตัวแปร $credits ที่ผ่านการตรวจสอบแล้วในการบันทึก
            $stmt->execute([getUuid(), $data['subjectCode'], $data['subjectName'], $credits, $data['subjectType']]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลรายวิชาสำเร็จ']);
            break;

        case 'update':
            $data = $input['subjectData'];
            if (empty($data['subjectId']) || empty($data['subjectCode']) || empty($data['subjectName']) || empty($data['subjectType'])) {
                send_json_error('กรุณากรอกข้อมูลรายวิชาให้ครบถ้วน', 400);
            }

            // --- START: ✨ นี่คือส่วนที่แก้ไขปัญหา ---
            // ตรวจสอบค่า 'credits' ที่ส่งมา: ถ้าไม่มีค่า หรือเป็นสตริงว่าง ให้แปลงเป็น NULL
            $credits = (isset($data['credits']) && $data['credits'] !== '') ? $data['credits'] : null;
            // --- END: ✨ นี่คือส่วนที่แก้ไขปัญหา ---
            
            $sql = "UPDATE Subjects SET subjectCode=?, subjectName=?, credits=?, subjectType=? WHERE subjectId=?";
            $stmt = $conn->prepare($sql);
            // ใช้ตัวแปร $credits ที่ผ่านการตรวจสอบแล้วในการบันทึก
            $stmt->execute([$data['subjectCode'], $data['subjectName'], $credits, $data['subjectType'], $data['subjectId']]);
            echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลรายวิชาสำเร็จ']);
            break;

        case 'delete':
            $subjectId = $input['subjectId'];
            if (empty($subjectId)) {
                send_json_error('Subject ID is missing.', 400);
            }
            $stmt = $conn->prepare("DELETE FROM Subjects WHERE subjectId = ?");
            $stmt->execute([$subjectId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'ลบข้อมูลรายวิชาสำเร็จ']);
            } else {
                send_json_error('ไม่พบรายวิชาที่ต้องการลบ', 404);
            }
            break;

        default:
            send_json_error('Invalid action for subject handler.', 400);
            break;
    }
} catch (PDOException $e) {
    // Check for duplicate entry error
    if ($e->getCode() == 23000) {
        send_json_error('รหัสวิชานี้มีอยู่ในระบบแล้ว', 409); // 409 Conflict
    } else {
        send_json_error('Database error: ' . $e->getMessage(), 500);
    }
}
?>