<?php
// school-management/api/student_handler.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

require_once(dirname(__FILE__) . '/db_connect.php');

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
            // --- START: ✨ ส่วนที่แก้ไข ---
            // เปลี่ยน ORDER BY ให้เรียงตามรหัสนักเรียนเป็นหลัก
            $stmt = $conn->prepare("SELECT * FROM Students ORDER BY studentCode ASC");
            // --- END: ✨ ส่วนที่แก้ไข ---
            
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'students' => $students]);
            break;

        case 'get_class_rooms':
            $stmt = $conn->prepare("SELECT DISTINCT class FROM Students WHERE class IS NOT NULL AND class != '' ORDER BY class ASC");
            $stmt->execute();
            $rooms = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            echo json_encode(['success' => true, 'classRooms' => $rooms]);
            break;

        case 'import':
            $studentsToImport = $input['students'] ?? [];
            if (empty($studentsToImport)) {
                send_json_error('ไม่มีข้อมูลนักเรียนสำหรับนำเข้า', 400);
            }

            $conn->beginTransaction();
            try {
                $codesInFile = array_column($studentsToImport, 'studentCode');
                $placeholders = implode(',', array_fill(0, count($codesInFile), '?'));
                
                $checkSql = "SELECT studentCode FROM Students WHERE studentCode IN ($placeholders)";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute($codesInFile);
                $existingCodes = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($existingCodes)) {
                    $conn->rollBack();
                    send_json_error('พบรหัสนักเรียนที่ซ้ำกับข้อมูลในระบบ: ' . implode(', ', $existingCodes), 409);
                }

                $sql = "
                    INSERT INTO Students 
                        (studentId, studentCode, prefix, name, surname, dateOfBirth, gender, grade, class, parentName, parentPhone) 
                    VALUES 
                        (:studentId, :studentCode, :prefix, :name, :surname, :dateOfBirth, :gender, :grade, :class, :parentName, :parentPhone)
                ";
                $stmt = $conn->prepare($sql);

                $importedCount = 0;
                foreach ($studentsToImport as $student) {
                    if (empty($student['studentCode']) || empty($student['name'])) {
                        continue;
                    }
                    
                    $dob = !empty($student['dateOfBirth']) ? $student['dateOfBirth'] : null;

                    $stmt->execute([
                        ':studentId' => getUuid(),
                        ':studentCode' => $student['studentCode'],
                        ':prefix' => $student['prefix'] ?? '',
                        ':name' => $student['name'],
                        ':surname' => $student['surname'] ?? '',
                        ':dateOfBirth' => $dob,
                        ':gender' => $student['gender'] ?? null,
                        ':grade' => $student['grade'] ?? null,
                        ':class' => $student['class'] ?? null,
                        ':parentName' => $student['parentName'] ?? null,
                        ':parentPhone' => $student['parentPhone'] ?? null
                    ]);
                    $importedCount++;
                }

                $conn->commit();
                echo json_encode(['success' => true, 'message' => "นำเข้าข้อมูลนักเรียนใหม่สำเร็จ {$importedCount} รายการ"]);

            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $e;
            }
            break;

        case 'add':
            $studentData = $input['studentData'];
            $newId = getUuid();

            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Students WHERE studentCode = ?");
            $stmtCheck->execute([$studentData['studentCode']]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('รหัสนักเรียนนี้มีอยู่ในระบบแล้ว', 409);
            }

            $sql = "INSERT INTO Students (studentId, studentCode, prefix, name, surname, dateOfBirth, gender, grade, class, parentName, parentPhone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $newId, $studentData['studentCode'], $studentData['prefix'], $studentData['name'], $studentData['surname'],
                $studentData['dateOfBirth'] ?? null, $studentData['gender'] ?? null, $studentData['grade'] ?? null,
                $studentData['class'] ?? null, $studentData['parentName'] ?? null, $studentData['parentPhone'] ?? null
            ]);
            echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลนักเรียนสำเร็จ']);
            break;

        case 'update':
            $studentData = $input['studentData'];
            $studentId = $studentData['studentId'];

            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Students WHERE studentCode = ? AND studentId != ?");
            $stmtCheck->execute([$studentData['studentCode'], $studentId]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('รหัสนักเรียนนี้มีอยู่ในระบบแล้ว', 409);
            }

            $sql = "UPDATE Students SET studentCode=?, prefix=?, name=?, surname=?, dateOfBirth=?, gender=?, grade=?, class=?, parentName=?, parentPhone=? WHERE studentId=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $studentData['studentCode'], $studentData['prefix'], $studentData['name'], $studentData['surname'],
                $studentData['dateOfBirth'] ?? null, $studentData['gender'] ?? null, $studentData['grade'] ?? null,
                $studentData['class'] ?? null, $studentData['parentName'] ?? null, $studentData['parentPhone'] ?? null,
                $studentId
            ]);
            echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลนักเรียนสำเร็จ']);
            break;

        case 'delete':
            $studentId = $input['studentId'];
            $stmt = $conn->prepare("DELETE FROM Students WHERE studentId = ?");
            $stmt->execute([$studentId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'ลบข้อมูลนักเรียนสำเร็จ']);
            } else {
                send_json_error('ไม่พบนักเรียนที่ต้องการลบ', 404);
            }
            break;

        default:
            send_json_error('Invalid action for student handler.', 400);
            break;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        send_json_error('ข้อมูลซ้ำ: รหัสนักเรียนนี้มีอยู่ในระบบแล้ว', 409);
    } else {
        send_json_error('Database error: ' . $e->getMessage(), 500);
    }
} catch (Exception $e) {
    send_json_error('Server error: ' . $e->getMessage(), 500);
}
?>