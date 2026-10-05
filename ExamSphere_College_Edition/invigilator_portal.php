<?php
require_once "config.php";
start_role_session("invigilator");
require_role("invigilator");
$pdo = db();
$invigilatorId = (int) $_SESSION["invigilator_id"];
$profile = $pdo->prepare(
    "SELECT INVIGILATOR_NAME FROM INVIGILATOR WHERE INVIGILATOR_ID=?",
);
$profile->execute([$invigilatorId]);
$name = $profile->fetchColumn();
if ($name === false) {
    $_SESSION = [];
    session_destroy();
    redirect("login.php");
}
$jobs = $pdo->prepare(
    "SELECT e.EXAM_ID,e.EXAM_DATE,e.START_TIME,e.END_TIME,s.SUBJECT_NAME,d.DEPT_NAME,r.ROOM_ID,r.BUILDING,r.FLOOR,(e.EXAM_DATE<CURDATE() OR (e.EXAM_DATE=CURDATE() AND e.END_TIME<=CURTIME())) AS is_completed FROM INVIGILATOR_DUTY duty JOIN EXAMINATION e ON e.EXAM_ID=duty.EXAM_ID JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID JOIN DEPARTMENT d ON d.DEPT_ID=s.DEPT_ID JOIN ROOMS r ON r.ROOM_ID=duty.ROOM_ID WHERE duty.INVIGILATOR_ID=? ORDER BY e.EXAM_DATE,e.START_TIME,duty.ROOM_ID",
);
$jobs->execute([$invigilatorId]);
$jobs = $jobs->fetchAll();
$scheduled = [];
$completed = [];
foreach ($jobs as $job) {
    if ((int) $job["is_completed"] === 1) {
        $completed[] = $job;
    } else {
        $scheduled[] = $job;
    }
}
include "portal_header.php";
?>
<section class="portal-welcome"><div><span class="eyebrow">INVIGILATOR PORTAL</span><h1>Your examination timetable</h1><p>Welcome, <?= e(
    $name,
) ?>. Only duties assigned to your account are shown.</p></div><div class="portal-chip"><?= count(
    $scheduled,
) ?> upcoming · <?= count($completed) ?> completed</div></section>
<section class="portal-section" id="scheduled"><div class="portal-section-head"><div><span class="eyebrow">YOUR SCHEDULE</span><h2>Scheduled duties</h2><p>Upcoming and ongoing examination assignments.</p></div><span class="portal-count"><?= count(
    $scheduled,
) ?> scheduled</span></div>
<?php if (
    $scheduled
): ?><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Date</th><th>Examination</th><th>Department</th><th>Time</th><th>Room</th></tr></thead><tbody><?php foreach (
    $scheduled
    as $job
): ?><tr><td><?= e(
    date("D, d M Y", strtotime($job["EXAM_DATE"])),
) ?></td><td><b><?= e($job["SUBJECT_NAME"]) ?></b><small>Exam #<?= e(
    $job["EXAM_ID"],
) ?></small></td><td><?= e($job["DEPT_NAME"]) ?></td><td><?= e(
    substr($job["START_TIME"], 0, 5),
) ?>–<?= e(substr($job["END_TIME"], 0, 5)) ?></td><td>Room <?= e(
    $job["ROOM_ID"],
) ?><small><?= e($job["BUILDING"]) ?> · Floor <?= e(
     $job["FLOOR"],
 ) ?></small></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="portal-empty">No upcoming examination duties are assigned to you.</div><?php endif; ?></section>
<section class="portal-section" id="completed"><div class="portal-section-head"><div><span class="eyebrow">DUTY HISTORY</span><h2>Completed duties</h2><p>Assignments whose examination end time has passed.</p></div><span class="portal-count"><?= count(
    $completed,
) ?> completed</span></div>
<?php if (
    $completed
): ?><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Date</th><th>Examination</th><th>Department</th><th>Time</th><th>Room</th></tr></thead><tbody><?php foreach (
    $completed
    as $job
): ?><tr><td><?= e(
    date("D, d M Y", strtotime($job["EXAM_DATE"])),
) ?></td><td><b><?= e($job["SUBJECT_NAME"]) ?></b><small>Exam #<?= e(
    $job["EXAM_ID"],
) ?></small></td><td><?= e($job["DEPT_NAME"]) ?></td><td><?= e(
    substr($job["START_TIME"], 0, 5),
) ?>–<?= e(substr($job["END_TIME"], 0, 5)) ?></td><td>Room <?= e(
    $job["ROOM_ID"],
) ?><small><?= e($job["BUILDING"]) ?> · Floor <?= e(
     $job["FLOOR"],
 ) ?></small></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="portal-empty">Your completed duty history will appear here.</div><?php endif; ?></section>
<?php include "portal_footer.php"; ?>
