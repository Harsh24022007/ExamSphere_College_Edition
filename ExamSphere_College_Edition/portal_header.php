<?php
require_once __DIR__ . "/config.php";
start_role_session($_SESSION["user_role"] ?? "student");
require_login();
$portalRole = $_SESSION["user_role"];
$portalLinks =
    $portalRole === "student"
        ? [
            ["student_portal.php#scheduled", "Scheduled"],
            ["student_portal.php#completed", "Completed"],
            ["student_portal.php#available", "Register"],
        ]
        : [
            ["invigilator_portal.php#scheduled", "Scheduled Duties"],
            ["invigilator_portal.php#completed", "Completed Duties"],
        ];
?>
<!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>ExamSphere | <?= e(ucfirst($portalRole)) ?> Portal</title>
 <link rel="stylesheet" href="assets/style.css">
 <link rel="stylesheet" href="assets/portal.css">
</head>
<body class="portal-page">
<header class="portal-header">
 <a class="brand" href="<?= e(
     login_destination($portalRole),
 ) ?>"><span class="logo">ES</span><div><b>ExamSphere</b><small><?= e(
    ucfirst($portalRole),
) ?> Portal</small></div></a>
 <nav><?php foreach ($portalLinks as [$href, $label]): ?><a href="<?= e(
    $href,
) ?>"><?= e($label) ?></a><?php endforeach; ?><span class="portal-user"><?= e(
    admin_name(),
) ?></span><a class="portal-logout" href="logout.php?role=<?= e(
    $portalRole,
) ?>">Sign out</a></nav>
</header>
<?php if ($f = getFlash()): ?><div class="portal-toast <?= e(
    $f["type"],
) ?>"><?= e($f["message"]) ?></div><?php endif; ?>
<main class="portal-main">
