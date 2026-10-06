<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

$st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([(int)$user['id']]);
$user = $st->fetch();

/* If they DO have an active subscription, send them back */
if (is_subscribed($user)) {
    redirect(SITE_URL . '/student/dashboard.php');
}

/* ---------- Check for a pending payment ---------- */
$pending_payment = null;
try {
    $ph = $pdo->prepare("
        SELECT * FROM payments
        WHERE user_id = ?
          AND status = 'pending'
        ORDER BY id DESC
        LIMIT 1
    ");
    $ph->execute([(int)$user['id']]);
    $pending_payment = $ph->fetch() ?: null;
} catch (Exception $e) {}

/* ---------- Last approved sub info ---------- */
$active_grades = [];
if (!empty($user['subscribed_grades'])) {
    $active_grades = array_map('trim', explode(',', $user['subscribed_grades']));
    $active_grades = array_values(array_filter($active_grades));
}

$last_plan  = $user['subscription_plan'] ?? '';
$last_end   = $user['subscription_end'] ?? null;
$last_label = isset(PLANS[$last_plan]) ? PLANS[$last_plan]['label'] : $last_plan;

/* Plan info for the pending payment */
$pending_label = '';
if ($pending_payment && isset(PLANS[$pending_payment['plan']])) {
    $pending_label = PLANS[$pending_payment['plan']]['label'];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#4c0519 0%,#7f1d1d 40%,#050b33 100%);min-height:100vh;color:#fff;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#d97706,#b45309);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(255,245,245,.94));border-radius:28px;padding:40px 32px;box-shadow:0 40px 80px -20px rgba(0,0,0,.6),0 20px 40px -10px rgba(206,17,38,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;text-align:center;position:relative;overflow:hidden;}

  .lock-icon{width:140px;height:140px;margin:0 auto 20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:4rem;box-shadow:0 20px 40px rgba(0,0,0,.35),inset 0 4px 8px rgba(255,255,255,.2);animation:pulse 2s infinite;}
  .lock-icon.expired{background:linear-gradient(135deg,#CE1126 0%,#a30d1e 50%,#d97706 100%);}
  .lock-icon.pending{background:linear-gradient(135deg,#d97706 0%,#b45309 50%,#92400e 100%);}
  @keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.05);}}

  h2.big{font-weight:900;font-size:2rem;letter-spacing:-.5px;margin-bottom:10px;}
  h2.big.expired{color:#CE1126;}
  h2.big.pending{color:#d97706;}
  .sub-text{color:#6b7280;font-size:1rem;margin-bottom:24px;}

  .info-box{border-radius:16px;padding:18px;text-align:left;margin-bottom:20px;box-shadow:0 8px 16px rgba(0,0,0,.15);}
  .info-box.expired{background:linear-gradient(135deg,#fff1f2,#fee2e2);border-left:5px solid #CE1126;}
  .info-box.expired .small{color:#991b1b;}
  .info-box.expired strong{color:#7f1d1d;}
  .info-box.pending{background:linear-gradient(135deg,#fffbeb,#fef3c7);border-left:5px solid #d97706;}
  .info-box.pending .small{color:#78350f;}
  .info-box.pending strong{color:#78350f;}

  .step-card{background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:14px;padding:16px;margin-bottom:12px;text-align:left;display:flex;gap:14px;align-items:flex-start;box-shadow:0 4px 10px rgba(12,30,127,.08);}
  .step-num{width:34px;height:34px;flex-shrink:0;border-radius:50%;background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.9rem;box-shadow:0 4px 8px rgba(12,30,127,.3);}
  .step-title{font-weight:800;color:#0C1E7F;font-size:.9rem;}
  .step-desc{font-size:.78rem;color:#4b5563;margin-top:2px;}

  .btn-3d{display:block;padding:18px 32px;border-radius:16px;font-weight:900;border:none;color:#fff;transition:transform .1s,box-shadow .1s;font-size:1.05rem;cursor:pointer;text-decoration:none;letter-spacing:.5px;width:100%;max-width:360px;margin:6px auto;text-align:center;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 10px 0 #1a3a12,0 18px 36px rgba(58,119,40,.5);}
  .btn-3d.green:hover{transform:translateY(-3px);box-shadow:0 13px 0 #1a3a12,0 22px 44px rgba(58,119,40,.6);}
  .btn-3d.green:active{transform:translateY(7px);box-shadow:0 3px 0 #1a3a12;}
  .btn-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);box-shadow:0 10px 0 #050b33,0 18px 36px rgba(12,30,127,.5);}
  .btn-3d.blue:hover{transform:translateY(-3px);}
  .btn-3d.blue:active{transform:translateY(7px);box-shadow:0 3px 0 #050b33;}
  .btn-3d.gold{background:linear-gradient(135deg,#d97706,#b45309);box-shadow:0 10px 0 #5c2b04,0 18px 36px rgba(217,119,6,.5);}
  .btn-3d.gold:hover{transform:translateY(-3px);}
  .btn-3d.gold:active{transform:translateY(7px);box-shadow:0 3px 0 #5c2b04;}

  .pill{display:inline-block;padding:6px 16px;border-radius:20px;font-weight:800;font-size:.75rem;}
  .pill.pending{background:linear-gradient(135deg,#d97706,#b45309);color:#fff;}
  .pill.red{background:linear-gradient(135deg,#CE1126,#a30d1e);color:#fff;}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-5" style="max-width:640px;position:relative;z-index:2;">
  <div class="glass">

    <?php if ($pending_payment): ?>

      <!-- ============================================================
           PENDING PAYMENT — awaiting admin approval
           ============================================================ -->
      <div class="lock-icon pending">⏳</div>

      <h2 class="big pending">Payment Pending Approval</h2>
      <p class="sub-text">
        Hi <b><?= e($user['name']) ?></b>, your payment was received.<br>
        The admin will approve it shortly — usually within minutes.
      </p>

      <div class="info-box pending">
        <div class="small fw-bold mb-2">📋 YOUR PAYMENT</div>
        <div class="d-flex justify-content-between mb-1">
          <span class="small">Payment #</span>
          <span class="small fw-bold">#<?= (int)$pending_payment['id'] ?></span>
        </div>
        <?php if ($pending_label): ?>
          <div class="d-flex justify-content-between mb-1">
            <span class="small">Plan</span>
            <span class="small fw-bold"><?= e($pending_label) ?></span>
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-1">
          <span class="small">Amount</span>
          <span class="small fw-bold">D<?= (int)$pending_payment['amount'] ?></span>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span class="small">Method</span>
          <span class="small fw-bold"><?= e($pending_payment['method']) ?></span>
        </div>
        <?php if (!empty($pending_payment['subscribed_grades'])): ?>
          <div class="d-flex justify-content-between mb-1">
            <span class="small">Grade(s)</span>
            <span class="small fw-bold"><?= e($pending_payment['subscribed_grades']) ?></span>
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between">
          <span class="small">Submitted</span>
          <span class="small fw-bold"><?= e(fmt_date($pending_payment['created_at'])) ?></span>
        </div>
        <hr class="my-2">
        <div class="text-center">
          <span class="pill pending">⏳ Awaiting admin approval</span>
        </div>
      </div>

      <div class="step-card">
        <div class="step-num">1</div>
        <div>
          <div class="step-title">Admin reviews your payment</div>
          <div class="step-desc">They check the reference number and screenshot against the payment received.</div>
        </div>
      </div>
      <div class="step-card">
        <div class="step-num">2</div>
        <div>
          <div class="step-title">Your account gets activated</div>
          <div class="step-desc">Full access returns instantly once approved.</div>
        </div>
      </div>
      <div class="step-card">
        <div class="step-num">3</div>
        <div>
          <div class="step-title">Refresh this page to check status</div>
          <div class="step-desc">Or try Write Test again — you'll get in automatically once approved.</div>
        </div>
      </div>

      <a href="<?= SITE_URL ?>/student/no_subscription.php" class="btn-3d gold">
        🔄 Refresh Status
      </a>
      <a href="<?= SITE_URL ?>/student/dashboard.php" class="btn-3d blue">
        ← Try Dashboard
      </a>

    <?php elseif ($last_end): ?>

      <!-- ============================================================
           EXPIRED — was subscribed before
           ============================================================ -->
      <div class="lock-icon expired">🔒</div>

      <h2 class="big expired">Subscription Expired</h2>
      <p class="sub-text">
        Hi <b><?= e($user['name']) ?></b>, your subscription has ended.<br>
        Subscribe again to keep practising.
      </p>

      <div class="info-box expired">
        <div class="small fw-bold mb-1">⏰ EXPIRED ON</div>
        <div style="font-weight:800;font-size:1.05rem;color:#7f1d1d;"><?= e(fmt_date($last_end)) ?></div>
        <?php if ($last_label): ?>
          <div class="small mt-2"><strong>Last plan:</strong> <?= e($last_label) ?></div>
        <?php endif; ?>
        <?php if (!empty($active_grades)): ?>
          <div class="small mt-1"><strong>Covered grades:</strong> <?= e(implode(' · ', $active_grades)) ?></div>
        <?php endif; ?>
      </div>

      <div class="mt-4 mb-4">
        <div class="step-card">
          <div class="step-num">1</div>
          <div>
            <div class="step-title">Choose a plan</div>
            <div class="step-desc">D25 for 1 day · D75 for 1 week · D225 for 1 month</div>
          </div>
        </div>
        <div class="step-card">
          <div class="step-num">2</div>
          <div>
            <div class="step-title">Pay with Wave, Afrimoney or QMoney</div>
            <div class="step-desc">Send payment, upload screenshot — admin approves in minutes.</div>
          </div>
        </div>
        <div class="step-card">
          <div class="step-num">3</div>
          <div>
            <div class="step-title">Start writing tests again</div>
            <div class="step-desc">Full access returns instantly once approved.</div>
          </div>
        </div>
      </div>

      <a href="<?= SITE_URL ?>/student/subscription.php" class="btn-3d green">
        💳 Subscribe Now
      </a>

    <?php else: ?>

      <!-- ============================================================
           NEVER SUBSCRIBED
           ============================================================ -->
      <div class="lock-icon expired">🔒</div>

      <h2 class="big expired">No Active Subscription</h2>
      <p class="sub-text">
        Hi <b><?= e($user['name']) ?></b>, you don't have an active subscription yet.<br>
        Choose a plan to unlock your tests.
      </p>

      <div class="info-box expired">
        <div class="small fw-bold mb-1">ℹ️ NO SUBSCRIPTION</div>
        <div class="small">
          Subscribe to unlock all the practice questions for your grade.
        </div>
      </div>

      <div class="mt-4 mb-4">
        <div class="step-card">
          <div class="step-num">1</div>
          <div>
            <div class="step-title">Choose a plan</div>
            <div class="step-desc">D25 for 1 day · D75 for 1 week · D225 for 1 month</div>
          </div>
        </div>
        <div class="step-card">
          <div class="step-num">2</div>
          <div>
            <div class="step-title">Pay with Wave, Afrimoney or QMoney</div>
            <div class="step-desc">Send payment, upload screenshot — admin approves in minutes.</div>
          </div>
        </div>
        <div class="step-card">
          <div class="step-num">3</div>
          <div>
            <div class="step-title">Start writing tests</div>
            <div class="step-desc">Full access unlocks instantly once approved.</div>
          </div>
        </div>
      </div>

      <a href="<?= SITE_URL ?>/student/subscription.php" class="btn-3d green">
        💳 Subscribe Now
      </a>

    <?php endif; ?>

    <a href="<?= SITE_URL ?>/student/logout.php" class="btn-3d blue">🚪 Logout</a>

    <div style="text-align:center;color:#6b7280;font-size:.75rem;margin-top:20px;">
      Made with ❤️ for Gambian students 🇬🇲
    </div>

  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>