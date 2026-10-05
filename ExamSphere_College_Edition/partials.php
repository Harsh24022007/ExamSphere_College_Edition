<?php
require_once __DIR__ . "/config.php";
start_role_session("admin");
require_admin();
function navActive($needle)
{
    $uri = $_SERVER["REQUEST_URI"] ?? "";
    return str_contains($uri, $needle) ? "active" : "";
}
?>
<!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>ExamSphere | Academic Administration</title>
 <link rel="stylesheet" href="assets/style.css">
 <link rel="stylesheet" href="assets/workflow.css">
</head>
<body>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
 <a href="dashboard.php" class="side-brand"><span class="logo">ES</span><div><b>ExamSphere</b><small>College ERP</small></div></a>
 <div class="side-label">MAIN</div>
 <nav class="side-nav">
  <a href="dashboard.php" class="<?= navActive(
      "dashboard.php",
  ) ?>"><span>⌂</span> Dashboard</a>
  <a href="analytics.php" class="<?= navActive(
      "analytics.php",
  ) ?>"><span>◒</span> Department Analytics</a>
  <a href="allocation.php" class="<?= navActive(
      "allocation.php",
  ) ?>"><span>▦</span> Seat Allocation</a>
  <a href="seating.php" class="<?= navActive(
      "seating.php",
  ) ?>"><span>⊞</span> Visual Seating</a>
  <a href="reports.php" class="<?= navActive(
      "reports.php",
  ) ?>"><span>▤</span> Reports & Exports</a>
 </nav>
 <div class="side-label">MASTER DATA</div>
 <nav class="side-nav">
  <a href="manage.php?table=DEPARTMENT">Departments</a><a href="manage.php?table=SUBJECT">Subjects</a><a href="manage.php?table=STUDENTS">Students</a><a href="manage.php?table=EXAMINATION">Examinations</a><a href="manage.php?table=ROOMS">Rooms</a><a href="manage.php?table=INVIGILATOR">Invigilators</a><a href="manage.php?table=EXAM_REGISTRATION">Registrations</a><a href="manage.php?table=INVIGILATOR_DUTY">Invigilator Duties</a>
 </nav>
 <div class="side-footer"><div class="admin-mini"><span><?= e(
     strtoupper(substr(admin_name(), 0, 1)),
 ) ?></span><div><b><?= e(
    admin_name(),
) ?></b><small>Administrator</small></div></div><a href="logout.php?role=admin" class="logout">↪ Sign out</a></div>
</aside>
<div class="app-main"><header class="appbar"><button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div><span class="eyebrow">EXAMINATION MANAGEMENT</span><strong>Academic Administration</strong></div><div class="appbar-right"><span class="live-dot"></span> MySQL Connected <a href="index.php" class="home-mini">Home</a></div><a href="logout.php?role=admin" class="admin-signout-top">Sign out</a></header>
<?php if ($f = getFlash()): ?><div class="toast <?= $f["type"] ?>"><?= e(
    $f["message"],
) ?></div><?php endif; ?>
