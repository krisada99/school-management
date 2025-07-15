<?php
// api/supervision/upload_supervision_file.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
// --- START: CORRECTED THIS LINE ---
// แก้ไข Path จาก '../../db_connect.php' เป็น '../db_connect.php'
require_once '../db_connect.php'; 
// --- END: CORRECTED THIS LINE ---

// Helper function for JSON error response
function send_json_error($message, $code = 500, $details = "") {
    http_response_code($code);
    $response = ["success" => false, "error" => $message];
    if (!empty($details)) { $response['details'] = $details; }
    echo json_encode($response);
    exit();
}

// ตรวจสอบว่ามีการส่งไฟล์มาหรือไม่
if (!isset($_FILES['fileToUpload'])) {
    send_json_error('No file uploaded.', 400);
}

$file = $_FILES['fileToUpload'];
$fileTypeCategory = $_POST['fileTypeCategory'] ?? ''; // 'result' or 'photo'

// กำหนดไดเรกทอรีการอัปโหลดและกฎตามประเภทไฟล์
$uploadBaseDir = dirname(dirname(__DIR__)) . '/uploads/supervision/'; // path ไปยัง school-management/uploads/supervision/
$maxSize = 0;
$allowedMimeTypes = [];
$targetDir = '';

if ($fileTypeCategory === 'result') {
    $targetDir = $uploadBaseDir . 'files/';
    $maxSize = 10 * 1024 * 1024; // 10MB
    // ใช้ associative array เพื่อให้ดึงนามสกุลไฟล์ได้ถูกต้อง: MIME type => extension
    $allowedMimeTypes = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
    ];
} elseif ($fileTypeCategory === 'photo') {
    $targetDir = $uploadBaseDir . 'photos/';
    $maxSize = 5 * 1024 * 1024; // 5MB
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
} else {
    send_json_error('Invalid file type category.', 400);
}

// สร้างโฟลเดอร์ถ้ายังไม่มี
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0775, true)) {
        send_json_error('Failed to create target directory. Check server permissions.', 500);
    }
}

// ตรวจสอบ error จากการอัปโหลดของ PHP
if ($file['error'] !== UPLOAD_ERR_OK) {
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
    send_json_error($errorMsg, 500);
}

// ตรวจสอบขนาดไฟล์
if ($file['size'] > $maxSize) {
    send_json_error('File size exceeds ' . ($maxSize / 1024 / 1024) . 'MB limit.', 413);
}

// ตรวจสอบ Mime Type ของไฟล์จากฝั่ง Server เพื่อความปลอดภัย
$fileMimeType = mime_content_type($file['tmp_name']);
if (!array_key_exists($fileMimeType, $allowedMimeTypes)) {
    send_json_error('Invalid file type. Allowed types: ' . implode(', ', array_keys($allowedMimeTypes)), 415);
}

// ดึงนามสกุลไฟล์ที่ถูกต้องจากรายการที่อนุญาต
$extension = $allowedMimeTypes[$fileMimeType];

// สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกันและปลอดภัย
$newUuid = getUuid(); // ฟังก์ชันนี้มาจาก db_connect.php
$safeFileName = $newUuid . '.' . $extension;
$destination = $targetDir . $safeFileName;

// ย้ายไฟล์ที่อัปโหลดไปยังโฟลเดอร์เป้าหมาย
if (move_uploaded_file($file['tmp_name'], $destination)) {
    // สร้าง URL สัมพัทธ์สำหรับใช้เก็บในฐานข้อมูล
    $fileUrl = 'uploads/supervision/' . ($fileTypeCategory === 'result' ? 'files/' : 'photos/') . $safeFileName;
    echo json_encode([
        'success' => true, 
        'url' => $fileUrl, 
        'fileId' => $newUuid, 
        'fileName' => $file['name'], 
        'fileType' => $fileMimeType
    ]);
} else {
    send_json_error('Failed to move uploaded file. Check server write permissions for the uploads directory.', 500);
}
?>