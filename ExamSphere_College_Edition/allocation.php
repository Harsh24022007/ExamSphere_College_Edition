<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $exam = (int) ($_POST["exam_id"] ?? 0);
    try {
        require_csrf();
        $pdo->beginTransaction();
        $examCheck = $pdo->prepare(
            "SELECT EXAM_ID FROM EXAMINATION WHERE EXAM_ID=?",
        );
        $examCheck->execute([$exam]);
        if (!$examCheck->fetchColumn()) {
            throw new RuntimeException("Select a valid examination.");
        }

        if (($_POST["mode"] ?? "manual") === "auto") {
            $rooms = $pdo
                ->query(
                    "SELECT ROOM_ID,CAPACITY FROM ROOMS WHERE STATUS='AVAILABLE' ORDER BY ROOM_ID",
                )
                ->fetchAll();
            $registrations = $pdo->prepare(
                "SELECT er.REGISTR_ID FROM EXAM_REGISTRATION er LEFT JOIN ROOM_ALLOCATION ra ON ra.REGISTR_ID=er.REGISTR_ID WHERE er.EXAM_ID=? AND ra.REGISTR_ID IS NULL ORDER BY er.REGISTR_ID",
            );
            $registrations->execute([$exam]);
            $registrations = $registrations->fetchAll(PDO::FETCH_COLUMN);
            $usedSeats = [];
            foreach (
                $pdo
                    ->query("SELECT ROOM_ID,SEAT_NO FROM ROOM_ALLOCATION")
                    ->fetchAll()
                as $used
            ) {
                $usedSeats[(int) $used["ROOM_ID"]][
                    (string) $used["SEAT_NO"]
                ] = true;
            }
            $nextId = (int) $pdo
                ->query(
                    "SELECT COALESCE(MAX(ALLOCATION_ID),0)+1 FROM ROOM_ALLOCATION",
                )
                ->fetchColumn();
            $roomIndex = 0;
            $made = 0;
            foreach ($registrations as $registrationId) {
                while (isset($rooms[$roomIndex])) {
                    $capacity = (int) $rooms[$roomIndex]["CAPACITY"];
                    $seat = null;
                    for (
                        $candidate = 1;
                        $candidate <= $capacity;
                        $candidate++
                    ) {
                        if (
                            empty(
                                $usedSeats[(int) $rooms[$roomIndex]["ROOM_ID"]][
                                    (string) $candidate
                                ]
                            )
                        ) {
                            $seat = $candidate;
                            break;
                        }
                    }
                    if ($seat !== null) {
                        break;
                    }
                    $roomIndex++;
                }
                if (!isset($rooms[$roomIndex])) {
                    break;
                }
                $roomId = (int) $rooms[$roomIndex]["ROOM_ID"];
                $insert = $pdo->prepare(
                    "INSERT INTO ROOM_ALLOCATION (ALLOCATION_ID,REGISTR_ID,ROOM_ID,SEAT_NO) VALUES (?,?,?,?)",
                );
                $insert->execute([
                    $nextId++,
                    $registrationId,
                    $roomId,
                    (string) $seat,
                ]);
                $usedSeats[$roomId][(string) $seat] = true;
                $made++;
            }
            $left = count($registrations) - $made;
            $pdo->commit();
            flash(
                $left ? "danger" : "success",
                $left
                    ? "$made registration(s) allocated; $left could not be seated because no available room capacity remains."
                    : "$made registration(s) allocated successfully.",
            );
        } else {
            $registrationId = (int) ($_POST["registr_id"] ?? 0);
            $roomId = (int) ($_POST["room_id"] ?? 0);
            $seat = trim((string) ($_POST["seat_no"] ?? ""));
            if (!ctype_digit($seat) || (int) $seat < 1) {
                throw new RuntimeException(
                    "Seat number must be a positive number.",
                );
            }

            $registration = $pdo->prepare(
                "SELECT REGISTR_ID FROM EXAM_REGISTRATION WHERE REGISTR_ID=? AND EXAM_ID=?",
            );
            $registration->execute([$registrationId, $exam]);
            if (!$registration->fetchColumn()) {
                throw new RuntimeException(
                    "Choose an unassigned registration for the selected examination.",
                );
            }

            $room = $pdo->prepare(
                "SELECT CAPACITY FROM ROOMS WHERE ROOM_ID=? AND STATUS='AVAILABLE'",
            );
            $room->execute([$roomId]);
            $capacity = $room->fetchColumn();
            if ($capacity === false) {
                throw new RuntimeException("Choose an available room.");
            }
            if ((int) $seat > (int) $capacity) {
                throw new RuntimeException(
                    "That seat number exceeds the room capacity.",
                );
            }

            $occupied = $pdo->prepare(
                "SELECT 1 FROM ROOM_ALLOCATION WHERE ROOM_ID=? AND SEAT_NO=?",
            );
            $occupied->execute([$roomId, $seat]);
            if ($occupied->fetchColumn()) {
                throw new RuntimeException(
                    "That seat is already occupied in this room.",
                );
            }

            $allocationId = (int) $pdo
                ->query(
                    "SELECT COALESCE(MAX(ALLOCATION_ID),0)+1 FROM ROOM_ALLOCATION",
                )
                ->fetchColumn();
            $insert = $pdo->prepare(
                "INSERT INTO ROOM_ALLOCATION (ALLOCATION_ID,REGISTR_ID,ROOM_ID,SEAT_NO) VALUES (?,?,?,?)",
            );
            $insert->execute([$allocationId, $registrationId, $roomId, $seat]);
            $pdo->commit();
            flash("success", "Seat allocated successfully.");
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("danger", "Allocation failed: " . $ex->getMessage());
    }
    redirect("allocation.php");
}

$exams = $pdo
    ->query(
        "SELECT e.EXAM_ID,e.EXAM_DATE,s.SUBJECT_NAME FROM EXAMINATION e JOIN SUBJECT s ON e.SUBJECT_ID=s.SUBJECT_ID ORDER BY e.EXAM_DATE DESC",
    )
    ->fetchAll();
$rooms = $pdo->query("SELECT * FROM ROOMS ORDER BY ROOM_ID")->fetchAll();
$regs = $pdo
    ->query(
        "SELECT er.REGISTR_ID,er.EXAM_ID,er.STUDENT_ID,CONCAT_WS(' ',s.FNAME,s.MNAME,s.LNAME) NAME FROM EXAM_REGISTRATION er JOIN STUDENTS s ON s.STUDENT_ID=er.STUDENT_ID LEFT JOIN ROOM_ALLOCATION ra ON ra.REGISTR_ID=er.REGISTR_ID WHERE ra.REGISTR_ID IS NULL ORDER BY er.REGISTR_ID",
    )
    ->fetchAll();
$alloc = $pdo
    ->query(
        "SELECT ra.*,er.EXAM_ID,CONCAT_WS(' ',s.FNAME,s.MNAME,s.LNAME) NAME,r.BUILDING,r.FLOOR FROM ROOM_ALLOCATION ra JOIN EXAM_REGISTRATION er ON er.REGISTR_ID=ra.REGISTR_ID JOIN STUDENTS s ON s.STUDENT_ID=er.STUDENT_ID JOIN ROOMS r ON r.ROOM_ID=ra.ROOM_ID ORDER BY er.EXAM_ID,ra.ROOM_ID,CAST(ra.SEAT_NO AS UNSIGNED)",
    )
    ->fetchAll();
include "partials.php";
?>
<main class="page">
<div class="page-title"><div><span class="eyebrow">SEATING ENGINE</span><h1>Room Allocation</h1><p>Assign seats manually or automatically, with room capacity and occupied seats checked before saving.</p></div></div>
<div class="two-col">
<section class="panel"><div class="panel-head"><h2>Automatic Allocation</h2></div><form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(
    csrf_token(),
) ?>"><input type="hidden" name="mode" value="auto"><label>Examination<select name="exam_id" required><option value="">Choose an examination</option><?php foreach (
    $exams
    as $examOption
): ?><option value="<?= $examOption["EXAM_ID"] ?>">#<?= $examOption[
    "EXAM_ID"
] ?> — <?= e($examOption["SUBJECT_NAME"]) ?> (<?= e(
     $examOption["EXAM_DATE"],
 ) ?>)</option><?php endforeach; ?></select></label><button class="btn primary" type="submit" <?= $exams
    ? ""
    : "disabled" ?>>Allocate Unassigned Students</button></form><p class="hint">Uses free numbered seats in available rooms and preserves existing assignments.</p></section>
<section class="panel"><div class="panel-head"><h2>Manual Allocation</h2></div><form method="post" class="form-grid compact"><input type="hidden" name="csrf_token" value="<?= e(
    csrf_token(),
) ?>"><input type="hidden" name="mode" value="manual">
<label>Examination<select name="exam_id" id="allocation-exam" required><option value="">Choose</option><?php foreach (
    $exams
    as $examOption
): ?><option value="<?= $examOption["EXAM_ID"] ?>">#<?= $examOption[
    "EXAM_ID"
] ?> — <?= e(
     $examOption["SUBJECT_NAME"],
 ) ?></option><?php endforeach; ?></select></label>
<label>Registration<select name="registr_id" id="allocation-registration" required><option value="">Choose exam first</option><?php foreach (
    $regs
    as $registration
): ?><option value="<?= $registration[
    "REGISTR_ID"
] ?>" data-exam="<?= $registration["EXAM_ID"] ?>">#<?= $registration[
    "REGISTR_ID"
] ?> — <?= e($registration["NAME"]) ?> / Exam <?= $registration[
     "EXAM_ID"
 ] ?></option><?php endforeach; ?></select></label>
<label>Room<select name="room_id" required><option value="">Choose an available room</option><?php foreach (
    $rooms
    as $room
):
    if ($room["STATUS"] === "AVAILABLE"): ?><option value="<?= $room[
    "ROOM_ID"
] ?>" data-capacity="<?= $room["CAPACITY"] ?>">Room <?= $room[
    "ROOM_ID"
] ?> — <?= e($room["BUILDING"]) ?> (Cap <?= e(
     $room["CAPACITY"],
 ) ?>)</option><?php endif;
endforeach; ?></select></label>
<label>Seat No.<input name="seat_no" type="number" min="1" required placeholder="1"></label><button class="btn primary" type="submit" <?= $exams &&
$regs
    ? ""
    : "disabled" ?>>Assign Seat</button></form></section></div>
<section class="panel"><div class="panel-head"><h2>Current Allocations</h2><span class="badge"><?= count(
    $alloc,
) ?> assigned</span></div><div class="table-wrap"><table><thead><tr><th>Allocation</th><th>Exam</th><th>Student</th><th>Room</th><th>Seat</th></tr></thead><tbody><?php
 foreach ($alloc as $assignment): ?><tr><td>#<?= e(
    $assignment["ALLOCATION_ID"],
) ?></td><td><?= e($assignment["EXAM_ID"]) ?></td><td><?= e(
    $assignment["NAME"],
) ?></td><td><?= e($assignment["ROOM_ID"]) ?> / <?= e(
     $assignment["BUILDING"],
 ) ?></td><td><b><?= e($assignment["SEAT_NO"]) ?></b></td></tr><?php endforeach;
 if (
     !$alloc
 ): ?><tr><td colspan="5" class="empty">No allocations yet.</td></tr><?php endif;
 ?></tbody></table></div></section>
</main>
<script>
const allocationExam=document.getElementById('allocation-exam');
const allocationRegistration=document.getElementById('allocation-registration');
function filterRegistrations(){
  const exam=allocationExam.value;
  let first='';
  for(const option of allocationRegistration.options){
    if(!option.value) continue;
    option.hidden=option.dataset.exam!==exam;
    if(!option.hidden&&!first) first=option.value;
  }
  allocationRegistration.value=first;
}
allocationExam.addEventListener('change',filterRegistrations);
filterRegistrations();
</script>
<?php include "end.php"; ?>
