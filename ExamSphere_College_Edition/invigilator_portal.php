<?php
require_once "config.php";
start_role_session("invigilator");
require_role("invigilator");
$pdo = db();
$invigilatorId = (int) $_SESSION["invigilator_id"];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        $allocationId = filter_var(
            $_POST["allocation_id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]],
        );
        $status = strtolower(trim((string) ($_POST["status"] ?? "")));
        if ($allocationId === false || !in_array($status, ["present", "absent"], true)) {
            throw new RuntimeException("Choose a valid student and attendance status.");
        }

        $pdo->beginTransaction();
        $authorized = $pdo->prepare(
            "SELECT ra.ALLOCATION_ID FROM INVIGILATOR_DUTY duty JOIN ROOM_ALLOCATION ra ON ra.ROOM_ID=duty.ROOM_ID JOIN EXAM_REGISTRATION er ON er.REGISTR_ID=ra.REGISTR_ID AND er.EXAM_ID=duty.EXAM_ID JOIN EXAMINATION e ON e.EXAM_ID=duty.EXAM_ID WHERE duty.INVIGILATOR_ID=? AND ra.ALLOCATION_ID=? AND (e.EXAM_DATE<CURDATE() OR (e.EXAM_DATE=CURDATE() AND e.START_TIME<=CURTIME())) FOR UPDATE",
        );
        $authorized->execute([$invigilatorId, $allocationId]);
        if (!$authorized->fetchColumn()) {
            throw new RuntimeException("Attendance can only be recorded for a student assigned to your duty after the exam begins.");
        }

        $save = $pdo->prepare(
            "INSERT INTO attendance (allocation_id,status,checked_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),checked_by=VALUES(checked_by)",
        );
        $save->execute([$allocationId, $status, (int) $_SESSION["user_id"]]);
        $pdo->commit();
        flash("success", "Attendance marked " . $status . ".");
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("danger", $ex->getMessage());
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ExamSphere attendance marking error: " . $ex->getMessage());
        flash("danger", "Attendance could not be saved. Check the database migration and try again.");
    }
    redirect("invigilator_portal.php#attendance");
}
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
    "SELECT e.EXAM_ID,e.EXAM_DATE,e.START_TIME,e.END_TIME,s.SUBJECT_NAME,d.DEPT_NAME,r.ROOM_ID,r.BUILDING,r.FLOOR,(e.EXAM_DATE<CURDATE() OR (e.EXAM_DATE=CURDATE() AND e.START_TIME<=CURTIME())) AS is_started,(e.EXAM_DATE<CURDATE() OR (e.EXAM_DATE=CURDATE() AND e.END_TIME<=CURTIME())) AS is_completed FROM INVIGILATOR_DUTY duty JOIN EXAMINATION e ON e.EXAM_ID=duty.EXAM_ID JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID JOIN DEPARTMENT d ON d.DEPT_ID=s.DEPT_ID JOIN ROOMS r ON r.ROOM_ID=duty.ROOM_ID WHERE duty.INVIGILATOR_ID=? ORDER BY e.EXAM_DATE,e.START_TIME,duty.ROOM_ID",
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
$roster = $pdo->prepare(
    "SELECT duty.EXAM_ID,duty.ROOM_ID,ra.ALLOCATION_ID,ra.SEAT_NO,st.STUDENT_ID,CONCAT_WS(' ',st.FNAME,st.MNAME,st.LNAME) AS student_name,a.status AS attendance_status FROM INVIGILATOR_DUTY duty JOIN ROOM_ALLOCATION ra ON ra.ROOM_ID=duty.ROOM_ID JOIN EXAM_REGISTRATION er ON er.REGISTR_ID=ra.REGISTR_ID AND er.EXAM_ID=duty.EXAM_ID JOIN STUDENTS st ON st.STUDENT_ID=er.STUDENT_ID LEFT JOIN attendance a ON a.allocation_id=ra.ALLOCATION_ID WHERE duty.INVIGILATOR_ID=? ORDER BY duty.EXAM_ID,duty.ROOM_ID,ra.SEAT_NO",
);
$roster->execute([$invigilatorId]);
$rostersByDuty = [];
foreach ($roster->fetchAll() as $row) {
    $key = $row["EXAM_ID"] . "\0" . $row["ROOM_ID"];
    $rostersByDuty[$key][] = $row;
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
<section class="portal-section" id="attendance"><div class="portal-section-head"><div><span class="eyebrow">EXAM ROSTER</span><h2>Attendance register</h2><p>Mark attendance only for students allocated to your room.</p></div></div>
<?php if (!$jobs): ?><div class="portal-empty">Attendance rosters will appear here when an examination duty is assigned.</div><?php else: ?>
<?php foreach ($jobs as $job):
    $dutyKey = $job["EXAM_ID"] . "\0" . $job["ROOM_ID"];
    $rosterRows = $rostersByDuty[$dutyKey] ?? [];
    ?><div class="attendance-duty"><div class="attendance-duty-head"><div><b><?= e(
        $job["SUBJECT_NAME"],
    ) ?></b><span>Exam #<?= e($job["EXAM_ID"]) ?> · Room <?= e($job["ROOM_ID"]) ?> · <?= e(
        date("D, d M Y", strtotime($job["EXAM_DATE"])),
    ) ?></span></div><span class="portal-count"><?= count($rosterRows) ?> students</span></div>
<?php if (!$rosterRows): ?><div class="portal-empty">No students are allocated to this room yet.</div><?php elseif ((int) $job["is_started"] !== 1): ?><div class="portal-empty">Attendance opens at the scheduled exam start time.</div><?php else: ?>
<div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Seat</th><th>Student</th><th>Status</th><th>Mark attendance</th></tr></thead><tbody><?php foreach ($rosterRows as $student): ?><tr><td><?= e(
        $student["SEAT_NO"],
    ) ?></td><td><b><?= e($student["student_name"]) ?></b><small>Student #<?= e(
        $student["STUDENT_ID"],
    ) ?></small></td><td><span class="attendance-status <?= e(strtolower($student["attendance_status"] ?? "unmarked")) ?>"><?= e(
        ucfirst($student["attendance_status"] ?? "Not marked"),
    ) ?></span></td><td><form method="post" class="attendance-actions"><input type="hidden" name="csrf_token" value="<?= e(
        csrf_token(),
    ) ?>"><input type="hidden" name="allocation_id" value="<?= e(
        $student["ALLOCATION_ID"],
    ) ?>"><button class="attendance-button present" name="status" value="present">Present</button><button class="attendance-button absent" name="status" value="absent">Absent</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?></div><?php endforeach; ?>
<?php endif; ?></section>
<?php include "portal_footer.php"; ?>
