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

/* Parse active grades */
$active_grades = [];
if (!empty($user['subscribed_grades'])) {
    $active_grades = array_map('trim', explode(',', $user['subscribed_grades']));
    $active_grades = array_values(array_filter($active_grades, fn($g) => $g !== ''));
}
if (($user['grade'] ?? '') === 'ALL' || strpos($user['subscription_plan'] ?? '', 'all_') === 0) {
    $active_grades = CLASS_LEVELS;
}
$can_add = array_values(array_diff(CLASS_LEVELS, $active_grades));
$active  = is_subscribed($user);
$days    = sub_days_left($user);
$has_all = (count($active_grades) === count(CLASS_LEVELS));

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
  .dash-container{position:relative;z-index:2;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  .blob-3{width:180px;height:180px;background:radial-gradient(circle at 30% 30%,#fbbf24,#b45309);top:45%;right:5%;animation-delay:5s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:28px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .hero-3d{background:linear-gradient(135deg,#CE1126 0%,#0C1E7F 50%,#3A7728 100%);border-radius:28px;padding:36px 30px;position:relative;overflow:hidden;box-shadow:0 40px 80px -20px rgba(12,30,127,.6),0 20px 40px -10px rgba(206,17,38,.4);color:#fff;}
  .hero-3d::before{content:"";position:absolute;top:-80px;right:-80px;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.1);}
  .hero-3d::after{content:"";position:absolute;bottom:-100px;left:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.07);}
  .hero-3d h2{font-weight:900;font-size:1.9rem;position:relative;z-index:2;margin:0;letter-spacing:-.5px;}
  .hero-3d p{position:relative;z-index:2;opacity:.9;margin:4px 0 0;font-size:.9rem;}

  .status-pill{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:20px;font-weight:800;font-size:.85rem;box-shadow:0 8px 20px rgba(0,0,0,.2);position:relative;z-index:2;}
  .pill-active{background:linear-gradient(135deg,#3A7728,#16a34a);color:#fff;}

  .action-card-3d{background:linear-gradient(135deg,#fff,#f8faff);border-radius:20px;padding:24px 18px;text-decoration:none;color:#0f172a;display:block;height:100%;box-shadow:0 15px 30px -10px rgba(12,30,127,.25),0 4px 8px rgba(12,30,127,.1),inset 0 1px 0 rgba(255,255,255,.9);transition:transform .25s ease,box-shadow .25s ease;border:2px solid transparent;}
  .action-card-3d:hover{transform:translateY(-8px) scale(1.02);box-shadow:0 30px 60px -15px rgba(12,30,127,.4);}
  .action-card-3d .ico{font-size:2.4rem;display:block;margin-bottom:10px;}
  .action-card-3d .title{font-weight:900;font-size:1rem;color:#0C1E7F;margin-bottom:4px;}
  .action-card-3d .sub{font-size:.78rem;color:#6b7280;line-height:1.3;}
  .action-card-3d.border-blue{border-color:#0C1E7F;background:linear-gradient(135deg,#eff6ff,#dbeafe);}
  .action-card-3d.border-red{border-color:#CE1126;background:linear-gradient(135deg,#fff1f2,#fee2e2);}
  .action-card-3d.border-green{border-color:#3A7728;background:linear-gradient(135deg,#f0fdf4,#dcfce7);}
  .action-card-3d.border-gold{border-color:#d97706;background:linear-gradient(135deg,#fffbeb,#fef3c7);}

  .grade-panel{display:flex;align-items:center;gap:14px;padding:16px;border-radius:16px;background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:2px solid #0C1E7F;box-shadow:0 8px 16px rgba(12,30,127,.12);transition:transform .25s;}
  .grade-panel:hover{transform:translateX(6px);}
  .grade-emoji{font-size:2rem;width:56px;height:56px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#fff;border-radius:14px;box-shadow:0 6px 12px rgba(0,0,0,.1);}
  .grade-title{font-weight:900;color:#0C1E7F;font-size:1rem;}
  .grade-meta{font-size:.75rem;color:#6b7280;margin-top:2px;}

  .notice-3d{background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:16px;padding:16px;border:2px dashed #d97706;display:flex;align-items:center;gap:14px;flex-wrap:wrap;box-shadow:0 10px 22px rgba(217,119,6,.2);}
  .notice-all{background:linear-gradient(135deg,#dcfce7,#bbf7d0);border:2px solid #16a34a;box-shadow:0 10px 22px rgba(22,163,74,.2);}

  .btn-3d{display:inline-block;padding:12px 22px;border-radius:12px;font-weight:800;text-decoration:none;border:none;color:#fff;transition:transform .1s,box-shadow .1s;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 6px 0 #1a3a12,0 10px 20px rgba(58,119,40,.4);}
  .btn-3d.green:active{transform:translateY(4px);box-shadow:0 2px 0 #1a3a12;}
  .btn-3d.gold{background:linear-gradient(135deg,#d97706,#b45309);box-shadow:0 6px 0 #5c2b04,0 10px 20px rgba(217,119,6,.4);}
  .btn-3d.gold:active{transform:translateY(4px);box-shadow:0 2px 0 #5c2b04;}
  .btn-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);box-shadow:0 6px 0 #050b33,0 10px 20px rgba(12,30,127,.4);}
  .btn-3d.blue:active{transform:translateY(4px);box-shadow:0 2px 0 #050b33;}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>
<div class="bg-blob blob-3"></div>

<div class="container py-4 dash-container" style="max-width:1000px">

  <div class="hero-3d mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h2>👋 Welcome, <?= e($user['name']) ?></h2>
        <p>Your learning dashboard</p>
      </div>
      <span class="status-pill pill-active">
        ✅ Active · <?= $days ?> days left
      </span>
    </div>

    <?php if (!empty($user['subscription_plan']) && isset(PLANS[$user['subscription_plan']])): ?>
      <div class="mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,.25);position:relative;z-index:2;">
        <div class="small opacity-75">Current Plan</div>
        <div class="fw-bold"><?= e(PLANS[$user['subscription_plan']]['label']) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="glass mb-4">
    <h5 class="fw-bold mb-3" style="color:#0C1E7F;">
      🎓 Your Subscribed Grades
      <span class="badge bg-primary ms-2"><?= count($active_grades) ?></span>
    </h5>

    <?php if (empty($active_grades)): ?>
      <div class="alert alert-warning mb-0">
        ⚠️ No active grades yet.
        <a href="<?= SITE_URL ?>/student/subscription.php" class="fw-bold">Subscribe now →</a>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($active_grades as $g):
          $is_all = ($g === 'ALL');
          $label = $is_all ? '🌟 All Grades' : $g;
          $icon  = $is_all ? '🌟' : (['NAT Grade 3'=>'🎒','NAT Grade 6'=>'📘','NAT Grade 8'=>'🎓','GABECE'=>'🏆'][$g] ?? '🎓');
        ?>
          <div class="col-12 col-md-6">
            <div class="grade-panel">
              <div class="grade-emoji"><?= $icon ?></div>
              <div class="flex-grow-1">
                <div class="grade-title"><?= e($label) ?></div>
                <div class="grade-meta">Active until <?= e(fmt_date($user['subscription_end'])) ?></div>
              </div>
              <a href="<?= SITE_URL ?>/student/subjects.php?grade=<?= urlencode($g) ?>" class="btn-3d blue" style="font-size:.8rem;padding:8px 14px;">Open →</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$has_all): ?>
      <div class="notice-3d mt-4">
        <div class="fs-2">🔓</div>
        <div class="flex-grow-1">
          <div class="fw-bold" style="color:#78350f;">You can get more grades here!</div>
          <div class="small" style="color:#78350f;"><?= count($can_add) ?> grade(s) available: <b><?= implode(' · ', array_map('e', $can_add)) ?></b></div>
        </div>
        <a href="<?= SITE_URL ?>/student/subscription.php" class="btn-3d gold">➕ Subscribe for More</a>
      </div>
    <?php else: ?>
      <div class="notice-3d notice-all mt-4">
        <div class="fs-2">🌟</div>
        <div class="flex-grow-1">
          <div class="fw-bold" style="color:#14532d;">You have every grade unlocked!</div>
          <div class="small" style="color:#14532d;">All 4 grades are active on your account. Great job!</div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <h5 class="fw-bold mb-3 text-white">⚡ Quick Actions</h5>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <a href="<?= SITE_URL ?>/student/subjects.php" class="action-card-3d border-blue">
        <span class="ico">📚</span>
        <div class="title">Subjects</div>
        <div class="sub">Browse your subjects</div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a href="<?= SITE_URL ?>/student/exam.php" class="action-card-3d border-gold">
        <span class="ico">📝</span>
        <div class="title">Write Test</div>
        <div class="sub">Practice exam</div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a href="<?= SITE_URL ?>/student/subscription.php" class="action-card-3d border-green">
        <span class="ico">💳</span>
        <div class="title">Subscription</div>
        <div class="sub">View plans</div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a href="<?= SITE_URL ?>/student/profile.php" class="action-card-3d border-red">
        <span class="ico">👤</span>
        <div class="title">Profile</div>
        <div class="sub">Your account</div>
      </a>
    </div>
  </div>

  <?php if (!empty($recent)): ?>
    <div class="glass">
      <h5 class="fw-bold mb-3" style="color:#0C1E7F;">📜 Recent Tests</h5>
      <?php foreach ($recent as $a):
        $badge = $a['percent'] >= 70 ? '#3A7728' : ($a['percent'] >= 50 ? '#d97706' : '#CE1126');
      ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
          <div>
            <div class="fw-bold small"><?= e($a['subject_name'] ?? '—') ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= e($a['grade']) ?> · <?= e(fmt_date($a['finished_at'] ?? $a['started_at'])) ?></div>
          </div>
          <span class="badge" style="background:<?= $badge ?>;"><?= (int)$a['score'] ?>/<?= (int)$a['total'] ?> · <?= (int)$a['percent'] ?>%</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>