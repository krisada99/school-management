<?php
// api/login.php - Final Production Version

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

header("Content-Type: application/json; charset=UTF-8");

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400); // Bad Request
    echo json_encode(["success" => false, "error" => "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน"]);
    exit();
}

try {
    // ดึงข้อมูลผู้ใช้ รวมถึงคอลัมน์ permissions
    $sql = "SELECT userId, prefix, name, surname, role, position, permissions FROM Users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // ถ้าพบผู้ใช้: ส่งข้อมูลกลับไปให้หน้าเว็บ
        $response = [
            "success" => true,
            "userId" => $user['userId'],
            "name" => $user['prefix'] . $user['name'] . ' ' . $user['surname'],
            "role" => $user['role'],
            "position" => $user['position'],
            // แปลง permissions จาก JSON string เป็น PHP array
            // หากค่าเป็น NULL หรือค่าว่าง จะถูกแปลงเป็น array ว่าง []
            "permissions" => json_decode($user['permissions'] ?? '') ?? []
        ];
    } else {
        // ถ้าไม่พบผู้ใช้: ส่งข้อความผิดพลาดกลับไป
        http_response_code(401); // Unauthorized
        $response = ["success" => false, "error" => "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง"];
    }

    // ส่งผลลัพธ์กลับไปในรูปแบบ JSON
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Database error on login: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error on login: ' . $e->getMessage()]);
}
?>