<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $examId = (int) ($_POST["exam_id"] ?? 0);
    $roomId = (int) ($_POST["room_id"] ?? 0);
    try {
        require_csrf();
        $allocationId = (int) ($_POST["allocation_id"] ?? 0);
        $seat = trim((string) ($_POST["seat_no"] ?? ""));
        if (!ctype_digit($seat) || (int) $seat < 1) {
            throw new RuntimeException("Choose a valid numbered seat.");
        }

        $roomQuery = $pdo->prepare(
            "SELECT CAPACITY FROM ROOMS WHERE ROOM_ID=?",
        );
        $roomQuery->execute([$roomId]);
        $capacity = $roomQuery->fetchColumn();
        if ($capacity === false || (int) $seat > (int) $capacity) {
            throw new RuntimeException(
                "The selected seat is outside this room’s capacity.",
            );
        }

        $allocationQuery = $pdo->prepare(
            "SELECT ra.ALLOCATION_ID FROM ROOM_ALLOCATION ra JOIN EXAM_REGISTRATION er ON er.REGISTR_ID=ra.REGISTR_ID WHERE ra.ALLOCATION_ID=? AND ra.ROOM_ID=? AND er.EXAM_ID=?",
        );
        $allocationQuery->execute([$allocationId, $roomId, $examId]);
        if (!$allocationQuery->fetchColumn()) {
            throw new RuntimeException(
                "The selected student is no longer assigned to this room and examination.",
            );
        }

        $occupied = $pdo->prepare(
            "SELECT 1 FROM ROOM_ALLOCATION WHERE ROOM_ID=? AND SEAT_NO=? AND ALLOCATION_ID<>?",
        );
        $occupied->execute([$roomId, $seat, $allocationId]);
        if ($occupied->fetchColumn()) {
            throw new RuntimeException("That seat is already occupied.");
        }

        $update = $pdo->prepare(
            "UPDATE ROOM_ALLOCATION SET SEAT_NO=? WHERE ALLOCATION_ID=?",
        );
        $update->execute([$seat, $allocationId]);
        flash("success", "Seat assignment saved.");
    } catch (Throwable $ex) {
        flash("danger", "Could not move the student: " . $ex->getMessage());
    }
    redirect("seating.php?room_id=" . $roomId . "&exam_id=" . $examId);
}

$rooms = $pdo->query("SELECT * FROM ROOMS ORDER BY ROOM_ID")->fetchAll();
$exams = $pdo
    ->query(
        "SELECT e.EXAM_ID,e.EXAM_DATE,s.SUBJECT_NAME FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID ORDER BY e.EXAM_DATE DESC",
    )
    ->fetchAll();
$rid = (int) ($_GET["room_id"] ?? ($rooms[0]["ROOM_ID"] ?? 0));
$eid = (int) ($_GET["exam_id"] ?? ($exams[0]["EXAM_ID"] ?? 0));
$room = null;
foreach ($rooms as $candidate) {
    if ((int) $candidate["ROOM_ID"] === $rid) {
        $room = $candidate;
    }
}
$alloc = [];
$blockedSeats = [];
if ($room && $eid) {
    $query = $pdo->prepare(
        "SELECT ra.ALLOCATION_ID,ra.REGISTR_ID,ra.SEAT_NO,er.EXAM_ID,CONCAT_WS(' ',s.FNAME,s.MNAME,s.LNAME) student,s.STUDENT_ID FROM ROOM_ALLOCATION ra JOIN EXAM_REGISTRATION er ON er.REGISTR_ID=ra.REGISTR_ID JOIN STUDENTS s ON s.STUDENT_ID=er.STUDENT_ID WHERE ra.ROOM_ID=? AND er.EXAM_ID=? ORDER BY CAST(ra.SEAT_NO AS UNSIGNED)",
    );
    $query->execute([$rid, $eid]);
    foreach ($query as $assignment) {
        $alloc[(string) $assignment["SEAT_NO"]] = $assignment;
    }
    $occupiedQuery = $pdo->prepare(
        "SELECT SEAT_NO FROM ROOM_ALLOCATION WHERE ROOM_ID=?",
    );
    $occupiedQuery->execute([$rid]);
    foreach ($occupiedQuery->fetchAll(PDO::FETCH_COLUMN) as $occupiedSeat) {
        $blockedSeats[(string) $occupiedSeat] = true;
    }
}
include "partials.php";
?>
<main class="content">
<div class="content-title"><div><span class="eyebrow">VISUAL SEATING STUDIO</span><h1>Room Seating Layout</h1><p>Drag an assigned student onto an available seat to save the change in the database.</p></div><button class="btn primary" type="button" onclick="window.print()">Print room plan</button></div>
<section class="panel seat-toolbar"><form method="get"><label>Examination<select name="exam_id" onchange="this.form.submit()"><option value="">Choose examination</option><?php foreach (
    $exams
    as $exam
): ?><option value="<?= $exam["EXAM_ID"] ?>" <?= $eid === $exam["EXAM_ID"]
    ? "selected"
    : "" ?>>#<?= $exam["EXAM_ID"] ?> — <?= e($exam["SUBJECT_NAME"]) ?> (<?= e(
     $exam["EXAM_DATE"],
 ) ?>)</option><?php endforeach; ?></select></label><label>Room<select name="room_id" onchange="this.form.submit()"><option value="">Choose room</option><?php foreach (
    $rooms
    as $roomOption
): ?><option value="<?= $roomOption["ROOM_ID"] ?>" <?= $rid ===
$roomOption["ROOM_ID"]
    ? "selected"
    : "" ?>>Room <?= $roomOption["ROOM_ID"] ?> — <?= e(
     $roomOption["BUILDING"],
 ) ?> — Capacity <?= $roomOption[
     "CAPACITY"
 ] ?></option><?php endforeach; ?></select></label></form><div class="legend"><span><i class="seat free"></i>Available</span><span><i class="seat filled"></i>Allocated for exam</span><span><i class="seat blocked"></i>In use</span></div></section>
<?php if (
    !$room
): ?><section class="panel empty">Add a room to begin building a seating plan.</section>
<?php elseif (
    !$eid
): ?><section class="panel empty">Add an examination to view its seating plan.</section>
<?php else: ?>
<section class="panel room-canvas"><div class="room-header"><div><b>ROOM <?= e(
    $room["ROOM_ID"],
) ?></b><span><?= e($room["BUILDING"]) ?> • Floor <?= e(
     $room["FLOOR"],
 ) ?></span></div><span class="badge"><?= count($alloc) ?> / <?= e(
     $room["CAPACITY"],
 ) ?> occupied</span></div><div class="board"><span>BOARD / SCREEN</span></div>
<div class="seat-grid" style="--capacity:<?= max(
    1,
    (int) $room["CAPACITY"],
) ?>"><?php for ($seatNo = 1; $seatNo <= (int) $room["CAPACITY"]; $seatNo++):

    $assignment = $alloc[(string) $seatNo] ?? null;
    $blocked = !$assignment && isset($blockedSeats[(string) $seatNo]);
    ?><div class="seat-wrap"><div class="seat <?= $assignment
    ? "filled"
    : ($blocked
        ? "blocked"
        : "free") ?>" draggable="<?= $assignment
    ? "true"
    : "false" ?>" data-seat="<?= $seatNo ?>" data-allocation="<?= $assignment
    ? e($assignment["ALLOCATION_ID"])
    : "" ?>" title="<?= $assignment
    ? e($assignment["student"])
    : ($blocked
        ? "Occupied by another examination"
        : "Available seat " . $seatNo) ?>"><span><?= $seatNo ?></span><?php if (
    $assignment
): ?><small><?= e($assignment["STUDENT_ID"]) ?></small><?php elseif (
    $blocked
): ?><small>IN USE</small><?php endif; ?></div><?php if (
    $assignment
): ?><em><?= e($assignment["student"]) ?></em><?php endif; ?></div><?php
endfor; ?></div></section>
<form id="seat-move-form" method="post" hidden><input type="hidden" name="csrf_token" value="<?= e(
    csrf_token(),
) ?>"><input type="hidden" name="exam_id" value="<?= $eid ?>"><input type="hidden" name="room_id" value="<?= $rid ?>"><input type="hidden" name="allocation_id" id="move-allocation"><input type="hidden" name="seat_no" id="move-seat"></form>
<?php endif; ?>
</main>
<script src="assets/app.js"></script>
<script>
let draggedSeat=null;
document.querySelectorAll('.seat.filled').forEach(seat=>seat.addEventListener('dragstart',()=>{draggedSeat=seat;}));
document.querySelectorAll('.seat.free').forEach(seat=>{
  seat.addEventListener('dragover',event=>event.preventDefault());
  seat.addEventListener('drop',event=>{
    event.preventDefault();
    if(!draggedSeat||draggedSeat===seat) return;
    document.getElementById('move-allocation').value=draggedSeat.dataset.allocation;
    document.getElementById('move-seat').value=seat.dataset.seat;
    document.getElementById('seat-move-form').requestSubmit();
  });
});
</script>
<?php include "end.php"; ?>
