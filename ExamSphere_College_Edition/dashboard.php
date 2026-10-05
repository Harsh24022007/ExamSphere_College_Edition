<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();
$c = [];
foreach (
    [
        "STUDENTS",
        "EXAMINATION",
        "ROOMS",
        "ROOM_ALLOCATION",
        "INVIGILATOR",
        "EXAM_REGISTRATION",
        "DEPARTMENT",
    ]
    as $t
) {
    $c[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}
$up = $pdo
    ->query(
        "SELECT e.EXAM_ID,e.EXAM_DATE,e.START_TIME,e.END_TIME,s.SUBJECT_NAME,d.DEPT_NAME FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID JOIN DEPARTMENT d ON d.DEPT_ID=s.DEPT_ID WHERE e.EXAM_DATE>=CURDATE() ORDER BY e.EXAM_DATE,e.START_TIME LIMIT 6",
    )
    ->fetchAll();
$room = $pdo
    ->query(
        "SELECT COUNT(*) total,SUM(CASE WHEN STATUS='AVAILABLE' THEN 1 ELSE 0 END) available FROM ROOMS",
    )
    ->fetch();
include "partials.php";
?>
<main class="content"><div class="content-title"><div><span class="eyebrow">OVERVIEW</span><h1>Good day, <?= e(
    explode(" ", admin_name())[0],
) ?> 👋</h1><p>Here is today's examination management overview.</p></div><div class="title-actions"><a class="btn ghost" href="reports.php">Export reports</a><a class="btn primary" href="seating.php">Open Seating Studio</a></div></div>
<div class="dash-cards">
<a class="dash-card cyan" href="manage.php?table=STUDENTS"><span>STUDENTS</span><b><?= $c[
    "STUDENTS"
] ?></b><small>Registered learners</small></a>
<a class="dash-card blue" href="manage.php?table=EXAMINATION"><span>EXAMINATIONS</span><b><?= $c[
    "EXAMINATION"
] ?></b><small>Scheduled exams</small></a>
<a class="dash-card violet" href="manage.php?table=ROOMS"><span>ROOMS</span><b><?= $c[
    "ROOMS"
] ?></b><small><?= $room["available"] ?? 0 ?> available now</small></a>
<a class="dash-card green" href="allocation.php"><span>ALLOCATIONS</span><b><?= $c[
    "ROOM_ALLOCATION"
] ?></b><small>Seats assigned</small></a>
</div>
<div class="dash-grid"><section class="panel"><div class="panel-head"><div><h2>Upcoming Examinations</h2><p>Next scheduled examinations</p></div><a href="manage.php?table=EXAMINATION">View all →</a></div><div class="exam-list"><?php
foreach ($up as $x): ?><div class="exam-row"><div class="date-chip"><b><?= date(
    "d",
    strtotime($x["EXAM_DATE"]),
) ?></b><small><?= date(
    "M",
    strtotime($x["EXAM_DATE"]),
) ?></small></div><div class="exam-info"><b><?= e(
    $x["SUBJECT_NAME"],
) ?></b><span><?= e($x["DEPT_NAME"]) ?> • <?= substr(
     $x["START_TIME"],
     0,
     5,
 ) ?>–<?= substr(
    $x["END_TIME"],
    0,
    5,
) ?></span></div><span class="exam-id">#<?= $x[
    "EXAM_ID"
] ?></span></div><?php endforeach;
if (!$up): ?><div class="empty">No upcoming exams.</div><?php endif;
?></div></section>
<section class="panel quick"><div class="panel-head"><div><h2>Quick Actions</h2><p>Common administration tasks</p></div></div>
<a href="manage.php?table=STUDENTS">＋ Add student <span>→</span></a><a href="manage.php?table=EXAMINATION">＋ Schedule examination <span>→</span></a><a href="allocation.php">▦ Allocate rooms <span>→</span></a><a href="analytics.php">◒ View department analytics <span>→</span></a><a href="reports.php">▤ Generate report <span>→</span></a></section></div></main><?php include "end.php"; ?>
