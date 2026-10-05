<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();
$rows = $pdo
    ->query(
        "SELECT d.DEPT_ID,d.DEPT_NAME,COUNT(DISTINCT st.STUDENT_ID) students,COUNT(DISTINCT s.SUBJECT_ID) subjects,COUNT(DISTINCT er.REGISTR_ID) registrations,COUNT(DISTINCT ra.ALLOCATION_ID) allocations FROM DEPARTMENT d LEFT JOIN STUDENTS st ON st.DEPT_ID=d.DEPT_ID LEFT JOIN SUBJECT s ON s.DEPT_ID=d.DEPT_ID LEFT JOIN EXAMINATION e ON e.SUBJECT_ID=s.SUBJECT_ID LEFT JOIN EXAM_REGISTRATION er ON er.EXAM_ID=e.EXAM_ID LEFT JOIN ROOM_ALLOCATION ra ON ra.REGISTR_ID=er.REGISTR_ID GROUP BY d.DEPT_ID,d.DEPT_NAME ORDER BY students DESC",
    )
    ->fetchAll();
$max = max([1, ...array_map("intval", array_column($rows, "students"))]);
include "partials.php";
?>
<main class="content"><div class="content-title"><div><span class="eyebrow">ANALYTICS</span><h1>Department Insights</h1><p>Understand student, subject, registration and allocation distribution by department.</p></div></div>
<div class="analytics-grid"><?php
foreach (
    $rows
    as $r
): ?><div class="dept-card"><div class="dept-top"><div class="dept-icon"><?= e(
    strtoupper(substr($r["DEPT_NAME"], 0, 2)),
) ?></div><div><h3><?= e($r["DEPT_NAME"]) ?></h3><span><?= e(
    $r["DEPT_ID"],
) ?></span></div></div><div class="bar-label"><span>Students</span><b><?= $r[
    "students"
] ?></b></div><div class="bar"><i style="width:<?= round(
    ($r["students"] / $max) * 100,
) ?>%"></i></div><div class="mini-metrics"><div><b><?= $r[
    "subjects"
] ?></b><small>Subjects</small></div><div><b><?= $r[
    "registrations"
] ?></b><small>Registrations</small></div><div><b><?= $r[
    "allocations"
] ?></b><small>Allocated</small></div></div></div><?php endforeach;
if (
    !$rows
): ?><div class="empty">Add departments and students to see analytics.</div><?php endif;
?></div>
<section class="panel"><div class="panel-head"><div><h2>Department Summary</h2><p>Live aggregate from MySQL</p></div></div><div class="table-wrap"><table><thead><tr><th>Department</th><th>Students</th><th>Subjects</th><th>Registrations</th><th>Allocated</th><th>Allocation Rate</th></tr></thead><tbody><?php foreach (
    $rows
    as $r
):
    $rate = $r["registrations"]
        ? round(($r["allocations"] / $r["registrations"]) * 100)
        : 0; ?><tr><td><b><?= e($r["DEPT_NAME"]) ?></b></td><td><?= $r[
    "students"
] ?></td><td><?= $r["subjects"] ?></td><td><?= $r[
    "registrations"
] ?></td><td><?= $r[
    "allocations"
] ?></td><td><span class="progress-text"><?= $rate ?>%</span><div class="bar tiny"><i style="width:<?= $rate ?>%"></i></div></td></tr><?php
endforeach; ?></tbody></table></div></section></main><?php include "end.php"; ?>
