<?php

define('DB_HOST', getenv('EXAMSPHERE_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('EXAMSPHERE_DB_PORT') ?: '3306');
define('DB_NAME', getenv('EXAMSPHERE_DB_NAME') ?: 'exam_room_allocation_system');
define('DB_USER', getenv('EXAMSPHERE_DB_USER') ?: 'root');
define('DB_PASS', getenv('EXAMSPHERE_DB_PASS') ?: '');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }
    return $pdo;
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $u): never
{
    header("Location: $u");
    exit();
}

function role_session_name(string $role): string
{
    if (!in_array($role, ['admin', 'student', 'invigilator'], true)) {
        throw new InvalidArgumentException('Unsupported portal session role.');
    }
    return 'EXAMSPHERE_' . strtoupper($role) . '_SID';
}

function start_role_session(string $role): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== role_session_name($role)) {
            throw new LogicException('A different portal session is already active.');
        }
        return;
    }
    session_name(role_session_name($role));
    session_start();
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $x = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $x;
}

function require_login(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        redirect('login.php');
    }
}

function admin_name(): string
{
    return $_SESSION['user_name'] ?? 'Administrator';
}

function require_role(string $role): void
{
    require_login();
    if ($_SESSION['user_role'] !== $role) {
        $portal = [
            'admin' => 'dashboard.php',
            'student' => 'student_portal.php',
            'invigilator' => 'invigilator_portal.php',
        ];
        redirect($portal[$_SESSION['user_role']] ?? 'login.php');
    }
}

function require_admin(): void
{
    require_role('admin');
}

function login_destination(string $role): string
{
    return match ($role) {
        'admin' => 'dashboard.php',
        'student' => 'student_portal.php',
        'invigilator' => 'invigilator_portal.php',
        default => 'login.php',
    };
}

function verify_user_password(string $password, string $storedHash): bool
{
    if (password_verify($password, $storedHash)) {
        return true;
    }
    $parts = explode('$', $storedHash);
    if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256' || !ctype_digit($parts[1])) {
        return false;
    }
    $iterations = (int) $parts[1];
    if ($iterations < 1 || $iterations > 2000000) {
        return false;
    }
    $actual = base64_encode(hash_pbkdf2('sha256', $password, $parts[2], $iterations, 32, true));
    return hash_equals($parts[3], $actual);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
}
