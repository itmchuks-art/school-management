<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

/* Refresh user */
$st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([(int)$user['id']]);
$user = $st->fetch();

/* ⚠️ If subscription has expired — send them to the no-subscription page */
if (!is_subscribed($user)) {
    redirect(SITE_URL . '/student/no_subscription.php');
}

if (empty($user['grade']) && empty($user['subscribed_grades'])) {
    redirect(SITE_URL . '/student/choose_grade.php');
}

$allowed_grades = [];
if (!empty($user['subscribed_grades'])) {
    $allowed_grades = array_map('trim', explode(',', $user['subscribed_grades']));
    $allowed_grades = array_values(array_filter($allowed_grades));
}
if (($user['grade'] ?? '') === 'ALL' || strpos($user['subscription_plan'] ?? '', 'all_') === 0) {
    $allowed_grades = CLASS_LEVELS;
}
if (empty($allowed_grades) && !empty($user['grade']) && $user['grade'] !== 'ALL') {
    $allowed_grades = [$user['grade']];
}

$active = true;  /* we already verified subscription above */

$view = trim($_GET['grade'] ?? ($allowed_grades[0] ?? ''));
if (!in_array($view, $allowed_grades, true)) $view = $allowed_grades[0] ?? null;

$subjects = [];
if ($view) {
    try {
        $st = $pdo->prepare('SELECT * FROM subjects WHERE class_level = ? OR grade = ? ORDER BY name ASC');
        $st->execute([$view, $view]);
        $subjects = $st->fetchAll();
    } catch (Exception $e) {}
}

$can_add = array_values(array_diff(CLASS_LEVELS, $allowed_grades));

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;color:#fff;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:28px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .tab-3d{display:inline-block;padding:10px 20px;border-radius:14px;text-decoration:none;font-weight:800;font-size:.85rem;margin:4px;transition:all .25s;box-shadow:0 6px 14px rgba(12,30,127,.15);border:2px solid #0C1E7F;color:#0C1E7F;background:#fff;}
  .tab-3d:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(12,30,127,.25);}
  .tab-3d.active{background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;border-color:#050b33;box-shadow:0 8px 0 #050b33,0 12px 24px rgba(12,30,127,.4);}

  .notice-3d{background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:16px;padding:16px;border:2px dashed #d97706;display:flex;align-items:center;gap:14px;flex-wrap:wrap;box-shadow:0 10px 22px rgba(217,119,6,.2);}

  .subject-card-3d{display:block;padding:28px 20px;border-radius:20px;text-decoration:none;color:#0f172a;text-align:center;box-shadow:0 20px 40px -12px rgba(12,30,127,.3),0 6px 12px rgba(12,30,127,.12),inset 0 1px 0 rgba(255,255,255,.9);transition:transform .25s ease,box-shadow .25s ease;border:3px solid;height:100%;}
  .subject-card-3d:hover{transform:translateY(-8px) scale(1.03) rotateX(5deg);box-shadow:0 40px 60px -15px rgba(12,30,127,.4);}
  .subject-ico{font-size:3rem;display:block;margin-bottom:10px;}
  .subject-name{font-weight:900;font-size:1.1rem;color:#0C1E7F;margin-bottom:6px;}
  .subject-grade{font-size:.75rem;color:#6b7280;margin-bottom:10px;}

  .card-maths{border-color:#0C1E7F;background:linear-gradient(135deg,#fff,#eff6ff);}
  .card-english{border-color:#CE1126;background:linear-gradient(135deg,#fff,#fff1f2);}
  .card-science{border-color:#3A7728;background:linear-gradient(135deg,#fff,#f0fdf4);}
  .card-ses{border-color:#d97706;background:linear-gradient(135deg,#fff,#fffbeb);}
  .card-default{border-color:#6b7280;background:linear-gradient(135deg,#fff,#f8fafc);}

  .badge-unlocked{display:inline-block;background:linear-gradient(135deg,#3A7728,#16a34a);color:#fff;font-size:.7rem;font-weight:800;padding:6px 14px;border-radius:20px;box-shadow:0 4px 10px rgba(58,119,40,.3);}

  .btn-3d{display:inline-block;padding:12px 22px;border-radius:12px;font-weight:800;text-decoration:none;border:none;color:#fff;transition:transform .1s,box-shadow .1s;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 6px 0 #1a3a12,0 10px 20px rgba(58,119,40,.4);}
  .btn-3d.green:active{transform:translateY(4px);box-shadow:0 2px 0 #1a3a12;}
  .btn-3d.gold{background:linear-gradient(135deg,#d97706,#b45309);box-shadow:0 6px 0 #5c2b04,0 10px 20px rgba(217,119,6,.4);}
  .btn-3d.gold:active{transform:translateY(4px);box-shadow:0 2px 0 #5c2b04;}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4" style="max-width:950px;position:relative;z-index:2;">

  <div class="text-center mb-4">
    <h2 class="fw-bold text-white mb-1">📚 Subjects</h2>
    <p style="color:rgba(255,255,255,.8);">Browse the subjects available in your grade.</p>
  </div>

  <?php if (empty($allowed_grades)): ?>
    <div class="glass text-center">
      No grades unlocked. <a href="<?= SITE_URL ?>/student/subscription.php" class="fw-bold">Subscribe →</a>
    </div>
  <?php else: ?>

    <div class="text-center mb-4">
      <?php foreach ($allowed_grades as $g): ?>
        <a href="?grade=<?= urlencode($g) ?>" class="tab-3d <?= $g === $view ? 'active' : '' ?>"><?= e($g) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($can_add)): ?>
      <div class="notice-3d mb-4">
        <div class="fs-2">🔓</div>
        <div class="flex-grow-1">
          <div class="fw-bold" style="color:#78350f;">You can get more grades here!</div>
          <div class="small" style="color:#78350f;">Available: <b><?= implode(' · ', array_map('e', $can_add)) ?></b></div>
        </div>
        <a href="<?= SITE_URL ?>/student/subscription.php" class="btn-3d gold">➕ More Grades</a>
      </div>
    <?php endif; ?>

    <div class="glass mb-4" style="background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;border:none;">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="fs-1">📝</div>
        <div class="flex-grow-1">
          <div class="fw-bold" style="font-size:1.05rem;">Ready to test yourself?</div>
          <div class="small" style="opacity:.85;">Click below to answer questions and get your score.</div>
        </div>
        <a href="<?= SITE_URL ?>/student/exam.php" class="btn-3d green">📝 Write Test →</a>
      </div>
    </div>

    <?php if (empty($subjects)): ?>
      <div class="glass text-center">📭 No subjects added for <b><?= e($view) ?></b> yet.</div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($subjects as $s):
          $name = $s['name'] ?? '';
          $cls = 'card-default';
          if (stripos($name, 'math') !== false) $cls = 'card-maths';
          elseif (stripos($name, 'english') !== false) $cls = 'card-english';
          elseif (stripos($name, 'science') !== false) $cls = 'card-science';
          elseif (stripos($name, 'ses') !== false || stripos($name, 'social') !== false) $cls = 'card-ses';
        ?>
          <div class="col-6 col-md-4">
            <div class="subject-card-3d <?= $cls ?>">
              <span class="subject-ico">📖</span>
              <div class="subject-name"><?= e($name) ?></div>
              <div class="subject-grade"><?= e($s['class_level'] ?? $s['grade'] ?? '') ?></div>
              <span class="badge-unlocked">✅ Unlocked</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>