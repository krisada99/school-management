<?php
// api/supervision/supervision_handler.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php'; // เรียกใช้ db_connect.php จากโฟลเดอร์แม่

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
            $stmt = $conn->prepare("
                SELECT 
                    sr.*,
                    CONCAT(sup.prefix, sup.name, ' ', sup.surname) AS supervisorName,
                    CONCAT(sue.prefix, sue.name, ' ', sue.surname) AS superviseeName
                FROM SupervisionRecords sr
                JOIN Users sup ON sr.supervisorId = sup.userId
                JOIN Users sue ON sr.superviseeId = sue.userId
                ORDER BY sr.supervisionDate DESC, sr.createdAt DESC
            ");
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($records as &$record) {
                $stmtFiles = $conn->prepare("SELECT fileId, fileName, fileUrl, fileType FROM SupervisionResultFiles WHERE recordId = ?");
                $stmtFiles->execute([$record['recordId']]);
                $record['resultFiles'] = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

                $stmtPhotos = $conn->prepare("SELECT photoId, photoName, photoUrl, photoType FROM SupervisionPhotos WHERE recordId = ? ORDER BY orderNum ASC, uploadedAt ASC");
                $stmtPhotos->execute([$record['recordId']]);
                $record['photos'] = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($record);

            echo json_encode(['success' => true, 'supervisionRecords' => $records]);
            break;

        case 'add':
            $conn->beginTransaction();
            try {
                $data = $input['recordData'];
                $resultFilesData = $input['resultFiles'] ?? [];
                $photoFilesData = $input['photoFiles'] ?? [];
                $newId = getUuid();

                $sql = "INSERT INTO SupervisionRecords (recordId, supervisionDate, supervisorId, superviseeId, focusArea, observations, feedback, followUp, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $newId, $data['supervisionDate'], $data['supervisorId'], $data['superviseeId'],
                    $data['focusArea'] ?? null, $data['observations'] ?? null, $data['feedback'] ?? null,
                    $data['followUp'] ?? null, $data['status'] ?? 'Completed'
                ]);

                foreach ($resultFilesData as $fileData) {
                    $fileSql = "INSERT INTO SupervisionResultFiles (fileId, recordId, fileName, fileUrl, fileType) VALUES (?, ?, ?, ?, ?)";
                    $fileStmt = $conn->prepare($fileSql);
                    $fileStmt->execute([$fileData['fileId'], $newId, $fileData['fileName'], $fileData['fileUrl'], $fileData['fileType']]);
                }

                $orderNum = 0;
                foreach ($photoFilesData as $photoData) {
                    $photoSql = "INSERT INTO SupervisionPhotos (photoId, recordId, photoName, photoUrl, photoType, orderNum) VALUES (?, ?, ?, ?, ?, ?)";
                    $photoStmt = $conn->prepare($photoSql);
                    $photoStmt->execute([$photoData['photoId'], $newId, $photoData['photoName'], $photoData['photoUrl'], $photoData['photoType'], $orderNum++]);
                }

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'เพิ่มบันทึกการนิเทศสำเร็จ']);
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                throw $e;
            }
            break;

        // --- ✨ START: โค้ดที่แก้ไขทั้งหมด ---
        case 'update':
            $conn->beginTransaction();
            try {
                $data = $input['recordData'];
                $resultFilesData = $input['resultFiles'] ?? [];
                $photoFilesData = $input['photoFiles'] ?? [];
                $recordId = $data['recordId'];

                if (empty($recordId)) {
                    send_json_error('Record ID is missing for update.', 400);
                }

                // 1. อัปเดตข้อมูลหลัก
                $sql = "UPDATE SupervisionRecords SET supervisionDate=?, supervisorId=?, superviseeId=?, focusArea=?, observations=?, feedback=?, followUp=?, status=? WHERE recordId=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['supervisionDate'], $data['supervisorId'], $data['superviseeId'],
                    $data['focusArea'] ?? null, $data['observations'] ?? null, $data['feedback'] ?? null,
                    $data['followUp'] ?? null, $data['status'] ?? 'Completed', $recordId
                ]);

                // 2. ซิงค์ไฟล์ผลการนิเทศ (PDF)
                sync_files($conn, $recordId, $resultFilesData, 'SupervisionResultFiles', 'fileId', 'fileUrl');

                // 3. ซิงค์ไฟล์รูปภาพ
                sync_files($conn, $recordId, $photoFilesData, 'SupervisionPhotos', 'photoId', 'photoUrl', true);

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'อัปเดตบันทึกการนิเทศสำเร็จ']);

            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                throw $e;
            }
            break;
        // --- ✨ END: โค้ดที่แก้ไขทั้งหมด ---

        case 'delete':
            $conn->beginTransaction();
            try {
                $recordId = $input['recordId'];

                $stmtFiles = $conn->prepare("SELECT fileUrl FROM SupervisionResultFiles WHERE recordId = ?");
                $stmtFiles->execute([$recordId]);
                foreach ($stmtFiles->fetchAll(PDO::FETCH_COLUMN) as $fileUrl) {
                    if ($fileUrl && file_exists(dirname(dirname(__DIR__)) . '/' . $fileUrl)) {
                        unlink(dirname(dirname(__DIR__)) . '/' . $fileUrl);
                    }
                }
                $conn->prepare("DELETE FROM SupervisionResultFiles WHERE recordId = ?")->execute([$recordId]);

                $stmtPhotos = $conn->prepare("SELECT photoUrl FROM SupervisionPhotos WHERE recordId = ?");
                $stmtPhotos->execute([$recordId]);
                foreach ($stmtPhotos->fetchAll(PDO::FETCH_COLUMN) as $photoUrl) {
                    if ($photoUrl && file_exists(dirname(dirname(__DIR__)) . '/' . $photoUrl)) {
                        unlink(dirname(dirname(__DIR__)) . '/' . $photoUrl);
                    }
                }
                $conn->prepare("DELETE FROM SupervisionPhotos WHERE recordId = ?")->execute([$recordId]);

                $stmt = $conn->prepare("DELETE FROM SupervisionRecords WHERE recordId = ?");
                $stmt->execute([$recordId]);

                if ($stmt->rowCount() > 0) {
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'ลบบันทึกการนิเทศสำเร็จ']);
                } else {
                    $conn->rollBack();
                    send_json_error('ไม่พบบันทึกการนิเทศที่ต้องการลบ', 404);
                }
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                throw $e;
            }
            break;

        default:
            send_json_error('Invalid action for supervision handler.', 400);
            break;
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    send_json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    send_json_error('Server error: ' . $e->getMessage(), 500);
}

// --- ✨ START: เพิ่มฟังก์ชันใหม่สำหรับการซิงค์ไฟล์ ---
/**
 * ฟังก์ชันสำหรับซิงโครไนซ์ไฟล์ระหว่างข้อมูลที่ส่งมากับฐานข้อมูล
 */
function sync_files($conn, $recordId, $filesFromFrontend, $tableName, $idColumn, $urlColumn, $isPhoto = false) {
    // ดึง ID ของไฟล์ที่ต้องการเก็บไว้จาก Frontend
    $idsToKeep = array_map(function($file) use ($idColumn) {
        return $file[$idColumn];
    }, $filesFromFrontend);

    // ดึงไฟล์ทั้งหมดที่มีอยู่ใน DB สำหรับ record นี้
    $stmtCurrent = $conn->prepare("SELECT {$idColumn}, {$urlColumn} FROM {$tableName} WHERE recordId = ?");
    $stmtCurrent->execute([$recordId]);
    $currentDbFiles = $stmtCurrent->fetchAll(PDO::FETCH_ASSOC);

    // หาไฟล์ที่ต้องลบ (มีใน DB แต่ไม่มีในรายการที่ต้องการเก็บ)
    foreach ($currentDbFiles as $dbFile) {
        if (!in_array($dbFile[$idColumn], $idsToKeep)) {
            // ลบไฟล์ออกจากเซิร์ฟเวอร์
            $filePath = dirname(dirname(__DIR__)) . '/' . $dbFile[$urlColumn];
            if ($dbFile[$urlColumn] && file_exists($filePath)) {
                unlink($filePath);
            }
            // ลบข้อมูลออกจาก DB
            $stmtDelete = $conn->prepare("DELETE FROM {$tableName} WHERE {$idColumn} = ?");
            $stmtDelete->execute([$dbFile[$idColumn]]);
        }
    }

    // หาไฟล์ที่ต้องเพิ่ม (มีในรายการที่ต้องการเก็บ แต่ยังไม่มีใน DB)
    $currentDbIds = array_map(function($file) use ($idColumn) { return $file[$idColumn]; }, $currentDbFiles);
    $orderNum = 0;
    foreach ($filesFromFrontend as $fileData) {
        if (!in_array($fileData[$idColumn], $currentDbIds)) {
            if ($isPhoto) {
                $sql = "INSERT INTO {$tableName} ({$idColumn}, recordId, photoName, photoUrl, photoType, orderNum) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$fileData[$idColumn], $recordId, $fileData['photoName'], $fileData['photoUrl'], $fileData['photoType'], $orderNum]);
            } else {
                $sql = "INSERT INTO {$tableName} ({$idColumn}, recordId, fileName, fileUrl, fileType) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$fileData[$idColumn], $recordId, $fileData['fileName'], $fileData['fileUrl'], $fileData['fileType']]);
            }
        }
        $orderNum++;
    }
}
// --- ✨ END: เพิ่มฟังก์ชันใหม่ ---
?>