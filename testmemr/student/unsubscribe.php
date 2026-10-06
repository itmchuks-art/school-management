<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $confirm = trim($_POST['confirm'] ?? '');

    if ($confirm !== 'UNSUBSCRIBE') {
        $error = 'Type UNSUBSCRIBE to confirm.';
    } else {
        /* Clear subscription fields */
        $pdo->prepare('UPDATE users
            SET subscription_plan   = NULL,
                subscription_end    = NULL,
                subscribed_grades   = NULL,
                subscribed_subjects = NULL
            WHERE id = ?')
            ->execute([(int)$user['id']]);

        /* Mark all active/pending payments as cancelled (optional) */
        try {
            $pdo->prepare("UPDATE payments SET status = 'cancelled'
                           WHERE user_id = ? AND status IN ('pending','approved')")
                ->execute([(int)$user['id']]);
        } catch (Exception $e) { /* ignore if status column doesn't allow it */ }

        flash('✅ Your subscription has been cancelled.');
        redirect(SITE_URL . '/student/dashboard.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="max-width:520px">

  <div class="text-center mb-4">
    <h2 class="fw-bold" style="color:#CE1126">🚫 Cancel Subscription</h2>
    <p class="text-muted">You will lose access to all locked subjects immediately.</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!is_subscribed($user)): ?>
    <div class="alert alert-secondary">
      You don't have an active subscription. Nothing to cancel.
    </div>
    <div class="text-center">
      <a href="<?= SITE_URL ?>/student/dashboard.php" class="btn btn-outline-secondary">
        ← Back to Dashboard
      </a>
    </div>
  <?php else: ?>

    <div class="card p-4 shadow-sm" style="border-radius:16px;border-left:5px solid #CE1126">

      <p class="mb-2"><b>Current plan:</b>
        <?= e(PLANS[$user['subscription_plan']]['label'] ?? $user['subscription_plan']) ?>
      </p>
      <p class="mb-2"><b>Days remaining:</b> <?= sub_days_left($user) ?> day(s)</p>
      <p class="mb-3"><b>Ends on:</b> <?= e(fmt_date($user['subscription_end'])) ?></p>

      <div class="alert alert-warning small mb-3">
        ⚠️ <b>This cannot be undone.</b> You will lose access immediately.
        There are <b>no refunds</b> for unused days.
      </div>

      <form method="post">
        <label class="form-label fw-bold">
          Type <code>UNSUBSCRIBE</code> to confirm:
        </label>
        <input type="text" name="confirm" class="form-control mb-3"
               placeholder="UNSUBSCRIBE" required autofocus>

        <button type="submit" class="btn btn-danger w-100 py-3 fw-bold"
                onclick="return confirm('Are you 100% sure? This cannot be undone.');"
                style="border-radius:12px">
          🚫 Cancel My Subscription
        </button>

        <a href="<?= SITE_URL ?>/student/dashboard.php"
           class="btn btn-outline-secondary w-100 mt-2">Keep My Subscription</a>
      </form>
    </div>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>