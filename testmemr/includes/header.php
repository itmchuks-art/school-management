<?php
if (!function_exists('e')) {
    require_once __DIR__ . '/db.php';
}

$__is_admin   = !empty($_SESSION['admin_id']);
$__is_student = !empty($_SESSION['user_id']);
$__current    = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Test Me Mr 🇬🇲</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<script>
/* Apply saved theme BEFORE page renders (avoids flash) */
(function () {
  try {
    var theme = localStorage.getItem('tmr_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
  } catch (e) {}
})();
</script>

<style>
  :root {
    --gambia-red:   #CE1126;
    --gambia-blue:  #0C1E7F;
    --gambia-green: #3A7728;
  }

  /* ============================================================
     DARK THEME (default) — 3D Navy
     ============================================================ */
  html[data-theme="dark"] body {
    background: radial-gradient(circle at 20% 10%, #1e3a8a 0%, #0C1E7F 40%, #050b33 100%) !important;
    background-attachment: fixed !important;
    color: #fff;
    min-height: 100vh;
  }

  /* ============================================================
     LIGHT THEME — clean white / soft
     ============================================================ */
  html[data-theme="light"] body {
    background: linear-gradient(135deg, #f0f4ff 0%, #e8f5e9 50%, #fff5f5 100%) !important;
    background-attachment: fixed !important;
    color: #0f172a;
    min-height: 100vh;
  }

  /* Light theme tweaks for common elements */
  html[data-theme="light"] .bg-blob { opacity: 0.15 !important; }
  html[data-theme="light"] .glass {
    background: #fff !important;
    color: #0f172a !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 15px 35px -10px rgba(12,30,127,.15), 0 4px 8px rgba(12,30,127,.08) !important;
  }
  html[data-theme="light"] .hero-3d {
    box-shadow: 0 20px 40px -10px rgba(12,30,127,.25) !important;
  }
  html[data-theme="light"] h1,
  html[data-theme="light"] h2,
  html[data-theme="light"] h3,
  html[data-theme="light"] h4,
  html[data-theme="light"] h5,
  html[data-theme="light"] h6 {
    color: #0C1E7F !important;
  }
  html[data-theme="light"] .text-white,
  html[data-theme="light"] h5.text-white,
  html[data-theme="light"] .fw-bold.text-white {
    color: #0C1E7F !important;
  }
  html[data-theme="light"] .stat-3d,
  html[data-theme="light"] .action-card-3d,
  html[data-theme="light"] .student-row,
  html[data-theme="light"] .subj-row,
  html[data-theme="light"] .q-row,
  html[data-theme="light"] .pay-row,
  html[data-theme="light"] .hist-3d,
  html[data-theme="light"] .grade-panel,
  html[data-theme="light"] .subject-card-3d,
  html[data-theme="light"] .feature-3d,
  html[data-theme="light"] .price-3d,
  html[data-theme="light"] .plan-btn,
  html[data-theme="light"] .grade-pick,
  html[data-theme="light"] .card-3d {
    background: #fff !important;
    color: #0f172a !important;
    box-shadow: 0 10px 25px -8px rgba(12,30,127,.15), 0 3px 6px rgba(12,30,127,.06) !important;
  }
  html[data-theme="light"] .section-3d,
  html[data-theme="light"] .manual-numbers {
    background: #fff !important;
    color: #0f172a !important;
  }
  html[data-theme="light"] .notice-3d {
    background: linear-gradient(135deg,#fef3c7,#fde68a) !important;
    color: #78350f !important;
  }
  html[data-theme="light"] .notice-all {
    background: linear-gradient(135deg,#dcfce7,#bbf7d0) !important;
    color: #14532d !important;
  }
  html[data-theme="light"] .sub-banner,
  html[data-theme="light"] .status-banner.active {
    color: #fff !important;
  }
  html[data-theme="light"] .status-banner.active * {
    color: #fff !important;
  }

  /* Input fields in light mode */
  html[data-theme="light"] .input-3d,
  html[data-theme="light"] .form-select-3d,
  html[data-theme="light"] .search-input {
    background: #fff !important;
    color: #0f172a !important;
    border-color: #d1d5db !important;
  }

  /* ============================================================
     FLOATING THEME TOGGLE
     ============================================================ */
  #themeToggle {
    position: fixed;
    bottom: 22px;
    right: 22px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    font-size: 1.5rem;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform .25s, box-shadow .25s, background .25s;
    box-shadow: 0 10px 25px rgba(0,0,0,.35), 0 4px 10px rgba(0,0,0,.2);
    color: #fff;
    background: linear-gradient(135deg, #0C1E7F, #1e40af);
  }
  #themeToggle:hover {
    transform: translateY(-4px) scale(1.08) rotate(15deg);
    box-shadow: 0 15px 30px rgba(0,0,0,.4);
  }
  #themeToggle:active {
    transform: translateY(2px) scale(1);
  }
  html[data-theme="light"] #themeToggle {
    background: linear-gradient(135deg, #d97706, #b45309);
  }

  /* Hide blobs on light mode by default */
  html[data-theme="light"] .bg-blob {
    display: none;
  }

  /* ============ TOP NAV ============ */
  .tmr-nav {
    background: #fff;
    box-shadow: 0 2px 12px rgba(12,30,127,.08);
    padding: 10px 0;
  }
  .tmr-brand {
    display: flex; align-items: center; gap: 12px;
    text-decoration: none; color: #0f172a;
    font-weight: 800; font-size: 1.1rem;
  }
  .tmr-flag {
    width: 44px; height: 32px; border-radius: 4px; overflow: hidden;
    border: 1px solid #e5e7eb; display: flex; flex-direction: column;
  }
  .tmr-flag div { width: 100%; }
  .tmr-flag .f1 { height: 8px;  background: var(--gambia-red); }
  .tmr-flag .f2 { height: 4px;  background: #fff; }
  .tmr-flag .f3 { height: 8px;  background: var(--gambia-blue); }
  .tmr-flag .f4 { height: 4px;  background: #fff; }
  .tmr-flag .f5 { height: 8px;  background: var(--gambia-green); }

  .tmr-nav .nav-link {
    color: #0f172a; font-weight: 600; padding: 6px 14px !important;
    border-radius: 10px; font-size: .92rem; transition: all .15s;
  }
  .tmr-nav .nav-link:hover { background: #eff6ff; color: var(--gambia-blue); }
  .tmr-nav .nav-link.active {
    background: linear-gradient(135deg, var(--gambia-blue), #1e40af);
    color: #fff;
  }
  .tmr-nav .nav-link.danger-link { color: var(--gambia-red); }
  .tmr-nav .nav-link.danger-link:hover { background: #fee2e2; }
  .tmr-nav .nav-link.admin-link {
    background: linear-gradient(135deg, var(--gambia-red), #a30d1e);
    color: #fff; font-weight: 700;
  }
  .tmr-nav .nav-link.admin-link:hover { filter: brightness(1.1); color: #fff; }

  .tmr-username { font-weight: 700; color: var(--gambia-blue); font-size: .85rem; }

  .tmr-flash { margin-top: 14px; }
  .card { border-radius: 14px; }

  @media (max-width: 576px) {
    .tmr-brand { font-size: 1rem; }
    .tmr-nav .nav-link { padding: 6px 10px !important; font-size: .85rem; }
    #themeToggle { width: 48px; height: 48px; font-size: 1.25rem; bottom: 16px; right: 16px; }
  }
</style>
</head>
<body>

<!-- Theme toggle -->
<button id="themeToggle" title="Toggle light/dark mode" aria-label="Toggle theme">🌙</button>

<nav class="tmr-nav">
  <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">

    <a class="tmr-brand" href="<?= SITE_URL ?>/index.php">
      <div class="tmr-flag">
        <div class="f1"></div><div class="f2"></div><div class="f3"></div>
        <div class="f4"></div><div class="f5"></div>
      </div>
      <span>Test Me Mr 🇬🇲</span>
    </a>

    <ul class="nav flex-wrap align-items-center mb-0">

      <?php if ($__is_admin): ?>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/admin/dashboard.php">🛡️ Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'payments.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/admin/payments.php">💳 Payments</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'students.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/admin/students.php">👥 Students</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'subjects.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/admin/subjects.php">📚 Subjects</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'questions.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/admin/questions.php">❓ Questions</a>
        </li>
        <li class="nav-item">
          <span class="tmr-username ms-2">👤 <?= e($_SESSION['admin_name'] ?? 'Admin') ?></span>
        </li>
        <li class="nav-item">
          <a class="nav-link danger-link" href="<?= SITE_URL ?>/admin/logout.php">Logout</a>
        </li>

      <?php elseif ($__is_student): ?>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/student/') !== false ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/student/dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'subjects.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/student/subjects.php">Subjects</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'exam.php' || $__current === 'take_test.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/student/exam.php">📝 Write Test</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'subscription.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/student/subscription.php">Subscribe</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'profile.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/student/profile.php">Profile</a>
        </li>
        <li class="nav-item">
          <a class="nav-link danger-link" href="<?= SITE_URL ?>/student/logout.php">Logout</a>
        </li>

      <?php else: ?>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'login.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/login.php">Login</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $__current === 'register.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/register.php">Student Register</a>
        </li>
      <?php endif; ?>

    </ul>
  </div>
</nav>

<?php
$__flashes = function_exists('take_flashes') ? take_flashes() : [];
if ($__flashes):
?>
<div class="container tmr-flash">
  <?php foreach ($__flashes as $f):
    $t = $f['t'] ?? 'info';
    $bs = ['success'=>'success','info'=>'info','warning'=>'warning','danger'=>'danger','error'=>'danger'][$t] ?? 'info';
  ?>
    <div class="alert alert-<?= $bs ?> alert-dismissible fade show" role="alert">
      <?= $f['m'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
/* ============================================================
   THEME TOGGLE
   ============================================================ */
(function () {
  var btn = document.getElementById('themeToggle');
  if (!btn) return;

  function getTheme() {
    return document.documentElement.getAttribute('data-theme') || 'dark';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('tmr_theme', theme); } catch (e) {}
    btn.textContent = theme === 'dark' ? '🌙' : '☀️';
    btn.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
  }

  /* Apply on load */
  applyTheme(getTheme());

  /* Toggle on click */
  btn.addEventListener('click', function () {
    var next = getTheme() === 'dark' ? 'light' : 'dark';
    applyTheme(next);
  });
})();
</script>