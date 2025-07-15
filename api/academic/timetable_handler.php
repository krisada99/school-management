<?php
// school-management/api/academic/timetable_handler.php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db_connect.php';

/**
 * ฟังก์ชันสำหรับตรวจสอบตารางเรียนที่ซ้ำซ้อนของนักเรียน
 * @param PDO $conn - Connection object
 * @param array $studentIds - ID ของนักเรียนทั้งหมดที่ต้องการตรวจสอบ
 * @param string $dayOfWeek - วันที่ต้องการตรวจสอบ e.g., 'Monday'
 * @param string $startTime - เวลาเริ่มต้น HH:MM:SS
 * @param string $endTime - เวลาสิ้นสุด HH:MM:SS
 * @param string|null $excludeEntryId - ID ของตารางสอนที่จะไม่นำมาตรวจสอบ (ใช้ตอน "แก้ไข" เพื่อไม่ให้เช็คกับตัวเอง)
 * @return array - รายชื่อนักเรียนที่มีตารางเรียนซ้อน
 */
function checkForStudentConflicts($conn, $studentIds, $dayOfWeek, $startTime, $endTime, $excludeEntryId = null) {
    if (empty($studentIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

    $sql = "
        SELECT DISTINCT CONCAT(s.prefix, s.name, ' ', s.surname) as studentName
        FROM TimetableStudentLinks tsl
        JOIN TimetableEntries te ON tsl.entryId = te.entryId
        JOIN Students s ON tsl.studentId = s.studentId
        WHERE tsl.studentId IN ($placeholders)
          AND te.dayOfWeek = ?
          AND te.startTime < ?
          AND te.endTime > ?
    ";

    $params = $studentIds;
    array_push($params, $dayOfWeek, $endTime, $startTime);

    if ($excludeEntryId !== null) {
        $sql .= " AND te.entryId != ?";
        $params[] = $excludeEntryId;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


function send_json_error($message, $code = 500, $details = "") {
    ob_end_clean();
    http_response_code($code);
    header("Content-Type: application/json; charset=UTF-8");
    $response = ["success" => false, "error" => $message];
    if (!empty($details)) { $response['details'] = $details; }
    echo json_encode($response);
    exit();
}

function send_json_response($data) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($data);
    exit();
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'get':
            $stmt = $conn->prepare("
                SELECT
                    te.*,
                    cl.className AS classLevelName,
                    sub.subjectCode,
                    sub.subjectName,
                    sub.subjectType,
                    CONCAT(t.prefix, t.name, ' ', t.surname) AS teacherName,
                    CONCAT(s.prefix, s.name, ' ', s.surname) AS studentName,
                    s.studentCode AS studentCode,
                    (SELECT COUNT(*) FROM TimetableStudentLinks WHERE entryId = te.entryId) as studentCount
                FROM TimetableEntries te
                LEFT JOIN Subjects sub ON te.subjectId = sub.subjectId
                LEFT JOIN ClassLevels cl ON te.classLevelId = cl.classLevelId
                LEFT JOIN Users t ON te.teacherId = t.userId
                LEFT JOIN Students s ON te.studentId = s.studentId
                ORDER BY te.academicYear DESC, te.semester ASC, FIELD(te.dayOfWeek, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), te.startTime ASC
            ");
            $stmt->execute();
            $timetableEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($timetableEntries as &$entry) {
                if ($entry['studentCount'] > 1 && is_null($entry['studentName'])) {
                     $entry['studentName'] = ($entry['subjectType'] === 'วิชาเลือกเสรี')
                        ? "นักเรียนวิชาเลือกเสรี ({$entry['studentCount']} คน)"
                        : "นักเรียนทั้งห้อง ({$entry['studentCount']} คน)";
                }
            }
            send_json_response(['success' => true, 'timetableEntries' => $timetableEntries]);
            break;

        case 'add':
        case 'update':
            $conn->beginTransaction();
            try {
                $data = $input['timetableData'];
                $isEditing = ($action === 'update');
                $entryId = $isEditing ? ($data['entryId'] ?? null) : getUuid();

                if (($isEditing && !$entryId) || empty($data['subjectId']) || empty($data['teacherId']) || empty($data['dayOfWeek']) || empty($data['startTime']) || empty($data['endTime'])) {
                    send_json_error('ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบรายวิชา, ครู, วัน และเวลา', 400);
                }

                $stmt_subject = $conn->prepare("SELECT subjectType FROM Subjects WHERE subjectId = ?");
                $stmt_subject->execute([$data['subjectId']]);
                $subjectType = $stmt_subject->fetchColumn();
                if (!$subjectType) send_json_error('ไม่พบรายวิชาที่ระบุ', 404);

                $studentIdsToLink = [];
                $studentIdForEntry = null;

                if ($subjectType === 'วิชาเลือกเสรี') {
                    if (empty($data['studentIds']) || !is_array($data['studentIds'])) {
                        send_json_error('กรุณาเลือกนักเรียนอย่างน้อย 1 คนสำหรับวิชาเลือกเสรี', 400);
                    }
                    $studentIdsToLink = $data['studentIds'];
                } else {
                    if (empty($data['classLevelId']) || empty($data['room'])) {
                        send_json_error('กรุณาเลือกระดับชั้นและห้องเรียนสำหรับวิชานี้', 400);
                    }
                    $stmtClassName = $conn->prepare("SELECT className FROM ClassLevels WHERE classLevelId = ?");
                    $stmtClassName->execute([$data['classLevelId']]);
                    $className = $stmtClassName->fetchColumn();
                    $stmtStudents = $conn->prepare("SELECT studentId FROM Students WHERE grade = ? AND class = ?");
                    $stmtStudents->execute([$className, $data['room']]);
                    $studentIdsToLink = $stmtStudents->fetchAll(PDO::FETCH_COLUMN);
                    if (empty($studentIdsToLink)) {
                         send_json_error('ไม่พบนักเรียนในระดับชั้นและห้องที่เลือก', 404);
                    }
                }

                if(count($studentIdsToLink) === 1) {
                    $studentIdForEntry = $studentIdsToLink[0];
                }

                $conflictingStudents = checkForStudentConflicts($conn, $studentIdsToLink, $data['dayOfWeek'], $data['startTime'], $data['endTime'], $isEditing ? $entryId : null);
                if (!empty($conflictingStudents)) {
                    $conflictMessage = "บันทึกล้มเหลว! พบว่านักเรียนมีตารางเรียนซ้อนในวันและเวลาดังกล่าว: " . implode(', ', $conflictingStudents);
                    send_json_error($conflictMessage, 409);
                }

                if ($isEditing) {
                    $sql = "UPDATE TimetableEntries SET classLevelId=?, subjectId=?, teacherId=?, studentId=?, dayOfWeek=?, startTime=?, endTime=?, room=?, academicYear=?, semester=? WHERE entryId=?";
                    $params = [
                        $data['classLevelId'] ?? null, $data['subjectId'], $data['teacherId'], $studentIdForEntry,
                        $data['dayOfWeek'], $data['startTime'], $data['endTime'],
                        $data['room'] ?? null, $data['academicYear'] ?? null, $data['semester'] ?? null, $entryId
                    ];
                } else {
                    $sql = "INSERT INTO TimetableEntries (entryId, classLevelId, subjectId, teacherId, studentId, dayOfWeek, startTime, endTime, room, academicYear, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $entryId, $data['classLevelId'] ?? null, $data['subjectId'], $data['teacherId'], $studentIdForEntry,
                        $data['dayOfWeek'], $data['startTime'], $data['endTime'],
                        $data['room'] ?? null, $data['academicYear'] ?? null, $data['semester'] ?? null
                    ];
                }
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);

                $stmtDeleteLinks = $conn->prepare("DELETE FROM TimetableStudentLinks WHERE entryId = ?");
                $stmtDeleteLinks->execute([$entryId]);

                if (!empty($studentIdsToLink)) {
                    $stmtLink = $conn->prepare("INSERT INTO TimetableStudentLinks (linkId, entryId, studentId) VALUES (?, ?, ?)");
                    foreach ($studentIdsToLink as $studentId) {
                        $stmtLink->execute([getUuid(), $entryId, $studentId]);
                    }
                }

                $conn->commit();
                $message = $isEditing ? 'อัปเดตตารางสอนสำเร็จ' : 'เพิ่มตารางสอนสำเร็จ';
                send_json_response(['success' => true, 'message' => $message]);
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                throw $e;
            }
            break;

        case 'delete':
            $entryId = $input['entryId'] ?? null;
            if (empty($entryId)) send_json_error('Entry ID is missing for delete.', 400);

            $stmt = $conn->prepare("DELETE FROM TimetableEntries WHERE entryId = ?");
            $stmt->execute([$entryId]);

            if ($stmt->rowCount() > 0) {
                send_json_response(['success' => true, 'message' => 'ลบตารางสอนสำเร็จ']);
            } else {
                send_json_error('ไม่พบตารางสอนที่ต้องการลบ', 404);
            }
            break;

        case 'get_dropdown_data':
            $stmt_teachers = $conn->prepare("SELECT userId, prefix, name, surname FROM Users WHERE role = 'teacher' ORDER BY name ASC");
            $stmt_teachers->execute();
            $teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

            $stmt_levels = $conn->prepare("SELECT classLevelId, className FROM ClassLevels ORDER BY className ASC");
            $stmt_levels->execute();
            $classLevels = $stmt_levels->fetchAll(PDO::FETCH_ASSOC);

            $stmt_subjects = $conn->prepare("SELECT subjectId, subjectCode, subjectName, subjectType FROM Subjects ORDER BY subjectCode ASC");
            $stmt_subjects->execute();
            $subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);

            $stmt_term = $conn->prepare("SELECT academicYear, semester FROM AcademicTerms WHERE isCurrent = TRUE LIMIT 1");
            $stmt_term->execute();
            $currentTerm = $stmt_term->fetch(PDO::FETCH_ASSOC);

            $stmt_students = $conn->prepare("SELECT studentId, studentCode, prefix, name, surname, grade, class FROM Students ORDER BY grade, class, name ASC");
            $stmt_students->execute();
            $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

            $stmt_rooms = $conn->prepare("SELECT DISTINCT class FROM Students WHERE class IS NOT NULL AND class != '' ORDER BY class ASC");
            $stmt_rooms->execute();
            $classRooms = $stmt_rooms->fetchAll(PDO::FETCH_COLUMN, 0);

            send_json_response([
                'success' => true, 'teachers' => $teachers, 'classLevels' => $classLevels,
                'subjects' => $subjects, 'students' => $students,
                'currentTerm' => $currentTerm ?: null, 'classRooms' => $classRooms
            ]);
            break;

        case 'get_students_by_entry':
            $entryId = $input['entryId'] ?? null;
            if (!$entryId) {
                send_json_error('Entry ID is required.', 400);
            }
            $stmt = $conn->prepare("SELECT studentId FROM TimetableStudentLinks WHERE entryId = ?");
            $stmt->execute([$entryId]);
            $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            send_json_response(['success' => true, 'studentIds' => $studentIds]);
            break;

        default:
            send_json_error('Invalid action for timetable handler.', 400);
            break;
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    send_json_error('Database Error', 500, $e->getMessage());
} catch (Exception $e) {
    send_json_error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : 400);
}
?>