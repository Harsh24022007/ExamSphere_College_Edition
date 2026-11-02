<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();

$exams = $pdo->query(
    "SELECT e.EXAM_ID,e.EXAM_DATE,e.START_TIME,s.SUBJECT_NAME FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID ORDER BY (e.EXAM_DATE>=CURDATE()) DESC,e.EXAM_DATE DESC,e.START_TIME DESC",
)->fetchAll();
$requestedExam = $_GET["exam_id"] ?? "";
$selectedExamId = "";
if (is_string($requestedExam) && $requestedExam !== "") {
    foreach ($exams as $examOption) {
        if ((string) $examOption["EXAM_ID"] === $requestedExam) {
            $selectedExamId = $requestedExam;
            break;
        }
    }
}
if ($selectedExamId === "" && $exams) {
    $selectedExamId = (string) $exams[0]["EXAM_ID"];
}

$overview = null;
$roomRows = [];
$uncoveredRoomCount = 0;
if ($selectedExamId !== "") {
    $overviewQuery = $pdo->prepare(
        "SELECT e.EXAM_ID,e.EXAM_DATE,e.START_TIME,e.END_TIME,s.SUBJECT_NAME,d.DEPT_NAME,COALESCE(regs.registered,0) registered,COALESCE(seats.allocated,0) allocated,COALESCE(regs.registered,0)-COALESCE(seats.allocated,0) unallocated,COALESCE(seats.present,0) present,COALESCE(seats.absent,0) absent,COALESCE(seats.unmarked,0) unmarked,COALESCE(seats.allocated_rooms,0) allocated_rooms,COALESCE(duties.duty_count,0) duty_count FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID JOIN DEPARTMENT d ON d.DEPT_ID=s.DEPT_ID LEFT JOIN (SELECT EXAM_ID,COUNT(*) registered FROM EXAM_REGISTRATION GROUP BY EXAM_ID) regs ON regs.EXAM_ID=e.EXAM_ID LEFT JOIN (SELECT er.EXAM_ID,COUNT(ra.ALLOCATION_ID) allocated,COUNT(DISTINCT ra.ROOM_ID) allocated_rooms,SUM(CASE WHEN LOWER(a.status)='present' THEN 1 ELSE 0 END) present,SUM(CASE WHEN LOWER(a.status)='absent' THEN 1 ELSE 0 END) absent,SUM(CASE WHEN ra.ALLOCATION_ID IS NOT NULL AND a.attendance_id IS NULL THEN 1 ELSE 0 END) unmarked FROM EXAM_REGISTRATION er LEFT JOIN ROOM_ALLOCATION ra ON ra.REGISTR_ID=er.REGISTR_ID LEFT JOIN attendance a ON a.allocation_id=ra.ALLOCATION_ID GROUP BY er.EXAM_ID) seats ON seats.EXAM_ID=e.EXAM_ID LEFT JOIN (SELECT EXAM_ID,COUNT(*) duty_count FROM INVIGILATOR_DUTY GROUP BY EXAM_ID) duties ON duties.EXAM_ID=e.EXAM_ID WHERE e.EXAM_ID=?",
    );
    $overviewQuery->execute([$selectedExamId]);
    $overview = $overviewQuery->fetch();

    $roomQuery = $pdo->prepare(
        "SELECT r.ROOM_ID,r.BUILDING,r.FLOOR,r.CAPACITY,r.STATUS,COALESCE(a.exam_seats,0) exam_seats,COALESCE(a.all_exam_seats,0) all_exam_seats,GREATEST(r.CAPACITY-COALESCE(a.all_exam_seats,0),0) free_seats,COALESCE(d.duty_count,0) duty_count,COALESCE(d.invigilator_names,'—') invigilator_names FROM ROOMS r LEFT JOIN (SELECT ra.ROOM_ID,SUM(CASE WHEN er.EXAM_ID=? THEN 1 ELSE 0 END) exam_seats,COUNT(*) all_exam_seats FROM ROOM_ALLOCATION ra JOIN EXAM_REGISTRATION er ON er.REGISTR_ID=ra.REGISTR_ID GROUP BY ra.ROOM_ID) a ON a.ROOM_ID=r.ROOM_ID LEFT JOIN (SELECT duty.ROOM_ID,COUNT(DISTINCT duty.INVIGILATOR_ID) duty_count,GROUP_CONCAT(DISTINCT i.INVIGILATOR_NAME ORDER BY i.INVIGILATOR_NAME SEPARATOR ', ') invigilator_names FROM INVIGILATOR_DUTY duty JOIN INVIGILATOR i ON i.INVIGILATOR_ID=duty.INVIGILATOR_ID WHERE duty.EXAM_ID=? GROUP BY duty.ROOM_ID) d ON d.ROOM_ID=r.ROOM_ID ORDER BY (r.STATUS='AVAILABLE') DESC,r.ROOM_ID",
    );
    $roomQuery->execute([$selectedExamId, $selectedExamId]);
    $roomRows = $roomQuery->fetchAll();
    foreach ($roomRows as $room) {
        if ((int) $room["exam_seats"] > 0 && (int) $room["duty_count"] === 0) {
            $uncoveredRoomCount++;
        }
    }
}

include "partials.php";
?>
<main class="content">
  <div class="content-title">
    <div>
      <span class="eyebrow">EXAM OPERATIONS</span>
      <h1>Readiness &amp; Capacity</h1>
      <p>Use joined registration, seating, room, duty, and attendance data to find and resolve exam-day gaps.</p>
    </div>
    <div class="title-actions"><a class="btn ghost" href="dashboard.php">← Dashboard</a></div>
  </div>

  <?php if (!$exams): ?>
    <section class="panel"><div class="empty">Create an examination to see its operational readiness.</div><a class="btn primary" href="manage.php?table=EXAMINATION">Schedule an examination</a></section>
  <?php else: ?>
    <section class="panel">
      <form method="get" class="filter">
        <label>Examination<select name="exam_id" required><?php foreach ($exams as $examOption): ?><option value="<?= e($examOption["EXAM_ID"]) ?>" <?= $selectedExamId === (string) $examOption["EXAM_ID"] ? "selected" : "" ?>><?= e($examOption["EXAM_DATE"]) ?> · <?= e($examOption["SUBJECT_NAME"]) ?> (#<?= e($examOption["EXAM_ID"]) ?>)</option><?php endforeach; ?></select></label>
        <button class="btn primary" type="submit">View readiness</button>
      </form>
    </section>

    <?php if ($overview): ?>
      <?php
      $examStarted = $overview["EXAM_DATE"] < date("Y-m-d")
          || ($overview["EXAM_DATE"] === date("Y-m-d") && $overview["START_TIME"] <= date("H:i:s"));
      $examEnded = $overview["EXAM_DATE"] < date("Y-m-d")
          || ($overview["EXAM_DATE"] === date("Y-m-d") && $overview["END_TIME"] <= date("H:i:s"));
      $readinessIssues = (int) $overview["unallocated"] + $uncoveredRoomCount;
      ?>
      <section class="panel operations-exam">
        <div class="panel-head"><div><h2><?= e($overview["SUBJECT_NAME"]) ?></h2><p><?= e($overview["DEPT_NAME"]) ?> · Exam #<?= e($overview["EXAM_ID"]) ?> · <?= e(date("D, d M Y", strtotime($overview["EXAM_DATE"]))) ?> · <?= e(substr($overview["START_TIME"], 0, 5)) ?>–<?= e(substr($overview["END_TIME"], 0, 5)) ?></p></div><span class="badge <?= $readinessIssues === 0 ? "ready" : "needs-attention" ?>"><?= $readinessIssues === 0 ? "Ready" : e($readinessIssues) . " issue(s)" ?></span></div>
        <div class="operations-actions"><a class="btn primary" href="allocation.php?exam_id=<?= urlencode($selectedExamId) ?>">Allocate unassigned students</a><a class="btn ghost" href="manage.php?table=INVIGILATOR_DUTY&amp;exam_id=<?= urlencode($selectedExamId) ?>">Assign invigilators</a><a class="btn ghost" href="seating.php?exam_id=<?= urlencode($selectedExamId) ?>">Open seating plan</a></div>
      </section>

      <section class="operations-metrics">
        <article class="operations-metric"><span>Registered</span><b><?= e($overview["registered"]) ?></b><small>Student registrations</small></article>
        <article class="operations-metric"><span>Seats assigned</span><b><?= e($overview["allocated"]) ?></b><small><?= e($overview["allocated_rooms"]) ?> room(s) used</small></article>
        <article class="operations-metric <?= (int) $overview["unallocated"] > 0 ? "attention" : "" ?>"><span>Need seats</span><b><?= e($overview["unallocated"]) ?></b><small><?= (int) $overview["unallocated"] > 0 ? "Allocate before the exam" : "All registered students have seats" ?></small></article>
        <article class="operations-metric <?= $uncoveredRoomCount > 0 ? "attention" : "" ?>"><span>Rooms without invigilator</span><b><?= e($uncoveredRoomCount) ?></b><small><?= e($overview["duty_count"]) ?> duty(s) assigned</small></article>
        <article class="operations-metric"><span>Attendance</span><b><?= e($overview["present"]) ?> / <?= e($overview["registered"]) ?></b><small><?= e($overview["absent"]) ?> absent · <?= e($overview["unmarked"]) ?> unmarked<?= $examStarted ? "" : " · opens at start time" ?></small></article>
      </section>

      <section class="panel">
        <div class="panel-head"><div><h2>Room capacity &amp; invigilator coverage</h2><p>Seat usage for this exam and total remaining seats across all current allocations.</p></div></div>
        <?php if (!$roomRows): ?><div class="empty">Add rooms to plan exam seating.</div><?php else: ?>
          <div class="table-wrap"><table><thead><tr><th>Room</th><th>Status</th><th>Capacity</th><th>This exam</th><th>Free seats</th><th>Invigilators</th><th>Action</th></tr></thead><tbody>
          <?php foreach ($roomRows as $room): ?>
            <?php $needsDuty = (int) $room["exam_seats"] > 0 && (int) $room["duty_count"] === 0; ?>
            <tr><td><b><?= e($room["ROOM_ID"]) ?></b><small><?= e($room["BUILDING"] ?: "Building not set") ?> · Floor <?= e($room["FLOOR"] ?: "—") ?></small></td><td><?= e($room["STATUS"]) ?></td><td><?= e($room["CAPACITY"]) ?></td><td><?= e($room["exam_seats"]) ?> seat(s)</td><td><?= e($room["free_seats"]) ?></td><td><?= e($room["invigilator_names"]) ?></td><td><?= $needsDuty ? '<a class="link" href="manage.php?table=INVIGILATOR_DUTY&amp;exam_id=' . urlencode($selectedExamId) . '">Assign duty</a>' : ((int) $room["exam_seats"] > 0 ? "Covered" : "Not used") ?></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-head"><div><h2>Registration &amp; attendance</h2><p>Joined student-level register for the selected examination.</p></div><a href="reports.php?exam_id=<?= urlencode($selectedExamId) ?>">Open reports →</a></div>
        <div class="operations-actions"><span class="badge">Exam status: <?= $examEnded ? "Completed" : ($examStarted ? "In progress" : "Upcoming") ?></span><span class="badge">Invigilator duties: <?= e($overview["duty_count"]) ?></span><span class="badge">Rooms with assigned seats: <?= e($overview["allocated_rooms"]) ?></span></div>
        <p class="hint">Invigilators record present/absent from their portal. Attendance and room assignments update here automatically.</p>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php include "end.php"; ?>
