<?php
require_once "config.php";
start_role_session("admin");
require_admin();
$pdo = db();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        require_csrf();
        $requestId = filter_var(
            $_POST["request_id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]],
        );
        $action = $_POST["action"] ?? "";
        if ($requestId === false || !in_array($action, ["approve", "reject"], true)) {
            throw new RuntimeException("Choose a valid pending request.");
        }

        $pdo->beginTransaction();
        $request = $pdo->prepare(
            "SELECT REQUEST_ID,FULL_NAME,EMAIL,PHONE FROM INVIGILATOR_REQUEST WHERE REQUEST_ID=? AND STATUS='pending' FOR UPDATE",
        );
        $request->execute([$requestId]);
        $application = $request->fetch();
        if (!$application) {
            throw new RuntimeException("That request is no longer pending.");
        }

        if ($action === "approve") {
            $createProfile = $pdo->prepare(
                "INSERT INTO INVIGILATOR (INVIGILATOR_NAME,PHONE,EMAIL) VALUES (?,?,?)",
            );
            $createProfile->execute([
                $application["FULL_NAME"],
                $application["PHONE"],
                $application["EMAIL"],
            ]);
            $update = $pdo->prepare(
                "UPDATE INVIGILATOR_REQUEST SET STATUS='approved',INVIGILATOR_ID=?,REVIEWED_AT=CURRENT_TIMESTAMP WHERE REQUEST_ID=? AND STATUS='pending'",
            );
            $update->execute([(int) $pdo->lastInsertId(), $requestId]);
            flash("success", "Request approved. The applicant can now claim the account with their request number and one-time code.");
        } else {
            $update = $pdo->prepare(
                "UPDATE INVIGILATOR_REQUEST SET STATUS='rejected',REVIEWED_AT=CURRENT_TIMESTAMP WHERE REQUEST_ID=? AND STATUS='pending'",
            );
            $update->execute([$requestId]);
            flash("success", "Invigilator request rejected.");
        }
        if ($update->rowCount() !== 1) {
            throw new RuntimeException("The request could not be updated.");
        }
        $pdo->commit();
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("danger", $ex->getMessage());
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ExamSphere invigilator approval error: " . $ex->getMessage());
        flash("danger", "The request could not be processed. Check the database migration and try again.");
    }
    redirect("invigilator_requests.php");
}

$pending = $pdo->query(
    "SELECT REQUEST_ID,FULL_NAME,EMAIL,PHONE,REQUESTED_AT FROM INVIGILATOR_REQUEST WHERE STATUS='pending' ORDER BY REQUESTED_AT,REQUEST_ID",
)->fetchAll();
$history = $pdo->query(
    "SELECT q.REQUEST_ID,q.FULL_NAME,q.EMAIL,q.STATUS,q.REQUESTED_AT,q.REVIEWED_AT,i.INVIGILATOR_ID FROM INVIGILATOR_REQUEST q LEFT JOIN INVIGILATOR i ON i.INVIGILATOR_ID=q.INVIGILATOR_ID WHERE q.STATUS<>'pending' ORDER BY COALESCE(q.REVIEWED_AT,q.REQUESTED_AT) DESC,q.REQUEST_ID DESC LIMIT 100",
)->fetchAll();
include "partials.php";
?>
<main class="content">
 <div class="content-title"><div><span class="eyebrow">INVIGILATOR ONBOARDING</span><h1>Invigilator Requests</h1><p>Review applications before creating staff profiles and enabling account claims.</p></div></div>
 <section class="panel"><div class="panel-head"><div><h2>Pending requests</h2><p><?= count($pending) ?> awaiting review. Verify the applicant's identity and contact details before approval.</p></div></div>
 <?php if (!$pending): ?><div class="empty">There are no pending invigilator requests.</div><?php else: ?>
 <div class="table-wrap"><table><thead><tr><th>Request</th><th>Name</th><th>Email</th><th>Phone</th><th>Submitted</th><th>Decision</th></tr></thead><tbody>
 <?php foreach ($pending as $application): ?><tr><td>#<?= e($application["REQUEST_ID"]) ?></td><td><?= e($application["FULL_NAME"]) ?></td><td><?= e($application["EMAIL"]) ?></td><td><?= e($application["PHONE"] ?: "—") ?></td><td><?= e($application["REQUESTED_AT"]) ?></td><td><div class="actions"><form method="post" onsubmit="return confirm('Approve this applicant as an invigilator?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= e($application["REQUEST_ID"]) ?>"><button class="btn primary" name="action" value="approve">Approve</button></form><form method="post" onsubmit="return confirm('Reject this invigilator request?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= e($application["REQUEST_ID"]) ?>"><button class="danger-link" name="action" value="reject">Reject</button></form></div></td></tr><?php endforeach; ?>
 </tbody></table></div><?php endif; ?></section>
 <section class="panel"><div class="panel-head"><div><h2>Recent decisions</h2><p>Latest 100 approved, claimed, or rejected requests.</p></div></div>
 <?php if (!$history): ?><div class="empty">No requests have been reviewed yet.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Request</th><th>Name</th><th>Email</th><th>Status</th><th>Invigilator ID</th><th>Reviewed</th></tr></thead><tbody><?php foreach ($history as $application): ?><tr><td>#<?= e($application["REQUEST_ID"]) ?></td><td><?= e($application["FULL_NAME"]) ?></td><td><?= e($application["EMAIL"]) ?></td><td><?= e(ucfirst($application["STATUS"])) ?></td><td><?= e($application["INVIGILATOR_ID"] ?? "—") ?></td><td><?= e($application["REVIEWED_AT"] ?? "—") ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</main>
<?php include "end.php"; ?>
