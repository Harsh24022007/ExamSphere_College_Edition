<?php
require_once "config.php";
start_role_session("student");
if (!empty($_SESSION["user_role"])) {
    redirect(login_destination($_SESSION["user_role"]));
}

$pdo = db();
$departments = $pdo
    ->query("SELECT DEPT_ID,DEPT_NAME FROM DEPARTMENT ORDER BY DEPT_NAME")
    ->fetchAll();
$error = "";
$form = [
    "fname" => "",
    "mname" => "",
    "lname" => "",
    "department" => "",
    "semester" => "",
    "username" => "",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? ""));
    }
    try {
        require_csrf();
        $semester = filter_var($form["semester"], FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 1, "max_range" => 12],
        ]);
        $departmentId = filter_var($form["department"], FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 1],
        ]);
        $password = (string) ($_POST["password"] ?? "");
        if ($semester === false) {
            throw new RuntimeException("Choose a semester from 1 to 12.");
        }
        if ($departmentId === false) {
            throw new RuntimeException("Choose your department.");
        }
        if ($form["fname"] === "" || $form["lname"] === "") {
            throw new RuntimeException("Enter your first and last name.");
        }
        if (
            strlen($form["fname"]) > 50 ||
            strlen($form["mname"]) > 50 ||
            strlen($form["lname"]) > 50
        ) {
            throw new RuntimeException("Names must be 50 characters or fewer.");
        }
        if ($form["username"] === "" || strlen($form["username"]) > 60) {
            throw new RuntimeException(
                "Username must contain 1 to 60 characters.",
            );
        }
        if (strlen($password) < 8) {
            throw new RuntimeException(
                "Password must be at least 8 characters.",
            );
        }
        $fullName = trim(
            implode(
                " ",
                array_filter(
                    [$form["fname"], $form["mname"], $form["lname"]],
                    static fn($part) => $part !== "",
                ),
            ),
        );
        if (strlen($fullName) > 120) {
            throw new RuntimeException(
                "Your full name must be 120 characters or fewer.",
            );
        }
        $deptCheck = $pdo->prepare(
            "SELECT DEPT_ID FROM DEPARTMENT WHERE DEPT_ID=?",
        );
        $deptCheck->execute([$departmentId]);
        if (!$deptCheck->fetchColumn()) {
            throw new RuntimeException("Choose an available department.");
        }

        $pdo->beginTransaction();
        $usernameCheck = $pdo->prepare(
            "SELECT user_id FROM users WHERE username=?",
        );
        $usernameCheck->execute([$form["username"]]);
        if ($usernameCheck->fetchColumn()) {
            throw new RuntimeException(
                "That username is already in use. Choose another one.",
            );
        }

        $insertStudent = $pdo->prepare(
            "INSERT INTO STUDENTS (FNAME,MNAME,LNAME,CURRENT_SEMESTER,DEPT_ID) VALUES (?,?,?,?,?)",
        );
        $insertStudent->execute([
            $form["fname"],
            $form["mname"] !== "" ? $form["mname"] : null,
            $form["lname"],
            $semester,
            $departmentId,
        ]);
        $studentId = (int) $pdo->lastInsertId();
        $insertUser = $pdo->prepare(
            "INSERT INTO users (username,password_hash,full_name,role,student_id) VALUES (?,?,?,'student',?)",
        );
        $insertUser->execute([
            $form["username"],
            password_hash($password, PASSWORD_DEFAULT),
            $fullName,
            $studentId,
        ]);
        $pdo->commit();
        flash(
            "success",
            "Your student account is ready. Sign in to see eligible exams and your timetable.",
        );
        redirect("student_login.php");
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $ex->getMessage();
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (($ex->errorInfo[1] ?? null) === 1062) {
            $error =
                "That username is already registered. Choose a different username.";
        } elseif (($ex->errorInfo[1] ?? null) === 1364) {
            error_log(
                "ExamSphere student registration requires the STUDENT_ID auto-increment migration.",
            );
            $error =
                "Student account setup needs a database update. Please contact the administrator.";
        } else {
            error_log(
                "ExamSphere student registration database error: " .
                    $ex->getMessage(),
            );
            $error =
                "We could not create your account because of a database error. Please try again or contact the administrator.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Student Registration | ExamSphere</title>
 <link rel="stylesheet" href="assets/style.css">
 <link rel="stylesheet" href="assets/portal.css">
</head>
<body class="login-bg">
 <main class="login-card register-card">
  <a class="brand center" href="index.php"><span class="logo">ES</span><div><b>ExamSphere</b><small>Student Exam Portal</small></div></a>
  <div class="login-title"><span class="eyebrow">NEW STUDENT ACCOUNT</span><h1>Create your account</h1><p>Your student ID is assigned automatically. Your department and semester determine which exams you can register for.</p></div>
  <?php if ($error): ?><div class="alert danger"><?= e(
    $error,
) ?></div><?php endif; ?>
  <?php if (
      !$departments
  ): ?><div class="alert danger">Student registration is not available because no departments have been set up. Contact the administrator.</div><?php else: ?>
  <form method="post" class="stack register-form">
   <div class="register-fields">
    <label>Department<select name="department" required><option value="">Choose department</option><?php foreach (
        $departments
        as $department
    ): ?><option value="<?= e(
    $department["DEPT_ID"],
) ?>" <?= (string) $department["DEPT_ID"] === $form["department"]
    ? "selected"
    : "" ?>><?= e(
    $department["DEPT_NAME"],
) ?></option><?php endforeach; ?></select></label>
    <label>First name<input name="fname" maxlength="50" autocomplete="given-name" value="<?= e(
        $form["fname"],
    ) ?>" required></label>
    <label>Middle name <small>(optional)</small><input name="mname" maxlength="50" autocomplete="additional-name" value="<?= e(
        $form["mname"],
    ) ?>"></label>
    <label>Last name<input name="lname" maxlength="50" autocomplete="family-name" value="<?= e(
        $form["lname"],
    ) ?>" required></label>
    <label>Current semester<select name="semester" required><option value="">Choose semester</option><?php for (
        $semester = 1;
        $semester <= 12;
        $semester++
    ): ?><option value="<?= $semester ?>" <?= (string) $semester ===
$form["semester"]
    ? "selected"
    : "" ?>>Semester <?= $semester ?></option><?php endfor; ?></select></label>
    <label>Username<input name="username" maxlength="60" autocomplete="username" value="<?= e(
        $form["username"],
    ) ?>" required></label>
    <label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
   </div>
   <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
   <button class="btn primary">Create student account →</button>
  </form>
  <?php endif; ?>
  <p class="auth-switch">Already registered? <a href="student_login.php">Student sign in</a></p>
  <p class="auth-back"><a href="index.php">← Back to ExamSphere portals</a></p>
 </main>
</body>
</html>
