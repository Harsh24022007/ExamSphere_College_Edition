<?php
require_once "config.php";
$requestedRole = in_array(
    $_GET["role"] ?? "",
    ["admin", "student", "invigilator"],
    true,
)
    ? $_GET["role"]
    : "";
start_role_session($requestedRole !== "" ? $requestedRole : "admin");
if (
    !empty($_SESSION["user_role"]) &&
    ($requestedRole === "" || $requestedRole === $_SESSION["user_role"])
) {
    redirect(login_destination($_SESSION["user_role"]));
}
$error = "";
$notice = getFlash();
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $pdo = db();
        $username = trim((string) ($_POST["username"] ?? ""));
        $password = (string) ($_POST["password"] ?? "");
        $admin = $pdo->prepare(
            "SELECT ADMIN_ID,FULL_NAME,PASSWORD_HASH FROM ADMIN_USERS WHERE USERNAME=? LIMIT 1",
        );
        $admin->execute([$username]);
        $account = $admin->fetch();
        $role = "admin";
        $userId = null;
        if (
            !$account ||
            !password_verify($password, $account["PASSWORD_HASH"])
        ) {
            $lookup = $pdo->prepare(
                "SELECT user_id,username,password_hash,full_name,role,student_id,invigilator_id FROM users WHERE username=? LIMIT 1",
            );
            $lookup->execute([$username]);
            $account = $lookup->fetch();
            if (
                !$account ||
                !verify_user_password($password, $account["password_hash"])
            ) {
                $account = null;
            } elseif (
                !in_array(
                    $account["role"],
                    ["admin", "student", "invigilator"],
                    true,
                )
            ) {
                $account = null;
            } else {
                $role = $account["role"];
                $userId = (int) $account["user_id"];
                if ($role === "student" && empty($account["student_id"])) {
                    $account = null;
                }
                if (
                    $role === "invigilator" &&
                    empty($account["invigilator_id"])
                ) {
                    $account = null;
                }
                if (
                    $account &&
                    password_needs_rehash(
                        $account["password_hash"],
                        PASSWORD_DEFAULT,
                    )
                ) {
                    $rehash = $pdo->prepare(
                        "UPDATE users SET password_hash=? WHERE user_id=?",
                    );
                    $rehash->execute([
                        password_hash($password, PASSWORD_DEFAULT),
                        $userId,
                    ]);
                }
            }
        }
        if ($account && $requestedRole !== "" && $requestedRole !== $role) {
            $account = null;
        }
        if ($account) {
            if ($requestedRole === "") {
                session_write_close();
                start_role_session($role);
            }
            session_regenerate_id(true);
            $_SESSION["user_role"] = $role;
            $_SESSION["user_name"] =
                $role === "admin"
                    ? $account["FULL_NAME"] ?? $account["full_name"]
                    : $account["full_name"];
            if ($userId !== null) {
                $_SESSION["user_id"] = $userId;
            } else {
                $_SESSION["user_id"] = (int) $account["ADMIN_ID"];
                $_SESSION["admin_id"] = (int) $account["ADMIN_ID"];
            }
            if ($role === "student") {
                $_SESSION["student_id"] = (int) $account["student_id"];
            }
            if ($role === "invigilator") {
                $_SESSION["invigilator_id"] = (int) $account["invigilator_id"];
            }
            redirect(login_destination($role));
        }
        $error =
            "Invalid username or password, or the account has no linked student/invigilator profile.";
    } catch (PDOException $ex) {
        error_log("ExamSphere login database error: " . $ex->getMessage());
        if (($ex->errorInfo[1] ?? null) === 1146) {
            $error =
                "The login tables are missing. Run auth.sql and ensure the users table exists, then try again.";
        } else {
            $error =
                "Cannot connect to the configured database (" .
                e(DB_HOST) .
                ":" .
                e(DB_PORT) .
                "/" .
                e(DB_NAME) .
                "). Check the database settings in config.php.";
        }
    }
}
$pageTitle = match ($requestedRole) {
    "student" => "Student sign in",
    "invigilator" => "Invigilator sign in",
    "admin" => "Administrator sign in",
    default => "Portal sign in",
};
$pageDescription = match ($requestedRole) {
    "student"
        => "Sign in with the username and password you chose during registration. Your student ID is assigned automatically.",
    "invigilator" => "Sign in to view your assigned examination duties.",
    "admin"
        => "Sign in to manage examinations, rooms, students and invigilator assignments.",
    default
        => "Sign in with your administrator, student, or invigilator account.",
};
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | ExamSphere</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/portal.css"></head>
<body class="login-bg"><div class="login-card"><a class="brand center" href="index.php"><span class="logo">ES</span><div><b>ExamSphere</b><small>College Examination Management</small></div></a>
<div class="login-title"><span class="eyebrow"><?= e(
    strtoupper($pageTitle),
) ?></span><h1>Welcome back</h1><p><?= e($pageDescription) ?></p></div>
<?php if ($notice): ?><div class="alert success"><?= e(
    $notice["message"],
) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= e(
    $error,
) ?></div><?php endif; ?>
<form method="post" class="stack"><label>Username<input name="username" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="btn primary">Sign in →</button></form>
<?php if (
    $requestedRole === "student"
): ?><p class="auth-switch">Your student ID is generated during sign-up; sign in with your username and password.</p><p class="auth-switch">New student? <a href="student_register.php">Create a student account</a></p><?php elseif (
    $requestedRole === "invigilator"
): ?><p class="auth-switch">Invigilator accounts are created by your administrator.</p><?php else: ?><p class="auth-switch"><a href="student_login.php">Student sign in</a><span>·</span><a href="invigilator_login.php">Invigilator sign in</a></p><?php endif; ?>
<?php if (
    $requestedRole === "" ||
    $requestedRole === "admin"
): ?><div class="demo">Administrator demo: <b>admin</b> / <b>admin123</b></div><?php endif; ?>
<p class="auth-back"><a href="index.php">← Back to ExamSphere portals</a></p></div></body></html>