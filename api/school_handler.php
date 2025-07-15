<?php
// school_handler.php
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
            // ดึงข้อมูลโรงเรียน (คาดว่ามีเพียงหนึ่งเดียว)
            $stmt = $conn->prepare("SELECT * FROM SchoolInfo LIMIT 1");
            $stmt->execute();
            $schoolInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            // ถ้ายังไม่มีข้อมูลโรงเรียน ให้ส่งโครงสร้างว่างๆ กลับไป เพื่อให้ frontend รู้ว่าควรเพิ่มข้อมูล
            if (!$schoolInfo) {
                $schoolInfo = [
                    'schoolId' => '', // จะสร้างใหม่เมื่อมีการบันทึกครั้งแรก
                    'schoolName' => '',
                    'address' => '',
                    'phone' => '',
                    'email' => '',
                    'principalName' => '',
                    'logoUrl' => ''
                ];
            }
            echo json_encode(['success' => true, 'schoolInfo' => $schoolInfo]);
            break;

        case 'update':
            $data = $input['schoolInfo'];
            $schoolId = $data['schoolId'] ?? '';

            if (empty($schoolId)) {
                // ถ้าไม่มี schoolId แสดงว่าเป็นการบันทึกครั้งแรก
                $newId = getUuid();
                $sql = "INSERT INTO SchoolInfo (schoolId, schoolName, address, phone, email, principalName, logoUrl) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $newId, 
                    $data['schoolName'], 
                    $data['address'], 
                    $data['phone'], 
                    $data['email'], 
                    $data['principalName'], 
                    $data['logoUrl'] ?? null
                ]);
                echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลโรงเรียนสำเร็จ', 'schoolId' => $newId]);
            } else {
                // ถ้ามี schoolId แสดงว่าเป็นการอัปเดตข้อมูลที่มีอยู่
                $sql = "UPDATE SchoolInfo SET schoolName=?, address=?, phone=?, email=?, principalName=?, logoUrl=? WHERE schoolId=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['schoolName'], 
                    $data['address'], 
                    $data['phone'], 
                    $data['email'], 
                    $data['principalName'], 
                    $data['logoUrl'] ?? null,
                    $schoolId
                ]);
                echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลโรงเรียนสำเร็จ']);
            }
            break;

        default:
            send_json_error('Invalid action for school handler.', 400);
            break;
    }
} catch (PDOException $e) {
    send_json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    send_json_error('Server error: ' . $e->getMessage(), 500);
}
?>