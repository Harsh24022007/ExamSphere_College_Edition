<?php
require_once "config.php";
$role = $_GET["role"] ?? "admin";
if (!in_array($role, ["admin", "student", "invigilator"], true)) {
    $role = "admin";
}
start_role_session($role);
$destination = match ($role) {
    "admin" => "login.php?role=admin",
    "student" => "student_login.php",
    "invigilator" => "invigilator_login.php",
};
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(role_session_name($role), "", [
        "expires" => time() - 42000,
        "path" => $params["path"],
        "domain" => $params["domain"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"],
        "samesite" => $params["samesite"] ?? "Lax",
    ]);
}
session_destroy();
header("Location: " . $destination);
exit();
?>
