<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();
$exams = $pdo
    ->query(
        "SELECT e.EXAM_ID,e.EXAM_DATE,s.SUBJECT_NAME FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID ORDER BY e.EXAM_DATE DESC",
    )
    ->fetchAll();
$eid = (int) ($_GET["exam_id"] ?? 0);
$sql =
    "SELECT e.EXAM_ID,e.EXAM_DATE,s.SUBJECT_NAME,d.DEPT_NAME,st.STUDENT_ID,CONCAT_WS(' ',st.FNAME,st.MNAME,st.LNAME) STUDENT_NAME,ra.ROOM_ID,r.BUILDING,ra.SEAT_NO FROM ROOM_ALLOCATION ra JOIN EXAM_REGISTRATION er ON er.REGISTR_ID=ra.REGISTR_ID JOIN EXAMINATION e ON e.EXAM_ID=er.EXAM_ID JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID JOIN DEPARTMENT d ON d.DEPT_ID=s.DEPT_ID JOIN STUDENTS st ON st.STUDENT_ID=er.STUDENT_ID JOIN ROOMS r ON r.ROOM_ID=ra.ROOM_ID";
$p = [];
if ($eid) {
    $sql .= " WHERE e.EXAM_ID=?";
    $p[] = $eid;
}
$sql .= " ORDER BY e.EXAM_DATE,ra.ROOM_ID,ra.SEAT_NO";
$st = $pdo->prepare($sql);
$st->execute($p);
$rows = $st->fetchAll();
include "partials.php";
?>
<main class="content"><div class="content-title"><div><span class="eyebrow">REPORT CENTER</span><h1>Reports & Exports</h1><p>Generate printable seating plans and spreadsheet-ready reports.</p></div><div class="title-actions"><a class="btn ghost" href="export.php?type=csv&exam_id=<?= $eid ?>">↓ CSV</a><a class="btn primary" href="export.php?type=excel&exam_id=<?= $eid ?>">↓ Excel</a></div></div>
<section class="panel"><form class="report-filter"><label>Examination<select name="exam_id"><option value="0">All examinations</option><?php foreach (
    $exams
    as $e
): ?><option value="<?= $e["EXAM_ID"] ?>" <?= $eid == $e["EXAM_ID"]
    ? "selected"
    : "" ?>>#<?= $e["EXAM_ID"] ?> — <?= e($e["SUBJECT_NAME"]) ?> — <?= $e[
     "EXAM_DATE"
 ] ?></option><?php endforeach; ?></select></label><button class="btn ghost">Filter</button><button type="button" class="btn primary" onclick="window.print()">Print / Save PDF</button></form></section>
<section class="panel print-area"><div class="report-cover"><div><span class="eyebrow">EXAMSPHERE</span><h2>Examination Seating Report</h2><p>Database created by <b>Harshith S H & K Sudhanva Datta</b></p></div><div class="report-count"><b><?= count(
    $rows,
) ?></b><span>seat records</span></div></div><div class="table-wrap"><table><thead><tr><th>Exam</th><th>Date</th><th>Subject</th><th>Department</th><th>Student ID</th><th>Student</th><th>Room</th><th>Seat</th></tr></thead><tbody><?php
foreach ($rows as $r): ?><tr><td>#<?= $r["EXAM_ID"] ?></td><td><?= $r[
    "EXAM_DATE"
] ?></td><td><?= e($r["SUBJECT_NAME"]) ?></td><td><?= e(
    $r["DEPT_NAME"],
) ?></td><td><?= $r["STUDENT_ID"] ?></td><td><?= e(
    $r["STUDENT_NAME"],
) ?></td><td><?= $r["ROOM_ID"] ?> — <?= e($r["BUILDING"]) ?></td><td><b><?= $r[
    "SEAT_NO"
] ?></b></td></tr><?php endforeach;
if (
    !$rows
): ?><tr><td colspan="8" class="empty">No allocated seats found.</td></tr><?php endif;
?></tbody></table></div></section></main><?php include "end.php"; ?>
