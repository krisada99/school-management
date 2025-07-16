<?php
// เปิดการแสดงข้อผิดพลาดเพื่อช่วยในการดีบัก
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- ⚙️ กรุณาแก้ไขข้อมูลการเชื่อมต่อฐานข้อมูลของคุณที่นี่ ⚙️ ---
// ตรวจสอบให้แน่ใจว่าข้อมูลเหล่านี้ถูกต้องตามการตั้งค่า MariaDB บน Synology NAS ของคุณ
$serverName = "krisada.synology.me";    // โดยทั่วไปคือ "localhost" หรือ "127.0.0.1"
$database   = "school_system_db";   // !!! เปลี่ยนชื่อฐานข้อมูลที่คุณจะสร้าง (เช่น school_db)
$uid        = "school";     // !!! ชื่อผู้ใช้ฐานข้อมูลของคุณ (อาจไม่ใช่ root ใน production)
$pwd        = "P@ssw0rd@2012"; // !!! รหัสผ่านที่คุณตั้งไว้สำหรับผู้ใช้ฐานข้อมูล

try {
    // สร้างการเชื่อมต่อ PDO สำหรับ MariaDB/MySQL
    $conn = new PDO("mysql:host=$serverName;dbname=$database;charset=utf8mb4", $uid, $pwd);
    // ตั้งค่าให้ PDO โยน Exception เมื่อเกิดข้อผิดพลาด ทำให้การจัดการ error ง่ายขึ้น
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // ถ้าการเชื่อมต่อล้มเหลว จะส่ง JSON error กลับไป
    http_response_code(500); // Internal Server Error
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "success" => false,
        "error" => "Database Connection Failed in db_connect.php",
        "details" => $e->getMessage()
    ]);
    exit(); // หยุดการทำงานของสคริปต์
}

// ฟังก์ชันสำหรับสร้าง UUID (Universally Unique Identifier) v4
// ใช้สำหรับสร้าง ID ที่ไม่ซ้ำกันสำหรับแต่ละรายการในฐานข้อมูล
function getUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>
