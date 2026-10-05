<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();
function spreadsheetSafeValue($value): string
{
    $value = (string) $value;
    $isFormula = preg_match('/^[\s\p{Z}\x00-\x20\x{FEFF}]*[=+\-@]/u', $value);
    if ($isFormula === false) {
        throw new UnexpectedValueException(
            "Could not validate an exported spreadsheet value.",
        );
    }
    return $isFormula === 1 ? "'" . $value : $value;
}
$type = $_GET["type"] ?? "csv";
$exam = (int) ($_GET["exam_id"] ?? 0);
$sql =
    "SELECT e.EXAM_ID,e.EXAM_DATE,s.SUBJECT_NAME,d.DEPT_NAME,st.STUDENT_ID,CONCAT_WS(' ',st.FNAME,st.MNAME,st.LNAME) STUDENT_NAME,ra.ROOM_ID,r.BUILDING,ra.SEAT_NO FROM ROOM_ALLOCATION ra JOIN EXAM_REGISTRATION er ON er.REGISTR_ID=ra.REGISTR_ID JOIN EXAMINATION e ON e.EXAM_ID=er.EXAM_ID JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID JOIN DEPARTMENT d ON d.DEPT_ID=s.DEPT_ID JOIN STUDENTS st ON st.STUDENT_ID=er.STUDENT_ID JOIN ROOMS r ON r.ROOM_ID=ra.ROOM_ID";
$params = [];
if ($exam) {
    $sql .= " WHERE e.EXAM_ID=?";
    $params[] = $exam;
}
$sql .= " ORDER BY e.EXAM_DATE,ra.ROOM_ID,ra.SEAT_NO";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
if ($type === "excel") {
    header("Content-Type: application/vnd.ms-excel");
    header(
        'Content-Disposition: attachment; filename="ExamSphere_Seating_Report.xls"',
    );
    echo "<table border='1'><tr><th>Exam</th><th>Date</th><th>Subject</th><th>Department</th><th>Student ID</th><th>Student</th><th>Room</th><th>Building</th><th>Seat</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>" .
            e(spreadsheetSafeValue($r["EXAM_ID"])) .
            "</td><td>" .
            e(spreadsheetSafeValue($r["EXAM_DATE"])) .
            "</td><td>" .
            e(spreadsheetSafeValue($r["SUBJECT_NAME"])) .
            "</td><td>" .
            e(spreadsheetSafeValue($r["DEPT_NAME"])) .
            "</td><td>" .
            e(spreadsheetSafeValue($r["STUDENT_ID"])) .
            "</td><td>" .
            e(spreadsheetSafeValue($r["STUDENT_NAME"])) .
            "</td><td>" .
            e(spreadsheetSafeValue($r["ROOM_ID"])) .
            "</td><td>" .
            e(spreadsheetSafeValue($r["BUILDING"])) .
            "</td><td>" .
            e(spreadsheetSafeValue($r["SEAT_NO"])) .
            "</td></tr>";
    }
    echo "</table>";
    exit();
}
header("Content-Type: text/csv; charset=utf-8");
header(
    'Content-Disposition: attachment; filename="ExamSphere_Seating_Report.csv"',
);
$out = fopen("php://output", "w");
fputcsv($out, [
    "Exam",
    "Date",
    "Subject",
    "Department",
    "Student ID",
    "Student",
    "Room",
    "Building",
    "Seat",
]);
foreach ($rows as $r) {
    fputcsv($out, array_map("spreadsheetSafeValue", $r));
}
fclose($out);
exit();
?>
