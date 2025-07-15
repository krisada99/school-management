<?php
// school-management/api/settings_handler.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

function send_json_error($message, $code = 500, $details = "") {
    http_response_code($code);
    header("Content-Type: application/json; charset=UTF-8");
    $response = ["success" => false, "error" => $message];
    if (!empty($details)) { $response['details'] = $details; }
    echo json_encode($response);
    exit();
}

function send_json_response($data) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($data);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $entity = $input['entity'] ?? '';
    $action = $input['action'] ?? '';

    switch ($entity) {
        case 'academic_term':
            handle_academic_term($conn, $action, $input);
            break;
        case 'fiscal_year':
            handle_fiscal_year($conn, $action, $input);
            break;
        case 'holiday':
            handle_holiday($conn, $action, $input);
            break;
        case 'get_all_settings':
            $stmt_terms = $conn->prepare("SELECT * FROM AcademicTerms ORDER BY academicYear DESC, semester DESC");
            $stmt_terms->execute();
            $academicTerms = $stmt_terms->fetchAll(PDO::FETCH_ASSOC);

            $stmt_fiscal = $conn->prepare("SELECT * FROM FiscalYears ORDER BY year DESC");
            $stmt_fiscal->execute();
            $fiscalYears = $stmt_fiscal->fetchAll(PDO::FETCH_ASSOC);

            // This query is now robust and uses the correct column names.
            $stmt_holidays = $conn->prepare("SELECT h.holidayId, h.fiscalYearId, h.description, h.startDate, h.endDate, fy.year as fiscalYear FROM Holidays h LEFT JOIN FiscalYears fy ON h.fiscalYearId = fy.fiscalYearId ORDER BY h.startDate ASC");
            $stmt_holidays->execute();
            $holidays = $stmt_holidays->fetchAll(PDO::FETCH_ASSOC);
            
            send_json_response([
                'success' => true,
                'academicTerms' => $academicTerms,
                'fiscalYears' => $fiscalYears,
                'holidays' => $holidays,
            ]);
            break;

        default:
            send_json_error('Invalid entity for settings handler.', 400);
            break;
    }

} catch (PDOException $e) {
    send_json_error('Database Error', 500, $e->getMessage());
} catch (Exception $e) {
    send_json_error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : 400);
}

function handle_academic_term($conn, $action, $input) {
    $data = $input['data'] ?? [];
    switch ($action) {
        case 'add':
            $id = getUuid();
            $stmt = $conn->prepare("INSERT INTO AcademicTerms (termId, academicYear, semester, startDate, endDate) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $data['academicYear'], $data['semester'], $data['startDate'], $data['endDate']]);
            send_json_response(['success' => true, 'message' => 'เพิ่มปีการศึกษาสำเร็จ']);
            break;
        case 'update':
            $stmt = $conn->prepare("UPDATE AcademicTerms SET academicYear=?, semester=?, startDate=?, endDate=? WHERE termId=?");
            $stmt->execute([$data['academicYear'], $data['semester'], $data['startDate'], $data['endDate'], $data['termId']]);
            send_json_response(['success' => true, 'message' => 'อัปเดตปีการศึกษาสำเร็จ']);
            break;
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM AcademicTerms WHERE termId = ?");
            $stmt->execute([$data['termId']]);
            send_json_response(['success' => true, 'message' => 'ลบปีการศึกษาสำเร็จ']);
            break;
        case 'set_current':
            $conn->beginTransaction();
            $conn->exec("UPDATE AcademicTerms SET isCurrent = FALSE");
            $stmt = $conn->prepare("UPDATE AcademicTerms SET isCurrent = TRUE WHERE termId = ?");
            $stmt->execute([$data['termId']]);
            $conn->commit();
            send_json_response(['success' => true, 'message' => 'ตั้งเป็นปีการศึกษาปัจจุบันสำเร็จ']);
            break;
    }
}

function handle_fiscal_year($conn, $action, $input) {
     $data = $input['data'] ?? [];
    switch ($action) {
        case 'add':
            $id = getUuid();
            $stmt = $conn->prepare("INSERT INTO FiscalYears (fiscalYearId, year, startDate, endDate) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $data['year'], $data['startDate'], $data['endDate']]);
            send_json_response(['success' => true, 'message' => 'เพิ่มปีงบประมาณสำเร็จ']);
            break;
        case 'update':
            $stmt = $conn->prepare("UPDATE FiscalYears SET year=?, startDate=?, endDate=? WHERE fiscalYearId=?");
            $stmt->execute([$data['year'], $data['startDate'], $data['endDate'], $data['fiscalYearId']]);
            send_json_response(['success' => true, 'message' => 'อัปเดตปีงบประมาณสำเร็จ']);
            break;
        case 'delete':
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Holidays WHERE fiscalYearId = ?");
            $stmtCheck->execute([$data['fiscalYearId']]);
            if ($stmtCheck->fetchColumn() > 0) {
                send_json_error('ไม่สามารถลบได้ เนื่องจากมีวันหยุดผูกอยู่กับปีงบประมาณนี้', 409);
            }
            $stmt = $conn->prepare("DELETE FROM FiscalYears WHERE fiscalYearId = ?");
            $stmt->execute([$data['fiscalYearId']]);
            send_json_response(['success' => true, 'message' => 'ลบปีงบประมาณสำเร็จ']);
            break;
        case 'set_current':
            $conn->beginTransaction();
            $conn->exec("UPDATE FiscalYears SET isCurrent = FALSE");
            $stmt = $conn->prepare("UPDATE FiscalYears SET isCurrent = TRUE WHERE fiscalYearId = ?");
            $stmt->execute([$data['fiscalYearId']]);
            $conn->commit();
            send_json_response(['success' => true, 'message' => 'ตั้งเป็นปีงบประมาณปัจจุบันสำเร็จ']);
            break;
    }
}

function handle_holiday($conn, $action, $input) {
    $data = $input['data'] ?? [];
    switch ($action) {
        case 'add':
            $id = getUuid();
            $stmt = $conn->prepare("INSERT INTO Holidays (holidayId, fiscalYearId, description, startDate, endDate) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $data['fiscalYearId'], $data['description'], $data['startDate'], $data['endDate']]);
            send_json_response(['success' => true, 'message' => 'เพิ่มวันหยุดสำเร็จ']);
            break;
        case 'update':
            $stmt = $conn->prepare("UPDATE Holidays SET fiscalYearId=?, description=?, startDate=?, endDate=? WHERE holidayId=?");
            $stmt->execute([$data['fiscalYearId'], $data['description'], $data['startDate'], $data['endDate'], $data['holidayId']]);
            send_json_response(['success' => true, 'message' => 'อัปเดตวันหยุดสำเร็จ']);
            break;
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM Holidays WHERE holidayId = ?");
            $stmt->execute([$data['holidayId']]);
            send_json_response(['success' => true, 'message' => 'ลบวันหยุดสำเร็จ']);
            break;
    }
}
?>