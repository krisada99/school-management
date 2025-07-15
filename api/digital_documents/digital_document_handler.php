<?php
// school-management/api/digital_documents/digital_document_handler.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

function send_json_error($message, $code = 500, $details = "") {
    http_response_code($code);
    echo json_encode(["success" => false, "error" => $message, "details" => $details]);
    exit();
}

// --- START: ✨ ฟังก์ชันที่แก้ไขใหม่ทั้งหมด ---
/**
 * ฟังก์ชันสร้างเลขที่เอกสารใหม่ โดยหาจากเลขที่สูงสุดของปีปัจจุบันแล้วบวก 1
 * เพื่อป้องกันปัญหาเลขซ้ำกรณีมีการลบข้อมูล
 */
function generateDocNumber($conn) {
    try {
        $year_th = date('Y') + 543; // ปี พ.ศ. ปัจจุบัน
        $prefix = "DOC-" . $year_th . "-";

        // ค้นหาเลขที่เอกสารสูงสุดในปีปัจจุบัน
        $sql = "SELECT MAX(docNumber) FROM DigitalDocuments WHERE docNumber LIKE ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $maxDocNumber = $stmt->fetchColumn();

        $next_num = 1;
        // ถ้ามีเลขที่เอกสารในปีนี้อยู่แล้ว
        if ($maxDocNumber) {
            // ดึงเฉพาะส่วนตัวเลขท้ายสุดออกมา เช่น จาก 'DOC-2568-00002' จะได้ '00002'
            $last_num_str = substr($maxDocNumber, strlen($prefix));
            // แปลงเป็นตัวเลข, บวก 1
            $next_num = (int)$last_num_str + 1;
        }

        // สร้างเลขที่ใหม่พร้อมเติมเลข 0 ข้างหน้าให้ครบ 5 หลัก
        return $prefix . str_pad($next_num, 5, '0', STR_PAD_LEFT);

    } catch (PDOException $e) {
        send_json_error('Database error during document number generation.', 500, $e->getMessage());
        return null; // Should not be reached
    }
}
// --- END: ✨ ฟังก์ชันที่แก้ไขใหม่ทั้งหมด ---

// สำหรับ action 'add' จะเป็นการส่งข้อมูลแบบ multipart/form-data
$input = json_decode(file_get_contents('php://input'), true);
$action = $_POST['action'] ?? ($input['action'] ?? '');

try {
    switch ($action) {
        case 'get':
            $filters = $input['filters'] ?? [];
            $sql = "
                SELECT d.*, CONCAT(u.prefix, u.name, ' ', u.surname) as uploaderName
                FROM DigitalDocuments d
                JOIN Users u ON d.uploadedBy = u.userId
                WHERE 1=1
            ";
            $params = [];

            if (!empty($filters['academicYear'])) { $sql .= " AND d.academicYear = ?"; $params[] = $filters['academicYear']; }
            if (!empty($filters['semester'])) { $sql .= " AND d.semester = ?"; $params[] = $filters['semester']; }
            if (!empty($filters['department'])) { $sql .= " AND d.department = ?"; $params[] = $filters['department']; }
            if (!empty($filters['docNumber'])) {
                $sql .= " AND (d.docNumber LIKE ? OR d.docTitle LIKE ?)";
                $searchTerm = '%' . $filters['docNumber'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
             if (!empty($filters['uploadDate'])) {
                $sql .= " AND DATE(d.uploadedAt) = ?";
                $params[] = $filters['uploadDate'];
            }

            $sql .= " ORDER BY d.uploadedAt DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'documents' => $documents]);
            break;

        case 'get_document_details':
            $docId = $input['docId'] ?? null;
            if (!$docId) send_json_error('Document ID is required.', 400);

            $stmt = $conn->prepare("SELECT * FROM DocumentFiles WHERE docId = ?");
            $stmt->execute([$docId]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'files' => $files]);
            break;

        case 'add':
            $conn->beginTransaction();
            try {
                $docData = $_POST;
                $newDocId = getUuid();
                $docNumber = generateDocNumber($conn);
                
                $sql = "INSERT INTO DigitalDocuments (docId, docNumber, docTitle, docDescription, academicYear, semester, department, uploadedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $newDocId, $docNumber, $docData['docTitle'],
                    $docData['docDescription'] ?? null, $docData['academicYear'],
                    $docData['semester'], $docData['department'], $docData['userId']
                ]);

                $uploadDir = dirname(dirname(__DIR__)) . '/uploads/digital_documents/';
                if (!is_dir($uploadDir)) {
                     if (!mkdir($uploadDir, 0775, true)) {
                        throw new Exception("Cannot create upload directory.");
                    }
                }

                $fileInsertSql = "INSERT INTO DocumentFiles (fileId, docId, fileName, fileUrl, fileType, fileSize) VALUES (?, ?, ?, ?, ?, ?)";
                $fileStmt = $conn->prepare($fileInsertSql);
                
                $fileCount = 0;
                if (!empty($_FILES['docFiles']) && is_array($_FILES['docFiles']['name'])) {
                    if (count($_FILES['docFiles']['name']) > 5) {
                        throw new Exception("Cannot upload more than 5 files at a time.");
                    }
                    
                    foreach ($_FILES['docFiles']['name'] as $key => $name) {
                        if ($_FILES['docFiles']['error'][$key] === UPLOAD_ERR_OK) {
                            $fileCount++;
                            $tmp_name = $_FILES['docFiles']['tmp_name'][$key];
                            $file_size = $_FILES['docFiles']['size'][$key];
                            $file_type = $_FILES['docFiles']['type'][$key];
                            
                            if ($file_size > 50 * 1024 * 1024) { // 50MB
                                throw new Exception("File '{$name}' is too large (max 50MB).");
                            }
                            
                            $original_filename = pathinfo($name, PATHINFO_FILENAME);
                            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            $counter = 1;
                            $fileNameForStorage = $name;
                            $destination = $uploadDir . $fileNameForStorage;

                            while (file_exists($destination)) {
                                $fileNameForStorage = $original_filename . '_(' . $counter . ').' . $extension;
                                $destination = $uploadDir . $fileNameForStorage;
                                $counter++;
                            }

                            $fileUrl = 'uploads/digital_documents/' . $fileNameForStorage;
                            
                            if (!move_uploaded_file($tmp_name, $destination)) {
                                throw new Exception("Failed to move uploaded file '{$name}'.");
                            }
                            
                            $fileStmt->execute([getUuid(), $newDocId, $name, $fileUrl, $file_type, $file_size]);
                        }
                    }
                }

                if ($fileCount === 0) {
                     throw new Exception("Please upload at least one document file.");
                }

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'เพิ่มเอกสารดิจิทัลสำเร็จ']);
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'delete':
            $docId = $input['docId'];
            $conn->beginTransaction();
            try {
                $stmtFiles = $conn->prepare("SELECT fileUrl FROM DocumentFiles WHERE docId = ?");
                $stmtFiles->execute([$docId]);
                $filesToDelete = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);

                foreach($filesToDelete as $fileUrl) {
                    $filePath = dirname(dirname(__DIR__)) . '/' . $fileUrl;
                    if (file_exists($filePath) && is_file($filePath)) {
                        unlink($filePath);
                    }
                }

                $stmtDelete = $conn->prepare("DELETE FROM DigitalDocuments WHERE docId = ?");
                $stmtDelete->execute([$docId]);

                if ($stmtDelete->rowCount() > 0) {
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'ลบเอกสารสำเร็จ']);
                } else {
                    $conn->rollBack();
                    send_json_error('ไม่พบเอกสารที่ต้องการลบ', 404);
                }

            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                throw $e;
            }
            break;

        default:
            send_json_error('Invalid action for digital document handler.', 400);
            break;
    }
} catch (Exception $e) {
    send_json_error($e->getMessage(), 500, $e->getTraceAsString());
}
?>