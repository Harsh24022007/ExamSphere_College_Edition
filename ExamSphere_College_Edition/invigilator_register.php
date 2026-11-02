<?php
require_once "config.php";
start_role_session("invigilator");
if (!empty($_SESSION["user_role"])) {
    redirect(login_destination($_SESSION["user_role"]));
}

$pdo = db();
$error = "";
$confirmation = $_SESSION["invigilator_request_confirmation"] ?? null;
unset($_SESSION["invigilator_request_confirmation"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        $action = $_POST["action"] ?? "";
        if ($action === "request") {
            $fullName = trim((string) ($_POST["full_name"] ?? ""));
            $email = trim((string) ($_POST["email"] ?? ""));
            $phone = trim((string) ($_POST["phone"] ?? ""));
            if ($fullName === "" || strlen($fullName) > 120) {
                throw new RuntimeException("Enter your full name (up to 120 characters).");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
                throw new RuntimeException("Enter a valid email address (up to 160 characters).");
            }
            if (strlen($phone) > 20) {
                throw new RuntimeException("Phone numbers must be 20 characters or fewer.");
            }

            $claimCode = bin2hex(random_bytes(6));
            $insert = $pdo->prepare(
                "INSERT INTO INVIGILATOR_REQUEST (FULL_NAME,EMAIL,PHONE,CLAIM_CODE_HASH) VALUES (?,?,?,?)",
            );
            $insert->execute([
                $fullName,
                $email,
                $phone !== "" ? $phone : null,
                password_hash($claimCode, PASSWORD_DEFAULT),
            ]);
            $_SESSION["invigilator_request_confirmation"] = [
                "request_id" => (int) $pdo->lastInsertId(),
                "claim_code" => $claimCode,
            ];
            redirect("invigilator_register.php");
        }

        if ($action !== "claim") {
            throw new RuntimeException("Choose a valid request action.");
        }
        $requestId = filter_var(
            $_POST["request_id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]],
        );
        $claimCode = trim((string) ($_POST["claim_code"] ?? ""));
        $username = trim((string) ($_POST["username"] ?? ""));
        $password = (string) ($_POST["password"] ?? "");
        if ($requestId === false || $claimCode === "" || $username === "" || strlen($username) > 60) {
            throw new RuntimeException("Enter the approved request number, claim code, and a username.");
        }
        if (strlen($password) < 8) {
            throw new RuntimeException("Password must be at least 8 characters.");
        }

        $pdo->beginTransaction();
        $request = $pdo->prepare(
            "SELECT REQUEST_ID,FULL_NAME,EMAIL,CLAIM_CODE_HASH,STATUS,INVIGILATOR_ID FROM INVIGILATOR_REQUEST WHERE REQUEST_ID=? FOR UPDATE",
        );
        $request->execute([$requestId]);
        $application = $request->fetch();
        if (
            !$application
            || $application["STATUS"] !== "approved"
            || !password_verify($claimCode, $application["CLAIM_CODE_HASH"])
            || empty($application["INVIGILATOR_ID"])
        ) {
            throw new RuntimeException("The request is not approved or the request number and claim code do not match.");
        }

        $createAccount = $pdo->prepare(
            "INSERT INTO users (username,password_hash,full_name,email,role,invigilator_id) VALUES (?,?,?,?, 'invigilator',?)",
        );
        $createAccount->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $application["FULL_NAME"],
            $application["EMAIL"],
            $application["INVIGILATOR_ID"],
        ]);
        $claim = $pdo->prepare(
            "UPDATE INVIGILATOR_REQUEST SET STATUS='claimed' WHERE REQUEST_ID=? AND STATUS='approved'",
        );
        $claim->execute([$requestId]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException("This request has already been claimed.");
        }
        $pdo->commit();
        flash("success", "Your invigilator account is ready. Sign in with your new username and password.");
        redirect("invigilator_login.php");
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
            $error = "That username is already in use. Choose another one.";
        } else {
            error_log("ExamSphere invigilator registration error: " . $ex->getMessage());
            $error = "The request could not be completed. Check the database migration or contact the administrator.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Invigilator Request | ExamSphere</title>
 <link rel="stylesheet" href="assets/style.css">
 <link rel="stylesheet" href="assets/portal.css">
</head>
<body class="login-bg">
 <main class="login-card register-card">
  <a class="brand center" href="index.php"><span class="logo">ES</span><div><b>ExamSphere</b><small>Invigilator Portal</small></div></a>
  <div class="login-title"><span class="eyebrow">INVIGILATOR ACCESS</span><h1>Request an account</h1><p>Submit your details for administrator approval. After approval, use your request number and claim code to choose a username and password.</p></div>
  <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
  <?php if ($confirmation): ?><section class="request-code"><h2>Request submitted</h2><p>Save these details. The administrator must approve your request before you can create your account.</p><div>Request number <b><?= e($confirmation["request_id"]) ?></b></div><div>One-time claim code <code><?= e($confirmation["claim_code"]) ?></code></div><small>The claim code is shown only once. Keep it private.</small></section><?php endif; ?>
  <form method="post" class="stack register-form">
   <h2>1. Submit your request</h2>
   <div class="register-fields">
    <label>Full name<input name="full_name" maxlength="120" autocomplete="name" required></label>
    <label>Email<input type="email" name="email" maxlength="160" autocomplete="email" required></label>
    <label>Phone <small>(optional)</small><input name="phone" maxlength="20" autocomplete="tel"></label>
   </div>
   <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
   <input type="hidden" name="action" value="request">
   <button class="btn primary">Submit for approval →</button>
  </form>
  <form method="post" class="stack register-form claim-form">
   <h2>2. Create your account after approval</h2>
   <div class="register-fields">
    <label>Request number<input type="number" name="request_id" min="1" required></label>
    <label>One-time claim code<input name="claim_code" autocomplete="one-time-code" required></label>
    <label>Choose username<input name="username" maxlength="60" autocomplete="username" required></label>
    <label>Choose password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
   </div>
   <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
   <input type="hidden" name="action" value="claim">
   <button class="btn ghost">Create account</button>
  </form>
  <p class="auth-switch">Already have an account? <a href="invigilator_login.php">Invigilator sign in</a></p>
  <p class="auth-back"><a href="index.php">← Back to ExamSphere portals</a></p>
 </main>
</body>
</html>
