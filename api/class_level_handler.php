<?php
// api/class_level_handler.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
require_once 'db_connect.php'; // Ensure db_connect.php is included for database connection and getUuid()

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
            // ดึงข้อมูลระดับชั้นทั้งหมด
            $stmt = $conn->prepare("SELECT * FROM ClassLevels ORDER BY className ASC");
            $stmt->execute();
            $classLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'classLevels' => $classLevels]);
            break;

        case 'add':
            $classData = $input['classData'];
            $newId = getUuid();

            // ตรวจสอบ className ซ้ำก่อนเพิ่ม
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM ClassLevels WHERE className = ?");
            $stmtCheck->execute([$classData['className']]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('ชื่อระดับชั้นนี้มีอยู่ในระบบแล้ว', 409); // 409 Conflict
            }

            $sql = "INSERT INTO ClassLevels (classLevelId, className, description) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $newId,
                $classData['className'],
                $classData['description'] ?? null
            ]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลระดับชั้นสำเร็จ']);
            break;

        case 'update':
            $classData = $input['classData'];
            $classLevelId = $classData['classLevelId'];

            // ตรวจสอบ className ซ้ำ ยกเว้น className ของตัวเอง
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM ClassLevels WHERE className = ? AND classLevelId != ?");
            $stmtCheck->execute([$classData['className'], $classLevelId]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('ชื่อระดับชั้นนี้มีอยู่ในระบบแล้ว', 409); // 409 Conflict
            }

            $sql = "UPDATE ClassLevels SET className=?, description=? WHERE classLevelId=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $classData['className'],
                $classData['description'] ?? null,
                $classLevelId
            ]);
            echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลระดับชั้นสำเร็จ']);
            break;

        case 'delete':
            $classLevelId = $input['classLevelId'];

            // Optional: Add a check here to prevent deleting a class level if it's currently assigned to students.
            // For example: SELECT COUNT(*) FROM Students WHERE grade = (SELECT className FROM ClassLevels WHERE classLevelId = ?)

            $stmt = $conn->prepare("DELETE FROM ClassLevels WHERE classLevelId = ?");
            $stmt->execute([$classLevelId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'ลบข้อมูลระดับชั้นสำเร็จ']);
            } else {
                send_json_error('ไม่พบระดับชั้นที่ต้องการลบ', 404);
            }
            break;

        default:
            send_json_error('Invalid action for class level handler.', 400);
            break;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        send_json_error('ข้อมูลซ้ำ: ชื่อระดับชั้นนี้มีอยู่ในระบบแล้ว', 409);
    } else {
        send_json_error('Database error: ' . $e->getMessage(), 500);
    }
} catch (Exception $e) {
    send_json_error('Server error: ' . $e->getMessage(), 500);
}
?>