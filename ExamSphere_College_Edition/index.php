<?php
require_once "config.php";
try {
    $pdo = db();
    $dbOK = true;
    $students = (int) $pdo
        ->query("SELECT COUNT(*) FROM STUDENTS")
        ->fetchColumn();
    $rooms = (int) $pdo->query("SELECT COUNT(*) FROM ROOMS")->fetchColumn();
    $exams = (int) $pdo
        ->query("SELECT COUNT(*) FROM EXAMINATION")
        ->fetchColumn();
} catch (Throwable $x) {
    $dbOK = false;
    $students = $rooms = $exams = 0;
}
?>
<!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>ExamSphere | College Examination Management</title>
 <link rel="stylesheet" href="assets/style.css">
 <link rel="stylesheet" href="assets/portal.css">
</head>
<body class="landing">
<header class="landing-nav">
 <a class="brand" href="index.php"><span class="logo">ES</span><div><b>ExamSphere</b><small>College Examination Management</small></div></a>
 <nav><a href="#features">Features</a><a href="#about">About</a><a class="nav-login" href="login.php?role=admin">Admin Login →</a></nav>
</header>
<section class="landing-hero">
 <div class="landing-copy">
  <span class="pill">SMART • SECURE • CONNECTED</span>
  <h1>Examinations,<br><em>organized for everyone.</em></h1>
  <p>Students register for eligible exams and follow their personal timetable. Invigilators see their assigned duties. Administrators manage the complete examination process.</p>
  <div class="actions"><a class="btn primary" href="student_login.php">Student Portal →</a><a class="btn ghost" href="#portals">Choose your portal</a></div>
  <div class="creator">Database created by <b>Harshith S H</b> <span>×</span> <b>K Sudhanva Datta</b></div>
 </div>
 <div class="landing-visual">
  <div class="float-card top-card"><span>DATABASE</span><b><?= $dbOK
      ? "ONLINE"
      : "OFFLINE" ?></b><i></i></div>
  <div class="orbit-card"><div class="orbit-ring"></div><div class="orbit-core">ES</div><div class="orbit-item o1">Students <b><?= $students ?></b></div><div class="orbit-item o2">Rooms <b><?= $rooms ?></b></div><div class="orbit-item o3">Exams <b><?= $exams ?></b></div></div>
 </div>
</section>
<section id="portals" class="portal-entry-grid" aria-label="Choose a portal">
 <article class="portal-entry-card">
  <span class="eyebrow">FOR LEARNERS</span><h2>Student portal</h2>
  <p>Create a student account or sign in to find eligible exams, register, and review scheduled and completed exams.</p>
  <div class="portal-entry-actions"><a class="btn primary" href="student_login.php">Student sign in</a><a href="student_register.php">New student →</a></div>
 </article>
 <article class="portal-entry-card">
  <span class="eyebrow">FOR EXAM STAFF</span><h2>Invigilator portal</h2>
  <p>Sign in to see your assigned exam and room schedule, including your upcoming and completed duties.</p>
  <div class="portal-entry-actions"><a class="btn primary" href="invigilator_login.php">Invigilator sign in</a></div>
 </article>
 <article class="portal-entry-card">
  <span class="eyebrow">FOR EXAM ADMIN</span><h2>Administrator portal</h2>
  <p>Manage departments, subjects, students, examinations, room allocations, and invigilator assignments.</p>
  <div class="portal-entry-actions"><a class="btn primary" href="login.php?role=admin">Administrator sign in</a></div>
 </article>
</section>
<section id="features" class="landing-features">
 <span class="eyebrow">THE PLATFORM</span><h2>Everything your examination cell needs.</h2>
 <div class="landing-feature-grid">
  <div><b>01</b><h3>Visual Seating</h3><p>See every room as a seat map and manage capacity visually.</p></div>
  <div><b>02</b><h3>Department Analytics</h3><p>Monitor student and registration distribution across departments.</p></div>
  <div><b>03</b><h3>Role-based Portals</h3><p>Separate secure workspaces for administrators, students and invigilators.</p></div>
  <div><b>04</b><h3>Reports & Exports</h3><p>Print plans and export allocation data to Excel or CSV.</p></div>
 </div>
</section>
<footer id="about">ExamSphere • Exam Room Allocation System • <b>Harshith S H & K Sudhanva Datta</b></footer>
</body>
</html>
