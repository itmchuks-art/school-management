<?php
require_once __DIR__ . '/includes/db.php';

/* Already logged in? Send forward */
if (!empty($_SESSION['user_id'])) {
    $st = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$_SESSION['user_id']]);
    $u = $st->fetch();

    if ($u) {
        $dest = empty($u['grade'])
            ? SITE_URL . '/student/choose_grade.php'
            : SITE_URL . '/student/welcome.php';
        redirect($dest);
    }
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['session_token']);
}

$mode  = $_GET['mode'] ?? 'student';
$error = '';
$name  = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode  = $_POST['mode'] ?? 'student';
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || $phone === '') {
        $error = 'Enter your name and phone number.';
    } else {
        if ($mode === 'admin') {
            $st = $pdo->prepare('SELECT * FROM admins WHERE name = ? AND phone = ? LIMIT 1');
            $st->execute([$name, $phone]);
            $admin = $st->fetch();

            if (!$admin) {
                $error = 'Invalid admin name or phone.';
            } else {
                $_SESSION['admin_id']   = (int)$admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                flash('👋 Welcome, ' . $admin['name']);
                redirect(SITE_URL . '/admin/dashboard.php');
            }
        } else {
            $st = $pdo->prepare('SELECT * FROM users WHERE name = ? AND phone = ? LIMIT 1');
            $st->execute([$name, $phone]);
            $user = $st->fetch();

            if (!$user) {
                $error = 'No student account found. Please register.';
            } else {
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];

                unset($_SESSION['seen_welcome']);
                generate_session_token($pdo, (int)$user['id']);

                flash('👋 Welcome back, ' . $user['name'] . '!');

                $dest = empty($user['grade'])
                    ? SITE_URL . '/student/choose_grade.php'
                    : SITE_URL . '/student/welcome.php';
                redirect($dest);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Test Me Mr 🇬🇲 — Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing: border-box; }

  body {
    min-height: 100vh;
    margin: 0;
    overflow-x: hidden;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: radial-gradient(circle at 20% 10%, #1e3a8a 0%, #0C1E7F 35%, #050b33 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    perspective: 1200px;
  }

  /* ==== Floating background 3D shapes ==== */
  .bg-shape {
    position: fixed;
    border-radius: 50%;
    opacity: 0.4;
    filter: blur(1px);
    animation: float 8s ease-in-out infinite;
    z-index: 0;
    pointer-events: none;
  }
  .bg-shape.s1 {
    width: 320px; height: 320px;
    background: radial-gradient(circle at 30% 30%, #CE1126, #7a0b18);
    top: -100px; left: -100px;
    animation-delay: 0s;
  }
  .bg-shape.s2 {
    width: 220px; height: 220px;
    background: radial-gradient(circle at 30% 30%, #3A7728, #1a3a12);
    bottom: -80px; right: -80px;
    animation-delay: 2s;
  }
  .bg-shape.s3 {
    width: 160px; height: 160px;
    background: radial-gradient(circle at 30% 30%, #fbbf24, #b45309);
    top: 40%; right: 10%;
    animation-delay: 4s;
  }
  @keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50%      { transform: translateY(-30px) rotate(20deg); }
  }

  /* ==== Main 3D card ==== */
  .scene {
    perspective: 1400px;
    z-index: 2;
    width: 100%;
    max-width: 460px;
  }

  .card-3d {
    background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(240,245,255,0.92));
    border-radius: 28px;
    padding: 40px 32px 28px;
    box-shadow:
      0 40px 80px -20px rgba(0,0,0,.55),
      0 20px 40px -10px rgba(12,30,127,.45),
      inset 0 1px 0 rgba(255,255,255,.9);
    transform-style: preserve-3d;
    transform: rotateX(0deg) rotateY(0deg);
    transition: transform .15s ease-out;
    position: relative;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,.6);
  }

  /* Flag logo — 3D animated */
  .flag-3d {
    width: 90px;
    height: 64px;
    margin: 0 auto 20px;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow:
      0 15px 30px rgba(0,0,0,.35),
      0 6px 12px rgba(0,0,0,.25),
      inset 0 1px 2px rgba(255,255,255,.6);
    transform: translateZ(40px);
    transition: transform .3s ease;
  }
  .flag-3d:hover { transform: translateZ(60px) scale(1.05) rotateY(10deg); }
  .flag-3d div { width: 100%; }
  .flag-3d .f1 { height: 15px; background: #CE1126; }
  .flag-3d .f2 { height: 6px;  background: #fff; }
  .flag-3d .f3 { height: 15px; background: #0C1E7F; }
  .flag-3d .f4 { height: 6px;  background: #fff; }
  .flag-3d .f5 { height: 15px; background: #3A7728; }

  .brand-title {
    text-align: center;
    font-weight: 900;
    font-size: 1.9rem;
    color: #0C1E7F;
    margin-bottom: 4px;
    letter-spacing: -0.5px;
    transform: translateZ(30px);
  }
  .brand-sub {
    text-align: center;
    color: #6b7280;
    font-size: .85rem;
    margin-bottom: 24px;
    transform: translateZ(20px);
  }

  /* ==== Tabs ==== */
  .tabs-3d {
    display: flex;
    gap: 8px;
    background: #e5e7eb;
    padding: 6px;
    border-radius: 14px;
    margin-bottom: 22px;
    transform: translateZ(20px);
    box-shadow: inset 0 2px 6px rgba(0,0,0,.08);
  }
  .tabs-3d a {
    flex: 1;
    text-align: center;
    padding: 10px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: .88rem;
    color: #6b7280;
    transition: all .2s;
  }
  .tabs-3d a.active {
    background: linear-gradient(135deg, #0C1E7F, #1e40af);
    color: #fff;
    box-shadow: 0 6px 14px rgba(12,30,127,.35);
  }
  .tabs-3d.admin-mode a.active {
    background: linear-gradient(135deg, #CE1126, #a30d1e);
    box-shadow: 0 6px 14px rgba(206,17,38,.35);
  }

  /* ==== Inputs — 3D style ==== */
  .input-3d {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #d1d5db;
    border-radius: 12px;
    font-size: 1rem;
    background: #fff;
    transition: all .2s;
    box-shadow: inset 0 2px 4px rgba(0,0,0,.04);
    transform: translateZ(10px);
  }
  .input-3d:focus {
    outline: none;
    border-color: #0C1E7F;
    box-shadow:
      inset 0 2px 4px rgba(0,0,0,.04),
      0 0 0 4px rgba(12,30,127,.15);
    transform: translateZ(20px);
  }

  .label-3d {
    font-weight: 700;
    font-size: .82rem;
    color: #374151;
    margin-bottom: 6px;
    display: block;
    text-transform: uppercase;
    letter-spacing: .5px;
  }

  /* ==== Submit button — 3D push ==== */
  .btn-3d {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 14px;
    font-weight: 800;
    font-size: 1rem;
    color: #fff;
    cursor: pointer;
    transition: all .1s;
    transform: translateY(0);
    margin-top: 8px;
    letter-spacing: .5px;
  }
  .btn-3d.student {
    background: linear-gradient(135deg, #0C1E7F, #1e40af);
    box-shadow:
      0 8px 0 #050b33,
      0 14px 28px rgba(12,30,127,.5);
  }
  .btn-3d.student:hover {
    transform: translateY(-2px);
    box-shadow:
      0 10px 0 #050b33,
      0 18px 32px rgba(12,30,127,.6);
  }
  .btn-3d.student:active {
    transform: translateY(6px);
    box-shadow:
      0 2px 0 #050b33,
      0 4px 8px rgba(12,30,127,.4);
  }
  .btn-3d.admin {
    background: linear-gradient(135deg, #CE1126, #a30d1e);
    box-shadow:
      0 8px 0 #630810,
      0 14px 28px rgba(206,17,38,.5);
  }
  .btn-3d.admin:active {
    transform: translateY(6px);
    box-shadow:
      0 2px 0 #630810,
      0 4px 8px rgba(206,17,38,.4);
  }

  .small-link {
    text-align: center;
    margin-top: 18px;
    font-size: .85rem;
    color: #6b7280;
  }
  .small-link a { color: #0C1E7F; font-weight: 700; text-decoration: none; }
  .small-link a:hover { text-decoration: underline; }

  .alert-3d {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: .88rem;
    margin-bottom: 18px;
    border-left: 4px solid #CE1126;
    box-shadow: 0 4px 12px rgba(206,17,38,.15);
  }

  /* Footer brand */
  .footer-3d {
    text-align: center;
    color: rgba(255,255,255,.7);
    font-size: .78rem;
    margin-top: 20px;
    z-index: 2;
    position: relative;
  }

  @media (max-width: 576px) {
    .card-3d { padding: 30px 22px 22px; border-radius: 22px; }
    .brand-title { font-size: 1.5rem; }
  }
</style>
</head>
<body>

<!-- Floating background shapes -->
<div class="bg-shape s1"></div>
<div class="bg-shape s2"></div>
<div class="bg-shape s3"></div>

<div class="scene">
  <div class="card-3d" id="card3d">

    <!-- 3D flag -->
    <div class="flag-3d">
      <div class="f1"></div><div class="f2"></div><div class="f3"></div>
      <div class="f4"></div><div class="f5"></div>
    </div>

    <div class="brand-title">Test Me Mr 🇬🇲</div>
    <div class="brand-sub">The #1 educational web app for Gambian students</div>

    <!-- Tabs -->
    <div class="tabs-3d <?= $mode === 'admin' ? 'admin-mode' : '' ?>">
      <a href="?mode=student" class="<?= $mode !== 'admin' ? 'active' : '' ?>">👤 Student</a>
      <a href="?mode=admin"   class="<?= $mode === 'admin' ? 'active' : '' ?>">🛡️ Admin</a>
    </div>

    <?php if ($error): ?>
      <div class="alert-3d">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="mode" value="<?= e($mode) ?>">

      <div class="mb-3">
        <label class="label-3d">Full Name</label>
        <input type="text" name="name" class="input-3d"
               value="<?= e($name) ?>" placeholder="e.g. Moses Ibuchim Chuku"
               required autofocus>
      </div>

      <div class="mb-3">
        <label class="label-3d">Phone Number</label>
        <input type="text" name="phone" class="input-3d"
               value="<?= e($phone) ?>" placeholder="e.g. 7705902" required>
        <div class="small text-muted mt-1" style="font-size:.72rem;">
          ⚠️ One account can only be open on <b>one device</b> at a time.
        </div>
      </div>

      <button type="submit"
              class="btn-3d <?= $mode === 'admin' ? 'admin' : 'student' ?>">
        <?= $mode === 'admin' ? '🛡️ Admin Login' : '👤 Student Login' ?>
      </button>

      <?php if ($mode !== 'admin'): ?>
        <div class="small-link">
          New student?
          <a href="<?= SITE_URL ?>/register.php">Create an account</a>
        </div>
      <?php else: ?>
        <div class="small-link">
          <a href="<?= SITE_URL ?>/admin/register.php">Create admin</a>
        </div>
      <?php endif; ?>
    </form>

  </div>
</div>

<div class="footer-3d">
  © <?= date('Y') ?> Test Me Mr 🇬🇲 — Made for Gambian schools.
</div>

<script>
/* ============================================================
   3D TILT ON MOUSE MOVE
   ============================================================ */
(function () {
  var card = document.getElementById('card3d');
  if (!card) return;

  var scene = card.parentElement;

  scene.addEventListener('mousemove', function (e) {
    var rect = scene.getBoundingClientRect();
    var x = e.clientX - rect.left;
    var y = e.clientY - rect.top;

    var cx = rect.width / 2;
    var cy = rect.height / 2;

    var rx = ((y - cy) / cy) * -8;    /* rotateX */
    var ry = ((x - cx) / cx) * 8;     /* rotateY */

    card.style.transform = 'rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
  });

  scene.addEventListener('mouseleave', function () {
    card.style.transform = 'rotateX(0deg) rotateY(0deg)';
  });

  /* Mobile — gentle auto tilt */
  if (window.innerWidth < 768) {
    var t = 0;
    setInterval(function () {
      t += 0.02;
      var rx = Math.sin(t) * 3;
      var ry = Math.cos(t * 0.7) * 3;
      card.style.transform = 'rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
    }, 50);
  }
})();
</script>

</body>
</html>