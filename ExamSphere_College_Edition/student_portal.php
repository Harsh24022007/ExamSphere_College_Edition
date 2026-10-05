<?php
require_once "config.php";
start_role_session("student");
require_role("student");
$pdo = db();
$studentId = (int) $_SESSION["student_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        $pdo->beginTransaction();
        $studentQuery = $pdo->prepare(
            "SELECT STUDENT_ID,DEPT_ID,CURRENT_SEMESTER FROM STUDENTS WHERE STUDENT_ID=? FOR UPDATE",
        );
        $studentQuery->execute([$studentId]);
        $student = $studentQuery->fetch();
        if (!$student) {
            throw new RuntimeException(
                "Your student profile is no longer active.",
            );
        }

        $examId = (int) ($_POST["exam_id"] ?? 0);
        $examQuery = $pdo->prepare(
            "SELECT e.EXAM_ID FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID WHERE e.EXAM_ID=? AND e.published=1 AND (e.EXAM_DATE>CURDATE() OR (e.EXAM_DATE=CURDATE() AND e.END_TIME>CURTIME())) AND s.DEPT_ID=? AND s.OFFERED_SEMESTER=? AND NOT EXISTS (SELECT 1 FROM EXAM_REGISTRATION er WHERE er.EXAM_ID=e.EXAM_ID AND er.STUDENT_ID=?) FOR UPDATE",
        );
        $examQuery->execute([
            $examId,
            $student["DEPT_ID"],
            $student["CURRENT_SEMESTER"],
            $studentId,
        ]);
        if (!$examQuery->fetchColumn()) {
            throw new RuntimeException(
                "That examination is not open for your department and semester, is no longer upcoming, or you are already registered.",
            );
        }

        $registrationId = (int) $pdo
            ->query(
                "SELECT COALESCE(MAX(REGISTR_ID),0)+1 FROM EXAM_REGISTRATION",
            )
            ->fetchColumn();
        $insert = $pdo->prepare(
            "INSERT INTO EXAM_REGISTRATION (REGISTR_ID,STUDENT_ID,EXAM_ID,REGISTR_DATE) VALUES (?,?,?,CURDATE())",
        );
        $insert->execute([$registrationId, $studentId, $examId]);
        $pdo->commit();
        flash("success", "You are registered for the examination.");
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("danger", "Registration failed: " . $ex->getMessage());
    }
    redirect("student_portal.php#available");
}

$profile = $pdo->prepare(
    "SELECT s.STUDENT_ID,CONCAT_WS(' ',s.FNAME,s.MNAME,s.LNAME) full_name,s.CURRENT_SEMESTER,s.DEPT_ID,d.DEPT_NAME FROM STUDENTS s JOIN DEPARTMENT d ON d.DEPT_ID=s.DEPT_ID WHERE s.STUDENT_ID=?",
);
$profile->execute([$studentId]);
$student = $profile->fetch();
if (!$student) {
    $_SESSION = [];
    session_destroy();
    redirect("login.php");
}
$registered = $pdo->prepare(
    "SELECT e.EXAM_ID,e.EXAM_DATE,e.START_TIME,e.END_TIME,s.SUBJECT_NAME,s.OFFERED_SEMESTER,d.DEPT_NAME,ra.ROOM_ID,r.BUILDING,ra.SEAT_NO,(e.EXAM_DATE<CURDATE() OR (e.EXAM_DATE=CURDATE() AND e.END_TIME<=CURTIME())) AS is_completed FROM EXAM_REGISTRATION er JOIN EXAMINATION e ON e.EXAM_ID=er.EXAM_ID JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID JOIN DEPARTMENT d ON d.DEPT_ID=s.DEPT_ID LEFT JOIN ROOM_ALLOCATION ra ON ra.REGISTR_ID=er.REGISTR_ID LEFT JOIN ROOMS r ON r.ROOM_ID=ra.ROOM_ID WHERE er.STUDENT_ID=? ORDER BY e.EXAM_DATE,e.START_TIME",
);
$registered->execute([$studentId]);
$registered = $registered->fetchAll();
$scheduled = [];
$completed = [];
foreach ($registered as $exam) {
    if ((int) $exam["is_completed"] === 1) {
        $completed[] = $exam;
    } else {
        $scheduled[] = $exam;
    }
}
$available = $pdo->prepare(
    "SELECT e.EXAM_ID,e.EXAM_DATE,e.START_TIME,e.END_TIME,s.SUBJECT_NAME FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID WHERE e.published=1 AND (e.EXAM_DATE>CURDATE() OR (e.EXAM_DATE=CURDATE() AND e.END_TIME>CURTIME())) AND s.DEPT_ID=? AND s.OFFERED_SEMESTER=? AND NOT EXISTS (SELECT 1 FROM EXAM_REGISTRATION er WHERE er.EXAM_ID=e.EXAM_ID AND er.STUDENT_ID=?) ORDER BY e.EXAM_DATE,e.START_TIME",
);
$available->execute([
    (int) $student["DEPT_ID"],
    (int) $student["CURRENT_SEMESTER"],
    $studentId,
]);
$available = $available->fetchAll();
include "portal_header.php";
?>
<section class="portal-welcome"><div><span class="eyebrow">STUDENT EXAM PORTAL</span><h1>Hello, <?= e(
    $student["full_name"],
) ?></h1><p><?= e($student["DEPT_NAME"]) ?> · Semester <?= e(
     $student["CURRENT_SEMESTER"],
 ) ?> · Student #<?= e(
     $student["STUDENT_ID"],
 ) ?></p></div><div class="portal-chip">Your schedule, in one place</div></section>
<section class="portal-section" id="scheduled"><div class="portal-section-head"><div><span class="eyebrow">PERSONAL TIMETABLE</span><h2>Scheduled examinations</h2><p>Your upcoming registered exams and seat assignments.</p></div><span class="portal-count"><?= count(
    $scheduled,
) ?> scheduled</span></div>
<?php if (
    $scheduled
): ?><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Date</th><th>Examination</th><th>Time</th><th>Semester</th><th>Seat</th></tr></thead><tbody><?php foreach (
    $scheduled
    as $exam
): ?><tr><td><?= e(
    date("D, d M Y", strtotime($exam["EXAM_DATE"])),
) ?></td><td><b><?= e($exam["SUBJECT_NAME"]) ?></b><small>Exam #<?= e(
    $exam["EXAM_ID"],
) ?></small></td><td><?= e(substr($exam["START_TIME"], 0, 5)) ?>–<?= e(
    substr($exam["END_TIME"], 0, 5),
) ?></td><td><?= e($exam["OFFERED_SEMESTER"]) ?></td><td><?= $exam["ROOM_ID"]
    ? "Room " . e($exam["ROOM_ID"]) . " · Seat " . e($exam["SEAT_NO"])
    : "Not assigned yet" ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="portal-empty">You have no upcoming registered exams.</div><?php endif; ?></section>
<section class="portal-section" id="completed"><div class="portal-section-head"><div><span class="eyebrow">EXAM HISTORY</span><h2>Completed examinations</h2><p>Exams whose scheduled end time has passed.</p></div><span class="portal-count"><?= count(
    $completed,
) ?> completed</span></div>
<?php if (
    $completed
): ?><div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Date</th><th>Examination</th><th>Time</th><th>Semester</th><th>Seat</th></tr></thead><tbody><?php foreach (
    $completed
    as $exam
): ?><tr><td><?= e(
    date("D, d M Y", strtotime($exam["EXAM_DATE"])),
) ?></td><td><b><?= e($exam["SUBJECT_NAME"]) ?></b><small>Exam #<?= e(
    $exam["EXAM_ID"],
) ?></small></td><td><?= e(substr($exam["START_TIME"], 0, 5)) ?>–<?= e(
    substr($exam["END_TIME"], 0, 5),
) ?></td><td><?= e($exam["OFFERED_SEMESTER"]) ?></td><td><?= $exam["ROOM_ID"]
    ? "Room " . e($exam["ROOM_ID"]) . " · Seat " . e($exam["SEAT_NO"])
    : "Not assigned" ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="portal-empty">Your completed exam history will appear here.</div><?php endif; ?></section>
<section class="portal-section" id="available"><div class="portal-section-head"><div><span class="eyebrow">ELIGIBLE EXAMS</span><h2>Register for an examination</h2><p>Only published upcoming exams for your department and current semester are listed.</p></div></div>
<?php if ($available): ?><div class="portal-exam-grid"><?php foreach (
    $available
    as $exam
): ?><article class="portal-exam-card"><div class="portal-exam-date"><b><?= e(
    date("d", strtotime($exam["EXAM_DATE"])),
) ?></b><span><?= e(
    date("M Y", strtotime($exam["EXAM_DATE"])),
) ?></span></div><div class="portal-exam-details"><h3><?= e(
    $exam["SUBJECT_NAME"],
) ?></h3><p>Semester <?= e($student["CURRENT_SEMESTER"]) ?> · <?= e(
     substr($exam["START_TIME"], 0, 5),
 ) ?>–<?= e(
    substr($exam["END_TIME"], 0, 5),
) ?></p></div><form method="post" onsubmit="return confirm('Register for <?= e(
    $exam["SUBJECT_NAME"],
) ?>?')"><input type="hidden" name="csrf_token" value="<?= e(
    csrf_token(),
) ?>"><input type="hidden" name="exam_id" value="<?= e(
    $exam["EXAM_ID"],
) ?>"><button class="btn primary">Register</button></form></article><?php endforeach; ?></div><?php else: ?><div class="portal-empty">There are no open upcoming examinations for your department and semester right now.</div><?php endif; ?></section>
<?php include "portal_footer.php"; ?>
