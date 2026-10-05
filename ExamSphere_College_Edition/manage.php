<?php

require_once "config.php";
start_role_session("admin");
require_admin();
$schemas = [
    "DEPARTMENT" => ["DEPT_ID", "DEPT_NAME"],
    "SUBJECT" => ["SUBJECT_ID", "SUBJECT_NAME", "OFFERED_SEMESTER", "DEPT_ID"],
    "STUDENTS" => [
        "STUDENT_ID",
        "FNAME",
        "MNAME",
        "LNAME",
        "CURRENT_SEMESTER",
        "DEPT_ID",
    ],
    "EXAMINATION" => [
        "EXAM_ID",
        "SUBJECT_ID",
        "EXAM_DATE",
        "START_TIME",
        "END_TIME",
    ],
    "ROOMS" => ["ROOM_ID", "BUILDING", "FLOOR", "CAPACITY", "STATUS"],
    "INVIGILATOR" => ["INVIGILATOR_ID", "INVIGILATOR_NAME", "PHONE", "EMAIL"],
    "EXAM_REGISTRATION" => [
        "REGISTR_ID",
        "STUDENT_ID",
        "EXAM_ID",
        "REGISTR_DATE",
    ],
    "ROOM_ALLOCATION" => ["ALLOCATION_ID", "REGISTR_ID", "ROOM_ID", "SEAT_NO"],
    "INVIGILATOR_DUTY" => [
        "DUTY_ID",
        "EXAM_ID",
        "INVIGILATOR_ID",
        "ROOM_ID",
        "ASSIGNED_ON",
    ],
];
$labels = [
    "DEPARTMENT" => "Departments",
    "SUBJECT" => "Subjects",
    "STUDENTS" => "Students",
    "EXAMINATION" => "Examinations",
    "ROOMS" => "Rooms",
    "INVIGILATOR" => "Invigilators",
    "EXAM_REGISTRATION" => "Exam Registrations",
    "ROOM_ALLOCATION" => "Room Allocations",
    "INVIGILATOR_DUTY" => "Invigilator Duties",
];
$table = strtoupper($_GET["table"] ?? "STUDENTS");
if (!isset($schemas[$table])) {
    redirect("dashboard.php");
}
$cols = $schemas[$table];
$pk = $cols[0];
$pdo = db();

function fieldType($c)
{
    if (str_contains($c, "DATE")) {
        return "date";
    }
    if (str_contains($c, "TIME")) {
        return "time";
    }
    if (
        in_array($c, [
            "OFFERED_SEMESTER",
            "CURRENT_SEMESTER",
            "CAPACITY",
            "SEAT_NO",
        ])
    ) {
        return "number";
    }
    return "text";
}

function foreignOptions(PDO $pdo, string $table, string $column): ?array
{
    $queries = [
        "SUBJECT.DEPT_ID" => [
            "SELECT DEPT_ID id,DEPT_NAME label FROM DEPARTMENT ORDER BY DEPT_NAME",
        ],
        "STUDENTS.DEPT_ID" => [
            "SELECT DEPT_ID id,DEPT_NAME label FROM DEPARTMENT ORDER BY DEPT_NAME",
        ],
        "EXAMINATION.SUBJECT_ID" => [
            "SELECT SUBJECT_ID id,CONCAT(SUBJECT_NAME,' (#',SUBJECT_ID,')') label FROM SUBJECT ORDER BY SUBJECT_NAME",
        ],
        "EXAM_REGISTRATION.STUDENT_ID" => [
            "SELECT STUDENT_ID id,CONCAT(FNAME,' ',LNAME,' (#',STUDENT_ID,')') label FROM STUDENTS ORDER BY LNAME,FNAME",
        ],
        "EXAM_REGISTRATION.EXAM_ID" => [
            "SELECT e.EXAM_ID id,CONCAT(s.SUBJECT_NAME,' — ',e.EXAM_DATE,' (#',e.EXAM_ID,')') label FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID ORDER BY e.EXAM_DATE DESC",
        ],
        "ROOM_ALLOCATION.REGISTR_ID" => [
            "SELECT er.REGISTR_ID id,CONCAT(st.FNAME,' ',st.LNAME,' — exam ',er.EXAM_ID,' (#',er.REGISTR_ID,')') label FROM EXAM_REGISTRATION er JOIN STUDENTS st ON st.STUDENT_ID=er.STUDENT_ID ORDER BY er.REGISTR_ID",
        ],
        "ROOM_ALLOCATION.ROOM_ID" => [
            "SELECT ROOM_ID id,CONCAT('Room ',ROOM_ID,' — ',COALESCE(BUILDING,''),' (capacity ',CAPACITY,')') label FROM ROOMS ORDER BY ROOM_ID",
        ],
        "INVIGILATOR_DUTY.EXAM_ID" => [
            "SELECT e.EXAM_ID id,CONCAT(s.SUBJECT_NAME,' — ',e.EXAM_DATE,' (#',e.EXAM_ID,')') label FROM EXAMINATION e JOIN SUBJECT s ON s.SUBJECT_ID=e.SUBJECT_ID ORDER BY e.EXAM_DATE DESC",
        ],
        "INVIGILATOR_DUTY.INVIGILATOR_ID" => [
            "SELECT i.INVIGILATOR_ID id,CONCAT(i.INVIGILATOR_NAME,' (#',i.INVIGILATOR_ID,')') label FROM INVIGILATOR i JOIN users u ON u.invigilator_id=i.INVIGILATOR_ID AND u.role='invigilator' ORDER BY i.INVIGILATOR_NAME",
        ],
        "INVIGILATOR_DUTY.ROOM_ID" => [
            "SELECT ROOM_ID id,CONCAT('Room ',ROOM_ID,' — ',COALESCE(BUILDING,''),' (capacity ',CAPACITY,')') label FROM ROOMS ORDER BY ROOM_ID",
        ],
    ];
    $sql = $queries["$table.$column"] ?? null;
    return $sql ? $pdo->query($sql[0])->fetchAll() : null;
}

function foreignKeyBlockers(PDO $pdo, string $table, string $id): array
{
    $query = $pdo->prepare(
        "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=? AND REFERENCED_COLUMN_NAME IS NOT NULL ORDER BY TABLE_NAME,COLUMN_NAME",
    );
    $query->execute([$table]);
    $blockers = [];
    foreach ($query->fetchAll() as $reference) {
        $childTable = str_replace("`", "``", $reference["TABLE_NAME"]);
        $childColumn = str_replace("`", "``", $reference["COLUMN_NAME"]);
        $count = $pdo->prepare(
            "SELECT COUNT(*) FROM `$childTable` WHERE `$childColumn`=?",
        );
        $count->execute([$id]);
        $rows = (int) $count->fetchColumn();
        if ($rows > 0) {
            $blockers[] =
                $reference["TABLE_NAME"] .
                " (" .
                $reference["COLUMN_NAME"] .
                "): " .
                $rows;
        }
    }
    return $blockers;
}

function tableExists(PDO $pdo, string $table): bool
{
    $query = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?",
    );
    $query->execute([$table]);
    return (bool) $query->fetchColumn();
}

function rowsForIds(PDO $pdo, string $table, string $column, array $ids): array
{
    if (!$ids) {
        return [];
    }
    $marks = implode(",", array_fill(0, count($ids), "?"));
    $query = $pdo->prepare(
        "SELECT * FROM `$table` WHERE `$column` IN ($marks)",
    );
    $query->execute(array_values($ids));
    return $query->fetchAll();
}

function deleteForIds(PDO $pdo, string $table, string $column, array $ids): int
{
    if (!$ids) {
        return 0;
    }
    $marks = implode(",", array_fill(0, count($ids), "?"));
    $query = $pdo->prepare("DELETE FROM `$table` WHERE `$column` IN ($marks)");
    $query->execute(array_values($ids));
    return $query->rowCount();
}

function deleteDepartmentRecords(PDO $pdo, int $departmentId): array
{
    $pdo->beginTransaction();
    try {
        $departmentQuery = $pdo->prepare(
            "SELECT DEPT_NAME FROM DEPARTMENT WHERE DEPT_ID=? FOR UPDATE",
        );
        $departmentQuery->execute([$departmentId]);
        $departmentName = $departmentQuery->fetchColumn();
        if ($departmentName === false) {
            throw new RuntimeException("The department no longer exists.");
        }

        $studentsQuery = $pdo->prepare(
            "SELECT STUDENT_ID,CONCAT_WS(' ',FNAME,MNAME,LNAME) display_name FROM STUDENTS WHERE DEPT_ID=?",
        );
        $studentsQuery->execute([$departmentId]);
        $students = $studentsQuery->fetchAll();
        $studentIds = array_map(
            "intval",
            array_column($students, "STUDENT_ID"),
        );

        $subjectsQuery = $pdo->prepare(
            "SELECT SUBJECT_ID,SUBJECT_NAME FROM SUBJECT WHERE DEPT_ID=?",
        );
        $subjectsQuery->execute([$departmentId]);
        $subjects = $subjectsQuery->fetchAll();
        $subjectIds = array_map(
            "intval",
            array_column($subjects, "SUBJECT_ID"),
        );

        $exams = [];
        if ($subjectIds) {
            $marks = implode(",", array_fill(0, count($subjectIds), "?"));
            $examQuery = $pdo->prepare(
                "SELECT EXAM_ID FROM EXAMINATION WHERE SUBJECT_ID IN ($marks)",
            );
            $examQuery->execute($subjectIds);
            $exams = $examQuery->fetchAll();
        }
        $examIds = array_map("intval", array_column($exams, "EXAM_ID"));

        $registrationWhere = [];
        $registrationParams = [];
        if ($studentIds) {
            $registrationWhere[] =
                "STUDENT_ID IN (" .
                implode(",", array_fill(0, count($studentIds), "?")) .
                ")";
            array_push($registrationParams, ...$studentIds);
        }
        if ($examIds) {
            $registrationWhere[] =
                "EXAM_ID IN (" .
                implode(",", array_fill(0, count($examIds), "?")) .
                ")";
            array_push($registrationParams, ...$examIds);
        }
        $registrations = [];
        if ($registrationWhere) {
            $registrationQuery = $pdo->prepare(
                "SELECT REGISTR_ID,STUDENT_ID,EXAM_ID FROM EXAM_REGISTRATION WHERE " .
                    implode(" OR ", $registrationWhere),
            );
            $registrationQuery->execute($registrationParams);
            $registrations = $registrationQuery->fetchAll();
        }
        $registrationIds = array_map(
            "intval",
            array_column($registrations, "REGISTR_ID"),
        );
        $allocations = rowsForIds(
            $pdo,
            "ROOM_ALLOCATION",
            "REGISTR_ID",
            $registrationIds,
        );
        $allocationIds = array_map(
            "intval",
            array_column($allocations, "ALLOCATION_ID"),
        );

        $attendance = [];
        if (tableExists($pdo, "attendance")) {
            $attendance = rowsForIds(
                $pdo,
                "attendance",
                "allocation_id",
                $allocationIds,
            );
        }
        $duties = rowsForIds($pdo, "INVIGILATOR_DUTY", "EXAM_ID", $examIds);

        $users = [];
        $userIds = [];
        if ($studentIds && tableExists($pdo, "users")) {
            $users = rowsForIds($pdo, "users", "student_id", $studentIds);
            $userIds = array_map("intval", array_column($users, "user_id"));
        }

        $counts = [];
        $lists = [];
        if ($attendance) {
            $attendanceDeleted = deleteForIds(
                $pdo,
                "attendance",
                "allocation_id",
                $allocationIds,
            );
        } else {
            $attendanceDeleted = 0;
        }
        $counts["Attendance"] = $attendanceDeleted;
        $counts["Room allocations"] = deleteForIds(
            $pdo,
            "ROOM_ALLOCATION",
            "REGISTR_ID",
            $registrationIds,
        );
        $counts["Invigilator duties"] = deleteForIds(
            $pdo,
            "INVIGILATOR_DUTY",
            "EXAM_ID",
            $examIds,
        );
        $counts["Exam registrations"] = deleteForIds(
            $pdo,
            "EXAM_REGISTRATION",
            "REGISTR_ID",
            $registrationIds,
        );

        $unlinkedAttendance = 0;
        if ($userIds && tableExists($pdo, "attendance")) {
            $marks = implode(",", array_fill(0, count($userIds), "?"));
            $query = $pdo->prepare(
                "UPDATE attendance SET checked_by=NULL WHERE checked_by IN ($marks)",
            );
            $query->execute($userIds);
            $unlinkedAttendance = $query->rowCount();
        }
        $counts["Student user accounts"] = tableExists($pdo, "users")
            ? deleteForIds($pdo, "users", "student_id", $studentIds)
            : 0;
        $counts["Students"] = deleteForIds(
            $pdo,
            "STUDENTS",
            "STUDENT_ID",
            $studentIds,
        );
        $counts["Examinations"] = deleteForIds(
            $pdo,
            "EXAMINATION",
            "EXAM_ID",
            $examIds,
        );
        $counts["Subjects"] = deleteForIds(
            $pdo,
            "SUBJECT",
            "SUBJECT_ID",
            $subjectIds,
        );
        $departmentDelete = $pdo->prepare(
            "DELETE FROM DEPARTMENT WHERE DEPT_ID=?",
        );
        $departmentDelete->execute([$departmentId]);
        $counts["Departments"] = $departmentDelete->rowCount();

        $lists["Students"] = array_map(
            fn($row) => $row["STUDENT_ID"] . " — " . $row["display_name"],
            $students,
        );
        $lists["Subjects"] = array_map(
            fn($row) => $row["SUBJECT_ID"] . " — " . $row["SUBJECT_NAME"],
            $subjects,
        );
        $lists["Examinations"] = array_map(
            fn($row) => (string) $row["EXAM_ID"],
            $exams,
        );
        $lists["Exam registrations"] = array_map(
            fn($row) => (string) $row["REGISTR_ID"],
            $registrations,
        );
        $lists["Room allocations"] = array_map(
            fn($row) => $row["ALLOCATION_ID"] .
                " — room " .
                $row["ROOM_ID"] .
                " seat " .
                $row["SEAT_NO"],
            $allocations,
        );
        $lists["Attendance"] = array_map(
            fn($row) => $row["attendance_id"] .
                " — allocation " .
                $row["allocation_id"] .
                " (" .
                $row["status"] .
                ")",
            $attendance,
        );
        $lists["Invigilator duties"] = array_map(
            fn($row) => (string) $row["DUTY_ID"],
            $duties,
        );
        $lists["Student user accounts"] = array_map(
            fn($row) => $row["username"] . " — " . $row["full_name"],
            $users,
        );

        $pdo->commit();
        return [
            "department" => $departmentId . " — " . $departmentName,
            "counts" => $counts,
            "lists" => $lists,
            "attendance_unlinked" => $unlinkedAttendance,
        ];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function deleteStudentRecords(PDO $pdo, int $studentId): array
{
    $pdo->beginTransaction();
    try {
        $studentQuery = $pdo->prepare(
            "SELECT CONCAT_WS(' ',FNAME,MNAME,LNAME) FROM STUDENTS WHERE STUDENT_ID=? FOR UPDATE",
        );
        $studentQuery->execute([$studentId]);
        $studentName = $studentQuery->fetchColumn();
        if ($studentName === false) {
            throw new RuntimeException("The student no longer exists.");
        }

        $registrations = rowsForIds($pdo, "EXAM_REGISTRATION", "STUDENT_ID", [
            $studentId,
        ]);
        $registrationIds = array_map(
            "intval",
            array_column($registrations, "REGISTR_ID"),
        );
        $allocations = rowsForIds(
            $pdo,
            "ROOM_ALLOCATION",
            "REGISTR_ID",
            $registrationIds,
        );
        $allocationIds = array_map(
            "intval",
            array_column($allocations, "ALLOCATION_ID"),
        );
        $attendance = tableExists($pdo, "attendance")
            ? rowsForIds($pdo, "attendance", "allocation_id", $allocationIds)
            : [];
        $users = tableExists($pdo, "users")
            ? rowsForIds($pdo, "users", "student_id", [$studentId])
            : [];
        $userIds = array_map("intval", array_column($users, "user_id"));

        $attendanceDeleted = tableExists($pdo, "attendance")
            ? deleteForIds($pdo, "attendance", "allocation_id", $allocationIds)
            : 0;
        $allocationDeleted = deleteForIds(
            $pdo,
            "ROOM_ALLOCATION",
            "REGISTR_ID",
            $registrationIds,
        );
        $registrationDeleted = deleteForIds(
            $pdo,
            "EXAM_REGISTRATION",
            "REGISTR_ID",
            $registrationIds,
        );

        $attendanceUnlinked = 0;
        if ($userIds && tableExists($pdo, "attendance")) {
            $marks = implode(",", array_fill(0, count($userIds), "?"));
            $update = $pdo->prepare(
                "UPDATE attendance SET checked_by=NULL WHERE checked_by IN ($marks)",
            );
            $update->execute($userIds);
            $attendanceUnlinked = $update->rowCount();
        }
        $usersDeleted = tableExists($pdo, "users")
            ? deleteForIds($pdo, "users", "student_id", [$studentId])
            : 0;
        $delete = $pdo->prepare("DELETE FROM STUDENTS WHERE STUDENT_ID=?");
        $delete->execute([$studentId]);
        $pdo->commit();
        return [
            "student" => $studentId . " — " . $studentName,
            "counts" => [
                "Students" => $delete->rowCount(),
                "Student user accounts" => $usersDeleted,
                "Exam registrations" => $registrationDeleted,
                "Room allocations" => $allocationDeleted,
                "Attendance" => $attendanceDeleted,
            ],
            "lists" => [
                "Student user accounts" => array_map(
                    fn($row) => $row["username"] . " — " . $row["full_name"],
                    $users,
                ),
                "Exam registrations" => array_map(
                    fn($row) => (string) $row["REGISTR_ID"],
                    $registrations,
                ),
                "Room allocations" => array_map(
                    fn($row) => $row["ALLOCATION_ID"] .
                        " — room " .
                        $row["ROOM_ID"] .
                        " seat " .
                        $row["SEAT_NO"],
                    $allocations,
                ),
                "Attendance" => array_map(
                    fn($row) => $row["attendance_id"] .
                        " — allocation " .
                        $row["allocation_id"] .
                        " (" .
                        $row["status"] .
                        ")",
                    $attendance,
                ),
            ],
            "attendance_unlinked" => $attendanceUnlinked,
        ];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function deleteInvigilatorRecords(PDO $pdo, int $invigilatorId): array
{
    $pdo->beginTransaction();
    try {
        $nameQuery = $pdo->prepare(
            "SELECT INVIGILATOR_NAME FROM INVIGILATOR WHERE INVIGILATOR_ID=? FOR UPDATE",
        );
        $nameQuery->execute([$invigilatorId]);
        $name = $nameQuery->fetchColumn();
        if ($name === false) {
            throw new RuntimeException("The invigilator no longer exists.");
        }
        $duties = rowsForIds($pdo, "INVIGILATOR_DUTY", "INVIGILATOR_ID", [
            $invigilatorId,
        ]);
        $users = tableExists($pdo, "users")
            ? rowsForIds($pdo, "users", "invigilator_id", [$invigilatorId])
            : [];
        $userIds = array_map("intval", array_column($users, "user_id"));
        $dutiesDeleted = deleteForIds(
            $pdo,
            "INVIGILATOR_DUTY",
            "INVIGILATOR_ID",
            [$invigilatorId],
        );
        $attendanceUnlinked = 0;
        if ($userIds && tableExists($pdo, "attendance")) {
            $marks = implode(",", array_fill(0, count($userIds), "?"));
            $update = $pdo->prepare(
                "UPDATE attendance SET checked_by=NULL WHERE checked_by IN ($marks)",
            );
            $update->execute($userIds);
            $attendanceUnlinked = $update->rowCount();
        }
        $usersDeleted = tableExists($pdo, "users")
            ? deleteForIds($pdo, "users", "invigilator_id", [$invigilatorId])
            : 0;
        $delete = $pdo->prepare(
            "DELETE FROM INVIGILATOR WHERE INVIGILATOR_ID=?",
        );
        $delete->execute([$invigilatorId]);
        $pdo->commit();
        return [
            "invigilator" => $invigilatorId . " — " . $name,
            "counts" => [
                "Invigilators" => $delete->rowCount(),
                "Invigilator user accounts" => $usersDeleted,
                "Invigilator duties" => $dutiesDeleted,
            ],
            "lists" => [
                "Invigilator user accounts" => array_map(
                    fn($row) => $row["username"] . " — " . $row["full_name"],
                    $users,
                ),
                "Invigilator duties" => array_map(
                    fn($row) => "Duty " .
                        $row["DUTY_ID"] .
                        " — exam " .
                        $row["EXAM_ID"] .
                        " / room " .
                        $row["ROOM_ID"],
                    $duties,
                ),
            ],
            "attendance_unlinked" => $attendanceUnlinked,
        ];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function saveProfileLogin(
    PDO $pdo,
    string $table,
    int $profileId,
    string $mode,
): void {
    $profileField = $table === "STUDENTS" ? "student_id" : "invigilator_id";
    $role = $table === "STUDENTS" ? "student" : "invigilator";
    $username = trim((string) ($_POST["account_username"] ?? ""));
    $password = (string) ($_POST["account_password"] ?? "");
    $lookup = $pdo->prepare(
        "SELECT user_id FROM users WHERE `$profileField`=? AND role=? LIMIT 1",
    );
    $lookup->execute([$profileId, $role]);
    $userId = $lookup->fetchColumn();
    if ($mode === "create" && ($username === "" || strlen($password) < 8)) {
        throw new RuntimeException(
            "Provide a login username and a password of at least 8 characters.",
        );
    }
    if ($userId === false) {
        if ($username === "" && $password === "") {
            return;
        }
        if ($username === "" || strlen($password) < 8) {
            throw new RuntimeException(
                "Provide both a login username and a password of at least 8 characters.",
            );
        }
        $name =
            $table === "STUDENTS"
                ? trim(
                    (string) ($_POST["FNAME"] ?? "") .
                        " " .
                        (string) ($_POST["MNAME"] ?? "") .
                        " " .
                        (string) ($_POST["LNAME"] ?? ""),
                )
                : trim((string) ($_POST["INVIGILATOR_NAME"] ?? ""));
        $email =
            $table === "INVIGILATOR"
                ? trim((string) ($_POST["EMAIL"] ?? ""))
                : null;
        $insert = $pdo->prepare(
            "INSERT INTO users (username,password_hash,full_name,email,role,`$profileField`) VALUES (?,?,?,?,?,?)",
        );
        $insert->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $name,
            $email ?: null,
            $role,
            $profileId,
        ]);
        return;
    }
    if ($username === "" && $password === "") {
        return;
    }
    $sets = [];
    $values = [];
    if ($username !== "") {
        $sets[] = "username=?";
        $values[] = $username;
    }
    if ($password !== "") {
        if (strlen($password) < 8) {
            throw new RuntimeException(
                "Password must be at least 8 characters.",
            );
        }
        $sets[] = "password_hash=?";
        $values[] = password_hash($password, PASSWORD_DEFAULT);
    }
    if ($table === "INVIGILATOR") {
        $sets[] = "full_name=?";
        $values[] = trim((string) ($_POST["INVIGILATOR_NAME"] ?? ""));
        $sets[] = "email=?";
        $values[] = trim((string) ($_POST["EMAIL"] ?? "")) ?: null;
    }
    if ($sets) {
        $values[] = $userId;
        $pdo->prepare(
            "UPDATE users SET " . implode(",", $sets) . " WHERE user_id=?",
        )->execute($values);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    try {
        require_csrf();
        if ($table === "EXAM_REGISTRATION") {
            throw new RuntimeException(
                "Students register for exams from their own student portal.",
            );
        }
        if ($action === "delete") {
            if ($table === "DEPARTMENT") {
                $report = deleteDepartmentRecords(
                    $pdo,
                    (int) ($_POST["id"] ?? 0),
                );
                $_SESSION["department_deletion_report"] = $report;
                flash(
                    "success",
                    "Department and related records deleted. Review the deletion report below.",
                );
            } elseif ($table === "STUDENTS") {
                $report = deleteStudentRecords($pdo, (int) ($_POST["id"] ?? 0));
                $_SESSION["student_deletion_report"] = $report;
                flash(
                    "success",
                    "Student and related records deleted. Review the deletion report below.",
                );
            } elseif ($table === "INVIGILATOR") {
                $report = deleteInvigilatorRecords(
                    $pdo,
                    (int) ($_POST["id"] ?? 0),
                );
                $_SESSION["invigilator_deletion_report"] = $report;
                flash(
                    "success",
                    "Invigilator and related records deleted. Review the deletion report below.",
                );
            } else {
                $st = $pdo->prepare("DELETE FROM `$table` WHERE `$pk`=?");
                $st->execute([$_POST["id"]]);
                flash(
                    $st->rowCount() ? "success" : "danger",
                    $st->rowCount()
                        ? "Record deleted successfully."
                        : "No matching record was found.",
                );
            }
        } elseif ($action === "save") {
            $mode = $_POST["mode"] ?? "create";
            $pdo->beginTransaction();
            $data = [];
            foreach ($cols as $c) {
                $value = trim((string) ($_POST[$c] ?? ""));
                $data[$c] = $value === "" && $c === "MNAME" ? null : $value;
            }
            if ($mode === "edit") {
                $sets = implode(
                    ",",
                    array_map(fn($c) => "`$c`=?", array_keys($data)),
                );
                $vals = array_values($data);
                $vals[] = $_POST["original_id"];
                $pdo->prepare(
                    "UPDATE `$table` SET $sets WHERE `$pk`=?",
                )->execute($vals);
                flash("success", "Record updated successfully.");
                $profileId = (int) $_POST["original_id"];
            } else {
                if ($table === "STUDENTS") {
                    unset($data["STUDENT_ID"]);
                }
                $fields = implode(
                    ",",
                    array_map(fn($c) => "`$c`", array_keys($data)),
                );
                $marks = implode(",", array_fill(0, count($data), "?"));
                $pdo->prepare(
                    "INSERT INTO `$table` ($fields) VALUES ($marks)",
                )->execute(array_values($data));
                flash("success", "Record added successfully.");
                $profileId =
                    $table === "STUDENTS"
                        ? (int) $pdo->lastInsertId()
                        : (int) ($data[$pk] ?? 0);
            }
            if (in_array($table, ["STUDENTS", "INVIGILATOR"], true)) {
                saveProfileLogin($pdo, $table, $profileId, $mode);
            }
            $pdo->commit();
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (
            $action === "delete" &&
            $ex instanceof PDOException &&
            $ex->getCode() === "23000"
        ) {
            $blockers = foreignKeyBlockers(
                $pdo,
                $table,
                (string) ($_POST["id"] ?? ""),
            );
            flash(
                "danger",
                $blockers
                    ? "Cannot delete this record yet. Remove these dependent records first: " .
                        implode("; ", $blockers) .
                        "."
                    : "Cannot delete this record because the database reports related records or another integrity constraint.",
            );
        } else {
            flash("danger", "Database error: " . $ex->getMessage());
        }
    }
    redirect("manage.php?table=" . urlencode($table));
}
$q = trim($_GET["q"] ?? "");
$where = "";
$params = [];
if ($q !== "") {
    $parts = array_map(fn($c) => "`$c` LIKE ?", $cols);
    $where = "WHERE " . implode(" OR ", $parts);
    foreach ($cols as $c) {
        $params[] = "%$q%";
    }
}
$stmt = $pdo->prepare(
    "SELECT * FROM `$table` $where ORDER BY `$pk` DESC LIMIT 200",
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$edit = null;
if (isset($_GET["edit"])) {
    $s = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk`=?");
    $s->execute([$_GET["edit"]]);
    $edit = $s->fetch();
}
$profileUsername = null;
if ($edit && in_array($table, ["STUDENTS", "INVIGILATOR"], true)) {
    $profileField = $table === "STUDENTS" ? "student_id" : "invigilator_id";
    $profileRole = $table === "STUDENTS" ? "student" : "invigilator";
    $profileQuery = $pdo->prepare(
        "SELECT username FROM users WHERE `$profileField`=? AND role=? LIMIT 1",
    );
    $profileQuery->execute([$edit[$pk], $profileRole]);
    $profileUsername = $profileQuery->fetchColumn() ?: null;
}
include "partials.php";
?>
<main class="page"><div class="page-title"><div><span class="eyebrow">DATABASE MANAGEMENT</span><h1><?= e(
    $labels[$table],
) ?></h1><p><?= count(
    $rows,
) ?> records shown • Search, create, edit and delete.</p></div></div>
<?php if ($table === "DEPARTMENT"): ?>
<section class="panel"><b>Department deletion</b><p class="hint">Deleting a department also deletes its students and student accounts, subjects, examinations, registrations for those students or exams, room allocations, linked attendance, and invigilator duties for its exams. Rooms, invigilators, and unrelated exams are kept. Student registrations in other departments’ exams are also deleted because the student is removed.</p></section>
<?php if (!empty($_SESSION["department_deletion_report"])):

    $deletionReport = $_SESSION["department_deletion_report"];
    unset($_SESSION["department_deletion_report"]);
    ?>
<section class="panel deletion-report"><div class="panel-head"><div><h2>Deleted records</h2><p>Department: <?= e(
    $deletionReport["department"],
) ?></p></div></div>
<div class="delete-counts"><?php foreach (
    $deletionReport["counts"]
    as $label => $count
):
    if ($count > 0): ?><div><b><?= e($count) ?></b><span><?= e(
    $label,
) ?></span></div><?php endif;
endforeach; ?></div>
<?php foreach ($deletionReport["lists"] as $label => $items):
    if ($items): ?><details class="deleted-group"><summary><?= e(
    $label,
) ?> — <?= count($items) ?> record(s)</summary><ul><?php foreach (
     $items
     as $item
 ): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></details><?php endif;
endforeach; ?>
<?php if ($deletionReport["attendance_unlinked"] > 0): ?><p class="hint"><?= e(
    $deletionReport["attendance_unlinked"],
) ?> remaining attendance record(s) had the deleted student account removed from “checked by”.</p><?php endif; ?></section>
<?php
endif;endif; ?>
<?php if (
    $table === "STUDENTS" &&
    !$edit
): ?><section class="panel"><b>Automatic student IDs</b><p class="hint">Student IDs are assigned by the database when you add a student. Enter the student's profile and login details below; you do not need to provide an ID.</p></section><?php endif; ?>
<?php if (
    $table === "STUDENTS" &&
    !empty($_SESSION["student_deletion_report"])
):

    $deletionReport = $_SESSION["student_deletion_report"];
    unset($_SESSION["student_deletion_report"]);
    ?>
<section class="panel deletion-report"><div class="panel-head"><div><h2>Deleted student records</h2><p>Student: <?= e(
    $deletionReport["student"],
) ?></p></div></div><div class="delete-counts"><?php foreach (
    $deletionReport["counts"]
    as $label => $count
):
    if ($count > 0): ?><div><b><?= e($count) ?></b><span><?= e(
    $label,
) ?></span></div><?php endif;
endforeach; ?></div><?php
foreach ($deletionReport["lists"] as $label => $items):
    if ($items): ?><details class="deleted-group"><summary><?= e(
    $label,
) ?> — <?= count($items) ?> record(s)</summary><ul><?php foreach (
     $items
     as $item
 ): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></details><?php endif;
endforeach;
if ($deletionReport["attendance_unlinked"] > 0): ?><p class="hint"><?= e(
    $deletionReport["attendance_unlinked"],
) ?> remaining attendance record(s) had the deleted student account removed from “checked by”.</p><?php endif;
?></section>
<?php
endif; ?>
<?php if (
    $table === "INVIGILATOR" &&
    !empty($_SESSION["invigilator_deletion_report"])
):

    $deletionReport = $_SESSION["invigilator_deletion_report"];
    unset($_SESSION["invigilator_deletion_report"]);
    ?>
<section class="panel deletion-report"><div class="panel-head"><div><h2>Deleted invigilator records</h2><p>Invigilator: <?= e(
    $deletionReport["invigilator"],
) ?></p></div></div><div class="delete-counts"><?php foreach (
    $deletionReport["counts"]
    as $label => $count
):
    if ($count > 0): ?><div><b><?= e($count) ?></b><span><?= e(
    $label,
) ?></span></div><?php endif;
endforeach; ?></div><?php
foreach ($deletionReport["lists"] as $label => $items):
    if ($items): ?><details class="deleted-group"><summary><?= e(
    $label,
) ?> — <?= count($items) ?> record(s)</summary><ul><?php foreach (
     $items
     as $item
 ): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></details><?php endif;
endforeach;
if ($deletionReport["attendance_unlinked"] > 0): ?><p class="hint"><?= e(
    $deletionReport["attendance_unlinked"],
) ?> remaining attendance record(s) had the deleted user account removed from “checked by”.</p><?php endif;
?></section>
<?php
endif; ?>
<?php if (
    $table === "EXAM_REGISTRATION"
): ?><section class="panel"><b>Student-owned registration</b><p class="hint">Students register for eligible published exams from their own portal. Administrators can view registrations here, but cannot create or delete them directly.</p></section><?php endif; ?>
<?php if (
    in_array($table, ["STUDENTS", "INVIGILATOR"], true)
): ?><section class="panel"><b>Login account</b><p class="hint"><?= $table ===
"STUDENTS"
    ? "Student"
    : "Invigilator" ?> records need a username and an initial password (8+ characters) to sign in. When editing, leave these fields blank to keep the existing login unchanged.</p></section><?php endif; ?>
<?php if ($table !== "EXAM_REGISTRATION"): ?>
<div class="panel form-panel"><div class="panel-head"><h2><?= $edit
    ? "Edit record"
    : "Add new record" ?></h2><?php if (
    $edit
): ?><a href="manage.php?table=<?= $table ?>">Cancel edit</a><?php endif; ?></div>
<form id="profile-form" method="post" class="form-grid"><?php foreach (
    $cols
    as $c
):

    $isEdit = $edit !== null;
    $autoStudentId = $table === "STUDENTS" && $c === $pk && !$isEdit;
    if ($autoStudentId) {
        continue;
    }
    $disabled = $c === $pk && !$isEdit;
    $options = foreignOptions($pdo, $table, $c);
    ?>
<label><?=
e(ucwords(strtolower(str_replace("_", " ", $c))))
?><?php
if ($options !== null): ?><select name="<?= e(
    $c,
) ?>" required><option value="">Choose…</option><?php foreach (
    $options
    as $option
): ?><option value="<?= e($option["id"]) ?>" <?= (string) ($edit[$c] ?? "") ===
(string) $option["id"]
    ? "selected"
    : "" ?>><?= e($option["label"]) ?></option><?php endforeach; ?></select>
<?php elseif ($c === "STATUS"): ?><select name="<?= e(
    $c,
) ?>" required><?php foreach (
    ["AVAILABLE", "UNAVAILABLE"]
    as $status
): ?><option value="<?= $status ?>" <?= ($edit[$c] ?? "AVAILABLE") === $status
    ? "selected"
    : "" ?>><?= e(
    ucfirst(strtolower($status)),
) ?></option><?php endforeach; ?></select>
<?php else: ?><input name="<?= e($c) ?>" type="<?= fieldType(
    $c,
) ?>" value="<?= e($edit[$c] ?? "") ?>" <?= $disabled
    ? "required"
    : "" ?> <?= $c === $pk && $isEdit ? "readonly" : "" ?> <?= $c !== "MNAME" &&
 !$disabled
     ? "required"
     : "" ?> <?= $c === "CAPACITY" ? 'min="1"' : "" ?>>
<?php endif;
?></label><?php
endforeach; ?><input type="hidden" name="csrf_token" value="<?= e(
    csrf_token(),
) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="mode" value="<?= $edit
    ? "edit"
    : "create" ?>"><?php if (
    $edit
): ?><input type="hidden" name="original_id" value="<?= e(
    $edit[$pk],
) ?>"><?php endif; ?><button class="btn primary" type="submit"><?= $edit
    ? "Update"
    : "Add record" ?></button></form></div>
<?php if (in_array($table, ["STUDENTS", "INVIGILATOR"], true)):
    $accountRequired = $edit
        ? ""
        : "required"; ?><section class="panel form-panel"><h2>Portal login credentials</h2><div class="form-grid"><label>Username<input form="profile-form" name="account_username" autocomplete="off" value="<?= e(
    $profileUsername ?? "",
) ?>" <?= $accountRequired ?>></label><label><?= $edit
    ? "New password"
    : "Initial password" ?><input form="profile-form" name="account_password" type="password" autocomplete="new-password" minlength="8" <?= $accountRequired ?>></label></div></section><?php
endif; ?>
<?php else: ?><section class="panel"><h2>Exam registrations</h2><p class="hint">Read-only view of registrations submitted from student accounts.</p><?php endif; ?>
<div class="panel"><div class="panel-head"><h2>Records</h2><form class="search"><input name="q" value="<?= e(
    $q,
) ?>" placeholder="Search all fields..."><input type="hidden" name="table" value="<?= $table ?>"><button class="btn ghost">Search</button></form></div>
<div class="table-wrap"><table><thead><tr><?php foreach (
    $cols
    as $c
): ?><th><?= e(
    $c,
) ?></th><?php endforeach; ?><th>Actions</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="<?= count($cols) +
    1 ?>" class="empty">No records found.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><?php foreach ($cols as $c): ?><td><?= e(
    $r[$c],
) ?></td><?php endforeach; ?><td class="actions"><?php if (
    $table === "EXAM_REGISTRATION"
): ?><span class="hint">Student managed</span><?php else: ?><a class="link" href="?table=<?= $table ?>&edit=<?= urlencode(
    $r[$pk],
) ?>">Edit</a><form method="post" onsubmit="return confirm('<?= e(
    $table === "DEPARTMENT"
        ? "Delete this department and all the related records listed in the department deletion notice?"
        : ($table === "EXAMINATION"
            ? "Delete this examination? Its registrations, room allocations, attendance entries, and invigilator duties will also be deleted. Students, subjects, rooms, and invigilators will be kept."
            : ($table === "STUDENTS"
                ? "Delete this student and their linked account, registrations, room allocations, and attendance?"
                : ($table === "INVIGILATOR"
                    ? "Delete this invigilator, their login, and assigned duties?"
                    : "Delete this record?"))),
) ?>')"><input type="hidden" name="csrf_token" value="<?= e(
    csrf_token(),
) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e(
    $r[$pk],
) ?>"><button class="danger-link">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></main><?php include "end.php";
