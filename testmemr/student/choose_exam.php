<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

/* If grade is already saved, skip to subscription */
if (!empty($user['grade']) &&
    in_array($user['grade'], CLASS_LEVELS, true)) {
    redirect(SITE_URL . '/student/subscription.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = trim($_POST['grade'] ?? '');

    if (!in_array($grade, CLASS_LEVELS, true)) {
        $error = 'Please choose your grade.';
    } else {
        $pdo->prepare('UPDATE users SET grade = ? WHERE id = ?')
            ->execute([$grade, (int)$user['id']]);

        flash('✅ Saved. Now choose your subscription plan.');
        redirect(SITE_URL . '/student/subscription.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="max-width:640px">

  <div class="text-center mb-4">
    <h2 class="fw-bold" style="color:#0C1E7F">🎓 Choose Your Grade</h2>
    <p class="text-muted">Pick the grade you are studying for.</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="row g-3">

      <div class="col-12">
        <label class="grade-card">
          <input type="radio" name="grade" value="NAT Grade 3" required>
          <div class="gc-icon">🎒</div>
          <div class="gc-body">
            <div class="gc-title">NAT Grade 3</div>
            <div class="gc-sub">Lower Basic · National Assessment Test</div>
          </div>
        </label>
      </div>

      <div class="col-12">
        <label class="grade-card">
          <input type="radio" name="grade" value="NAT Grade 6" required>
          <div class="gc-icon">📘</div>
          <div class="gc-body">
            <div class="gc-title">NAT Grade 6</div>
            <div class="gc-sub">Upper Basic · National Assessment Test</div>
          </div>
        </label>
      </div>

      <div class="col-12">
        <label class="grade-card">
          <input type="radio" name="grade" value="NAT Grade 8" required>
          <div class="gc-icon">🎓</div>
          <div class="gc-body">
            <div class="gc-title">NAT Grade 8</div>
            <div class="gc-sub">Upper Basic · National Assessment Test</div>
          </div>
        </label>
      </div>

      <div class="col-12">
        <label class="grade-card">
          <input type="radio" name="grade" value="GABECE" required>
          <div class="gc-icon">🏆</div>
          <div class="gc-body">
            <div class="gc-title">GABECE</div>
            <div class="gc-sub">Gambia Basic Education Certificate</div>
          </div>
        </label>
      </div>

    </div>

    <button type="submit" class="btn btn-success w-100 mt-4 py-3 fw-bold"
            style="border-radius:12px;background:linear-gradient(135deg,#3A7728,#16a34a);border:none">
      Continue to Subscription →
    </button>
  </form>
</div>

<style>
  .grade-card{display:flex;align-items:center;gap:14px;padding:16px 18px;border:3px solid #e5e7eb;border-radius:16px;background:#fff;cursor:pointer;transition:all .15s;width:100%;}
  .grade-card:hover{border-color:#0C1E7F;transform:translateY(-2px);box-shadow:0 8px 20px rgba(12,30,127,.10);}
  .grade-card input{display:none;}
  .grade-card:has(input:checked){border-color:#0C1E7F;background:linear-gradient(135deg,#eff6ff,#f0fdf4);box-shadow:0 8px 22px rgba(12,30,127,.18);}
  .gc-icon{font-size:1.8rem;width:52px;height:52px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#f1f5f9;border-radius:12px;}
  .gc-body{flex:1}
  .gc-title{font-weight:800;color:#0f172a;font-size:1rem;}
  .gc-sub{font-size:.8rem;color:#6b7280;margin-top:2px;}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>