<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

$st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([(int)$user['id']]);
$user = $st->fetch();

if (empty($user['grade']) && empty($user['subscribed_grades'])) {
    redirect(SITE_URL . '/student/choose_grade.php');
}

$active_grades = [];
if (!empty($user['subscribed_grades'])) {
    $active_grades = array_map('trim', explode(',', $user['subscribed_grades']));
    $active_grades = array_values(array_filter($active_grades));
}
if (($user['grade'] ?? '') === 'ALL') $active_grades = CLASS_LEVELS;

$active = is_subscribed($user);

define('MODEMPAY_API_KEY', '');

$merchant_phones = [
    'Wave'      => '+220866879863',
    'Afrimoney' => '+2207705902',
    'QMoney'    => '+2207705902',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_key = $_POST['plan']       ?? '';
    $grade    = trim($_POST['grade'] ?? '');
    $method   = trim($_POST['method'] ?? '');
    $ref      = trim($_POST['reference'] ?? '');
    $plan     = PLANS[$plan_key] ?? null;

    $errors = [];
    if (!$plan) $errors[] = 'Choose a valid plan.';
    if ($plan && $plan['type'] === 'single' && !in_array($grade, CLASS_LEVELS, true)) {
        $errors[] = 'Choose the grade.';
    }
    if ($method === '') $errors[] = 'Choose a payment method.';
    if ($ref === '')    $errors[] = 'Enter the reference.';

    if ($errors) {
        foreach ($errors as $e) flash($e, 'danger');
        redirect(SITE_URL . '/student/subscription.php');
    }

    $grades_csv = ($plan['type'] === 'all') ? implode(',', CLASS_LEVELS) : $grade;

    $screenshot = null;
    if (!empty($_FILES['screenshot']['tmp_name'])) {
        $dir = __DIR__ . '/../uploads';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','pdf'], true)) {
            $name = 'pay_' . $user['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $dir . '/' . $name)) {
                $screenshot = 'uploads/' . $name;
            }
        }
    }

    $pdo->prepare('INSERT INTO payments (user_id, plan, amount, method, reference, screenshot, status, subscribed_grades)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([(int)$user['id'], $plan_key, $plan['amount'], $method, $ref, $screenshot, 'pending', $grades_csv]);

    flash('✅ Payment submitted. Admin will approve it soon.');
    redirect(SITE_URL . '/student/subscription.php');
}

$ph = $pdo->prepare('SELECT * FROM payments WHERE user_id=? ORDER BY id DESC LIMIT 20');
$ph->execute([(int)$user['id']]);
$payments = $ph->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;color:#fff;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:28px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .section-title{font-weight:900;color:#0C1E7F;font-size:1.05rem;margin-bottom:16px;display:flex;align-items:center;gap:10px;}

  .grade-pick{display:flex;align-items:center;gap:12px;padding:16px;border:3px solid #e5e7eb;border-radius:16px;background:#fff;cursor:pointer;transition:all .25s;position:relative;box-shadow:0 8px 16px rgba(12,30,127,.08);}
  .grade-pick:hover{border-color:#0C1E7F;transform:translateY(-4px);box-shadow:0 16px 32px rgba(12,30,127,.2);}
  .grade-pick input{display:none;}
  .grade-pick.selected{border-color:#0C1E7F;background:linear-gradient(135deg,#eff6ff,#dbeafe);box-shadow:0 12px 24px rgba(12,30,127,.25);}
  .grade-pick.owned{border-color:#3A7728;background:linear-gradient(135deg,#f0fdf4,#dcfce7);}
  .grade-pick.owned::after{content:"✓ Owned";position:absolute;top:-10px;right:14px;background:linear-gradient(135deg,#3A7728,#16a34a);color:#fff;font-size:.62rem;font-weight:800;padding:4px 10px;border-radius:20px;box-shadow:0 4px 8px rgba(58,119,40,.3);}
  .gp-icon{font-size:1.7rem;width:50px;height:50px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f1f5f9,#e0e7ff);border-radius:12px;box-shadow:0 4px 8px rgba(0,0,0,.08);}
  .gp-title{font-weight:900;color:#0f172a;font-size:.92rem;}

  .plan-btn{background:#fff;border:3px solid #e5e7eb;border-radius:18px;padding:22px 14px;cursor:pointer;transition:all .25s;position:relative;text-align:center;display:block;width:100%;box-shadow:0 8px 16px rgba(12,30,127,.08);}
  .plan-btn:hover{border-color:#0C1E7F;transform:translateY(-5px);box-shadow:0 20px 40px rgba(12,30,127,.2);}
  .plan-btn input{display:none;}
  .plan-btn.selected{border-color:#0C1E7F;background:linear-gradient(135deg,#eff6ff,#dbeafe);box-shadow:0 16px 32px rgba(12,30,127,.3);transform:translateY(-3px);}
  .plan-btn .p-price{font-size:1.9rem;font-weight:900;color:#0C1E7F;line-height:1;}
  .plan-btn .p-duration{font-weight:900;font-size:.9rem;margin-top:8px;color:#0f172a;}
  .plan-btn .p-desc{font-size:.72rem;color:#6b7280;margin-top:4px;}
  .plan-btn.best{border-color:#d97706;background:linear-gradient(135deg,#fff,#fef9c3);position:relative;}
  .plan-btn.best::before{content:"⭐ BEST";position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#d97706,#b45309);color:#fff;font-size:.62rem;font-weight:900;padding:4px 12px;border-radius:20px;box-shadow:0 4px 8px rgba(217,119,6,.4);}

  .manual-numbers{background:linear-gradient(135deg,#fffbeb,#fef3c7);border-radius:16px;padding:18px;border-left:5px solid #d97706;box-shadow:0 8px 16px rgba(217,119,6,.12);}
  .manual-numbers code{background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;padding:6px 12px;border-radius:8px;font-weight:800;font-size:.85rem;}

  .input-3d,.form-select-3d{width:100%;padding:14px 16px;border:2px solid #d1d5db;border-radius:12px;font-size:1rem;background:#fff;transition:all .2s;box-shadow:inset 0 2px 4px rgba(0,0,0,.04);}
  .input-3d:focus,.form-select-3d:focus{outline:none;border-color:#0C1E7F;box-shadow:inset 0 2px 4px rgba(0,0,0,.04),0 0 0 4px rgba(12,30,127,.15);}
  .label-3d{font-weight:900;font-size:.78rem;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:block;}

  .btn-3d{display:inline-block;padding:16px 24px;border-radius:14px;font-weight:900;border:none;color:#fff;transition:transform .1s,box-shadow .1s;width:100%;font-size:1.05rem;cursor:pointer;letter-spacing:.5px;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 8px 0 #1a3a12,0 14px 28px rgba(58,119,40,.4);}
  .btn-3d.green:active{transform:translateY(6px);box-shadow:0 2px 0 #1a3a12;}

  .hist-3d{background:linear-gradient(135deg,#f8faff,#eff6ff);border-radius:14px;padding:14px 16px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;gap:12px;box-shadow:0 6px 14px rgba(12,30,127,.1);border-left:5px solid #0C1E7F;flex-wrap:wrap;}
  .hist-3d.approved{border-left-color:#3A7728;}
  .hist-3d.pending{border-left-color:#d97706;}
  .hist-3d.rejected{border-left-color:#CE1126;}

  .status-banner{border-radius:18px;padding:20px;color:#fff;box-shadow:0 20px 40px rgba(0,0,0,.3);position:relative;overflow:hidden;}
  .status-banner.active{background:linear-gradient(135deg,#3A7728,#16a34a);}
  .status-banner.inactive{background:linear-gradient(135deg,#d97706,#b45309);}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4" style="max-width:950px;position:relative;z-index:2;">

  <div class="text-center mb-4">
    <h2 class="fw-bold text-white mb-1">💳 Subscription</h2>
    <p style="color:rgba(255,255,255,.8);">Choose your grade → choose a plan → pay</p>
  </div>

  <!-- CURRENT SUB -->
  <?php if (!empty($active_grades)): ?>
    <div class="status-banner <?= $active ? 'active' : 'inactive' ?> mb-4">
      <div class="small opacity-75">Currently Subscribed</div>
      <div class="fs-5 fw-bold"><?= implode(' · ', array_map('e', $active_grades)) ?></div>
      <?php if ($active): ?>
        <div class="small mt-1">Expires <?= e(fmt_date($user['subscription_end'])) ?> · <?= sub_days_left($user) ?> day(s) left</div>
      <?php else: ?>
        <div class="small mt-1">⚠️ Not active — pick a plan below</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">

    <!-- STEP 1 -->
    <div class="glass mb-4">
      <div class="section-title">1️⃣ Pick Your Grade</div>
      <div class="row g-3">
        <?php
        $grade_meta = ['NAT Grade 3'=>'🎒','NAT Grade 6'=>'📘','NAT Grade 8'=>'🎓','GABECE'=>'🏆'];
        foreach (CLASS_LEVELS as $g):
          $owned = in_array($g, $active_grades, true);
        ?>
          <div class="col-6 col-md-3">
            <label class="grade-pick <?= $owned ? 'owned' : '' ?>">
              <input type="radio" name="grade" value="<?= e($g) ?>">
              <div class="gp-icon"><?= $grade_meta[$g] ?? '🎓' ?></div>
              <div class="flex-grow-1">
                <div class="gp-title"><?= e($g) ?></div>
              </div>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- STEP 2 -->
    <div class="glass mb-4">
      <div class="section-title">2️⃣ One Grade Plans</div>
      <div class="row g-3">
        <?php foreach (PLANS as $key => $p): if ($p['type'] !== 'single') continue; ?>
          <div class="col-6 col-md-4">
            <label class="plan-btn <?= $key === 'single_month' ? 'best' : '' ?>">
              <input type="radio" name="plan" value="<?= e($key) ?>">
              <div class="p-price">D<?= (int)$p['amount'] ?></div>
              <div class="p-duration"><?= e($p['duration_label']) ?></div>
              <div class="p-desc">All subjects · 1 grade</div>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- STEP 3 -->
    <?php $all_plans = array_filter(PLANS, fn($p) => $p['type'] === 'all'); ?>
    <?php if (!empty($all_plans)): ?>
      <div class="glass mb-4">
        <div class="section-title">3️⃣ All Grades Plan</div>
        <div class="row g-3">
          <?php foreach ($all_plans as $key => $p): ?>
            <div class="col-6 col-md-4">
              <label class="plan-btn">
                <input type="radio" name="plan" value="<?= e($key) ?>">
                <div class="p-price">D<?= (int)$p['amount'] ?></div>
                <div class="p-duration"><?= e($p['duration_label']) ?></div>
                <div class="p-desc">Every subject · every grade</div>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- STEP 4 -->
    <div class="glass mb-4">
      <div class="section-title">4️⃣ Payment Details</div>

      <div class="manual-numbers mb-3">
        <div class="fw-bold mb-2" style="color:#78350f;">📞 Send payment to:</div>
        <ul class="list-unstyled mb-0" style="font-size:.92rem;">
          <li>🌊 <b>Wave:</b> <code><?= e($merchant_phones['Wave']) ?></code></li>
          <li class="mt-1">📱 <b>Afrimoney:</b> <code><?= e($merchant_phones['Afrimoney']) ?></code></li>
          <li class="mt-1">💳 <b>QMoney:</b> <code><?= e($merchant_phones['QMoney']) ?></code></li>
        </ul>
        <p class="small text-muted mt-2 mb-0">Name: <b>Moses Ibuchim C.</b></p>
      </div>

      <div class="mb-3">
        <label class="label-3d">Method</label>
        <select name="method" class="form-select-3d" required>
          <option value="">-- Choose --</option>
          <option>Wave</option>
          <option>QMoney</option>
          <option>Afrimoney</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="label-3d">Reference / Transaction ID</label>
        <input name="reference" class="input-3d" placeholder="e.g. TX123456789" required>
      </div>

      <div class="mb-3">
        <label class="label-3d">Screenshot (optional)</label>
        <input type="file" name="screenshot" class="input-3d" accept="image/*,application/pdf">
      </div>

      <button class="btn-3d green">✅ Submit Payment</button>
    </div>

  </form>

  <!-- HISTORY -->
  <?php if ($payments): ?>
    <div class="glass">
      <h5 class="mb-3" style="color:#0C1E7F;font-weight:900;">📜 Payment History</h5>
      <?php foreach ($payments as $p):
        $iconMap = ['approved'=>'✅','pending'=>'⏳','rejected'=>'❌','failed'=>'⚠️','cancelled'=>'🚫'];
        $icon = $iconMap[$p['status']] ?? '•';
        $label = PLANS[$p['plan']]['label'] ?? ($p['plan'] . ' — D' . $p['amount']);
      ?>
        <div class="hist-3d <?= e($p['status']) ?>">
          <div class="fs-3"><?= $icon ?></div>
          <div class="flex-grow-1">
            <div class="fw-bold" style="font-size:.9rem;"><?= e($label) ?></div>
            <div style="font-size:.72rem;color:#6b7280;">
              <?= e($p['method']) ?> · <?= e(fmt_date($p['created_at'])) ?>
              <?php if (!empty($p['reference'])): ?> · Ref: <?= e($p['reference']) ?><?php endif; ?>
            </div>
          </div>
          <div class="fw-bold" style="color:#0C1E7F;">D<?= (int)$p['amount'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script>
(function () {
  document.querySelectorAll('.grade-pick').forEach(function (pick) {
    pick.addEventListener('click', function () {
      document.querySelectorAll('.grade-pick').forEach(function (p) { p.classList.remove('selected'); });
      pick.classList.add('selected');
      pick.querySelector('input').checked = true;
    });
  });
  document.querySelectorAll('.plan-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.plan-btn').forEach(function (b) { b.classList.remove('selected'); });
      btn.classList.add('selected');
      btn.querySelector('input').checked = true;
    });
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>