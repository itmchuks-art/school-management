<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

$st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([(int)$user['id']]);
$user = $st->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || strlen($name) < 3) {
        $error = 'Enter a valid name (at least 3 characters).';
    } elseif (!preg_match('/^[0-9]{7,10}$/', $phone)) {
        $error = 'Enter a valid phone number (7–10 digits).';
    } else {
        $chk = $pdo->prepare('SELECT id FROM users WHERE name = ? AND phone = ? AND id != ? LIMIT 1');
        $chk->execute([$name, $phone, (int)$user['id']]);
        if ($chk->fetch()) {
            $error = 'Another student already uses that name + phone.';
        } else {
            $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')
                ->execute([$name, $phone, (int)$user['id']]);
            $_SESSION['user_name'] = $name;
            flash('✅ Profile updated.');
            redirect(SITE_URL . '/student/profile.php');
        }
    }
}

$st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([(int)$user['id']]);
$user = $st->fetch();

$active_grades = [];
if (!empty($user['subscribed_grades'])) {
    $active_grades = array_map('trim', explode(',', $user['subscribed_grades']));
    $active_grades = array_values(array_filter($active_grades));
}
if (($user['grade'] ?? '') === 'ALL') $active_grades = CLASS_LEVELS;
$active = is_subscribed($user);

/* Recent tests */
$recent = [];
try {
    $ph = $pdo->prepare('SELECT a.*, s.name AS subject_name FROM attempts a LEFT JOIN subjects s ON s.id = a.subject_id WHERE a.user_id = ? ORDER BY a.id DESC LIMIT 5');
    $ph->execute([(int)$user['id']]);
    $recent = $ph->fetchAll();
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;color:#fff;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:28px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .avatar-3d{width:110px;height:110px;margin:0 auto;border-radius:50%;background:linear-gradient(135deg,#CE1126 0%,#0C1E7F 50%,#3A7728 100%);display:flex;align-items:center;justify-content:center;font-size:3rem;color:#fff;box-shadow:0 20px 40px rgba(12,30,127,.5),0 10px 20px rgba(206,17,38,.3),inset 0 2px 4px rgba(255,255,255,.3);position:relative;}
  .avatar-3d::after{content:"";position:absolute;inset:6px;border-radius:50%;border:2px solid rgba(255,255,255,.4);}

  .glass h5,.glass h6{color:#0C1E7F;font-weight:900;}

  .input-3d{width:100%;padding:14px 16px;border:2px solid #d1d5db;border-radius:12px;font-size:1rem;background:#fff;transition:all .2s;box-shadow:inset 0 2px 4px rgba(0,0,0,.04);}
  .input-3d:focus{outline:none;border-color:#0C1E7F;box-shadow:inset 0 2px 4px rgba(0,0,0,.04),0 0 0 4px rgba(12,30,127,.15);}

  .label-3d{font-weight:800;font-size:.78rem;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block;}

  .btn-3d{display:inline-block;padding:14px 24px;border-radius:12px;font-weight:900;border:none;transition:transform .1s,box-shadow .1s;color:#fff;width:100%;font-size:1rem;cursor:pointer;}
  .btn-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);box-shadow:0 8px 0 #050b33,0 14px 28px rgba(12,30,127,.4);}
  .btn-3d.blue:active{transform:translateY(6px);box-shadow:0 2px 0 #050b33;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 8px 0 #1a3a12,0 14px 28px rgba(58,119,40,.4);}
  .btn-3d.green:active{transform:translateY(6px);box-shadow:0 2px 0 #1a3a12;}

  .stat-card{background:linear-gradient(135deg,#eff6ff,#f0fdf4);border-radius:14px;padding:16px;text-align:center;box-shadow:0 8px 16px rgba(12,30,127,.1);border:2px solid #0C1E7F;}
  .stat-label{font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;font-weight:700;}
  .stat-value{font-size:1.3rem;font-weight:900;color:#0C1E7F;margin-top:4px;}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4" style="max-width:700px;position:relative;z-index:2;">

  <!-- HEADER CARD -->
  <div class="glass mb-4 text-center">
    <div class="avatar-3d mb-3">👤</div>
    <h3 class="fw-bold mb-1" style="color:#0C1E7F;"><?= e($user['name']) ?></h3>
    <p class="text-muted mb-3"><?= e($user['phone']) ?></p>

    <span class="badge" style="font-size:.85rem;padding:8px 18px;background:<?= $active ? 'linear-gradient(135deg,#3A7728,#16a34a)' : 'linear-gradient(135deg,#CE1126,#dc2626)' ?>;">
      <?= $active ? '✅ Active · ' . sub_days_left($user) . ' days left' : '❌ Not Active' ?>
    </span>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="row g-3 mb-4">
    <div class="col-4">
      <div class="stat-card">
        <div class="stat-label">Account</div>
        <div class="stat-value">#<?= (int)$user['id'] ?></div>
      </div>
    </div>
    <div class="col-4">
      <div class="stat-card">
        <div class="stat-label">Grades</div>
        <div class="stat-value"><?= count($active_grades) ?></div>
      </div>
    </div>
    <div class="col-4">
      <div class="stat-card">
        <div class="stat-label">Tests</div>
        <div class="stat-value"><?= count($recent) ?></div>
      </div>
    </div>
  </div>

  <!-- EDIT FORM -->
  <div class="glass mb-4">
    <h5 class="mb-3">✏️ Edit Details</h5>
    <form method="post">
      <div class="mb-3">
        <label class="label-3d">Full Name</label>
        <input name="name" class="input-3d" value="<?= e($user['name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="label-3d">Phone Number</label>
        <input name="phone" class="input-3d" value="<?= e($user['phone']) ?>" required>
        <div class="small text-muted mt-1">7–10 digits, without +220</div>
      </div>
      <button class="btn-3d blue">💾 Save Changes</button>
    </form>
  </div>

  <!-- GRADES -->
  <div class="glass mb-4">
    <h5 class="mb-3">🎓 My Grades</h5>
    <?php if (empty($active_grades)): ?>
      <div class="alert alert-warning mb-0">No grades yet. <a href="<?= SITE_URL ?>/student/subscription.php" class="fw-bold">Subscribe →</a></div>
    <?php else: ?>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($active_grades as $g): ?>
          <span class="badge" style="background:linear-gradient(135deg,#0C1E7F,#1e40af);padding:10px 18px;font-size:.85rem;"><?= e($g) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- RECENT TESTS -->
  <?php if (!empty($recent)): ?>
    <div class="glass">
      <h5 class="mb-3">📜 Recent Tests</h5>
      <?php foreach ($recent as $a):
        $badge = $a['percent'] >= 70 ? '#3A7728' : ($a['percent'] >= 50 ? '#d97706' : '#CE1126');
      ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
          <div>
            <div class="fw-bold small"><?= e($a['subject_name'] ?? '—') ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= e(fmt_date($a['finished_at'] ?? $a['started_at'])) ?></div>
          </div>
          <span class="badge" style="background:<?= $badge ?>;">
            <?= (int)$a['score'] ?>/<?= (int)$a['total'] ?> · <?= (int)$a['percent'] ?>%
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>