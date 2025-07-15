<?php
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบว่ามีโฟลเดอร์ uploads อยู่จริง และสร้างถ้ายังไม่มี
// การใช้ dirname(__DIR__) ทำให้ path อ้างอิงจากตำแหน่งของไฟล์นี้เสมอ ปลอดภัยกว่าการใช้ relative path แบบปกติ
// path: LeaveSystem-2/api/upload_file.php -> dirname(__DIR__) จะไปที่ LeaveSystem-2/
$uploadDir = dirname(__DIR__) . '/uploads/'; // ผลลัพธ์คือ /path/to/LeaveSystem-2/uploads/

if (!is_dir($uploadDir)) {
    // 0775 เป็น permission ที่ปลอดภัยกว่า 0777 สำหรับ web server
    if (!mkdir($uploadDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create uploads directory. Check server permissions.']);
        exit;
    }
}

// ตรวจสอบว่ามีการส่งไฟล์มาหรือไม่
if (!isset($_FILES['attachmentFile'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['attachmentFile'];

// ตรวจสอบ error จากการอัปโหลดของ PHP
if ($file['error'] !== UPLOAD_ERR_OK) {
    // ให้ข้อความ error ที่สื่อความหมายมากขึ้น
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive in HTML form.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
    ];
    $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error';
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

// --- การตั้งค่าและตรวจสอบความปลอดภัยของไฟล์ที่อัปโหลด ---

// 1. ขนาดไฟล์สูงสุด (ตัวอย่าง 5MB)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    http_response_code(413); // Payload Too Large
    echo json_encode(['success' => false, 'error' => 'File size exceeds ' . ($maxSize / 1024 / 1024) . 'MB limit.']);
    exit;
}

// 2. ตรวจสอบประเภทไฟล์ที่อนุญาต (Whitelist)
// อนุญาตเฉพาะรูปภาพและ PDF เพื่อความปลอดภัยสูงสุด
$allowedMimeTypes = [
    'image/jpeg'        => 'jpg',
    'image/png'         => 'png',
    'application/pdf'   => 'pdf'
];

// ตรวจสอบ Mime Type จากฝั่ง Server ด้วย `mime_content_type` เพื่อความปลอดภัยสูงสุด
// (ไม่เชื่อ Mime Type ที่ browser ส่งมา)
$fileMimeType = mime_content_type($file['tmp_name']);
if (!isset($allowedMimeTypes[$fileMimeType])) {
    http_response_code(415); // Unsupported Media Type
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, PDF are allowed.']);
    exit;
}

// 3. สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกันและปลอดภัย
// เพื่อป้องกันการเดาชื่อไฟล์ และการเขียนทับไฟล์เดิมที่อาจก่อให้เกิดช่องโหว่
$extension = $allowedMimeTypes[$fileMimeType];
$safeFileName = bin2hex(random_bytes(16)) . '.' . $extension; // สร้างชื่อแบบสุ่ม 32 ตัวอักษร + นามสกุล
$destination = $uploadDir . $safeFileName;

// 4. ย้ายไฟล์ไปยังโฟลเดอร์เป้าหมาย
if (move_uploaded_file($file['tmp_name'], $destination)) {
    // สร้าง URL ที่จะใช้เก็บในฐานข้อมูล
    // path นี้จะถูกเรียกจาก index.html ซึ่งอยู่ที่ root ของ LeaveSystem-2
    $fileUrl = 'uploads/' . $safeFileName; // URL สัมพัทธ์สำหรับเก็บใน DB
    echo json_encode(['success' => true, 'url' => $fileUrl]);
} else {
    // ถ้า move_uploaded_file ล้มเหลว มักเกิดจาก permission ของโฟลเดอร์ปลายทาง
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file. Check server write permissions for the "uploads" directory.']);
}
?>