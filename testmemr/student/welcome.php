<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

if (empty($user['grade'])) {
    redirect(SITE_URL . '/student/choose_grade.php');
}

if (!empty($_SESSION['seen_welcome'])) {
    $dest = is_subscribed($user)
        ? SITE_URL . '/student/dashboard.php'
        : SITE_URL . '/student/subscription.php';
    redirect($dest);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['seen_welcome'] = 1;
    $dest = is_subscribed($user)
        ? SITE_URL . '/student/dashboard.php'
        : SITE_URL . '/student/subscription.php';
    redirect($dest);
}

/* Grade info */
$active_grades = [];
if (!empty($user['subscribed_grades'])) {
    $active_grades = array_map('trim', explode(',', $user['subscribed_grades']));
    $active_grades = array_values(array_filter($active_grades));
}
if (($user['grade'] ?? '') === 'ALL') {
    $active_grades = CLASS_LEVELS;
}
if (empty($active_grades) && !empty($user['grade'])) {
    $active_grades = [$user['grade']];
}
$subscribed = is_subscribed($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Welcome — Test Me Mr 🇬🇲</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing: border-box; }

  body {
    margin: 0;
    overflow-x: hidden;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: radial-gradient(circle at 50% 0%, #1e3a8a 0%, #0C1E7F 40%, #050b33 100%);
    color: #fff;
    perspective: 1200px;
  }

  /* Floating background shapes */
  .bg-shape {
    position: fixed;
    border-radius: 50%;
    opacity: 0.35;
    filter: blur(2px);
    animation: float 10s ease-in-out infinite;
    z-index: 0;
    pointer-events: none;
  }
  .bg-shape.s1 { width: 380px; height: 380px; background: radial-gradient(circle at 30% 30%, #CE1126, #7a0b18); top: -120px; left: -120px; }
  .bg-shape.s2 { width: 280px; height: 280px; background: radial-gradient(circle at 30% 30%, #3A7728, #1a3a12); bottom: -100px; right: -100px; animation-delay: 3s; }
  .bg-shape.s3 { width: 180px; height: 180px; background: radial-gradient(circle at 30% 30%, #fbbf24, #b45309); top: 40%; right: 5%; animation-delay: 5s; }

  @keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50%      { transform: translateY(-40px) rotate(25deg); }
  }

  /* Hero 3D scene */
  .hero-scene {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 40px 20px;
    position: relative;
    z-index: 2;
  }

  .hero-card {
    background: linear-gradient(135deg, rgba(255,255,255,0.98), rgba(240,245,255,0.95));
    border-radius: 32px;
    padding: 50px 40px 40px;
    max-width: 560px;
    width: 100%;
    color: #0f172a;
    box-shadow:
      0 50px 100px -20px rgba(0,0,0,.6),
      0 25px 50px -10px rgba(12,30,127,.5),
      inset 0 2px 0 rgba(255,255,255,.9);
    transform-style: preserve-3d;
    transform: rotateX(2deg) rotateY(-2deg);
    transition: transform .3s ease;
    position: relative;
    border: 1px solid rgba(255,255,255,.7);
    animation: enterScene 1s ease-out;
  }

  @keyframes enterScene {
    from { opacity: 0; transform: rotateX(-20deg) rotateY(20deg) translateY(60px); }
    to   { opacity: 1; transform: rotateX(2deg) rotateY(-2deg) translateY(0); }
  }

  /* Flag 3D */
  .flag-3d {
    width: 100px;
    height: 72px;
    margin: 0 auto 22px;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow:
      0 20px 40px rgba(0,0,0,.4),
      0 8px 16px rgba(0,0,0,.25),
      inset 0 2px 4px rgba(255,255,255,.5);
    transform: translateZ(50px);
    transition: transform .3s ease;
  }
  .flag-3d:hover { transform: translateZ(80px) scale(1.05) rotateY(-15deg); }
  .flag-3d div { width: 100%; }
  .flag-3d .f1 { height: 17px; background: #CE1126; }
  .flag-3d .f2 { height: 6px;  background: #fff; }
  .flag-3d .f3 { height: 17px; background: #0C1E7F; }
  .flag-3d .f4 { height: 6px;  background: #fff; }
  .flag-3d .f5 { height: 17px; background: #3A7728; }

  .hero-title {
    text-align: center;
    font-weight: 900;
    font-size: 2.4rem;
    color: #0C1E7F;
    letter-spacing: -1px;
    margin-bottom: 10px;
    transform: translateZ(30px);
  }
  .hero-badge {
    text-align: center;
    display: block;
    background: linear-gradient(135deg, #0C1E7F, #1e40af);
    color: #fff;
    padding: 6px 18px;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: 1px;
    display: inline-block;
    margin: 0 auto 18px;
    box-shadow: 0 8px 20px rgba(12,30,127,.4);
  }
  .hero-badge-wrap { text-align: center; }

  .hero-welcome {
    text-align: center;
    color: #4b5563;
    margin-bottom: 26px;
    font-size: .95rem;
    transform: translateZ(20px);
  }

  /* 3D section cards */
  .section-3d {
    background: rgba(255,255,255,.9);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow:
      0 15px 30px rgba(12,30,127,.12),
      0 4px 8px rgba(12,30,127,.08),
      inset 0 1px 0 rgba(255,255,255,.9);
    transform-style: preserve-3d;
    transform: translateZ(15px);
    transition: transform .25s ease, box-shadow .25s ease;
    border: 1px solid rgba(12,30,127,.08);
  }
  .section-3d:hover {
    transform: translateZ(30px) translateY(-3px);
    box-shadow:
      0 25px 50px rgba(12,30,127,.2),
      0 8px 16px rgba(12,30,127,.12);
  }

  .section-title {
    font-weight: 900;
    color: #0C1E7F;
    font-size: 1.05rem;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  /* 3D Grade/Subject chips */
  .chip-3d {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 700;
    font-size: .8rem;
    margin: 4px;
    transition: all .2s;
    cursor: default;
    box-shadow: 0 4px 8px rgba(0,0,0,.1);
  }
  .chip-3d:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 8px 16px rgba(0,0,0,.2);
  }
  .chip-grade   { background: linear-gradient(135deg,#eff6ff,#dbeafe); color: #0C1E7F; border: 2px solid #0C1E7F; }
  .chip-subject { background: linear-gradient(135deg,#f0fdf4,#dcfce7); color: #14532d; border: 2px solid #3A7728; }

  /* Feature cards */
  .feature-3d {
    background: #fff;
    border-radius: 16px;
    padding: 18px;
    height: 100%;
    box-shadow:
      0 10px 20px rgba(12,30,127,.1),
      0 3px 6px rgba(12,30,127,.06);
    transition: all .25s;
    border: 1px solid rgba(12,30,127,.06);
  }
  .feature-3d:hover {
    transform: translateY(-6px) rotateX(4deg);
    box-shadow:
      0 20px 40px rgba(12,30,127,.2),
      0 6px 12px rgba(12,30,127,.1);
  }
  .feature-icon-3d {
    font-size: 2rem;
    margin-bottom: 8px;
    display: inline-block;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,.15));
  }
  .feature-3d h6 {
    font-weight: 800;
    color: #0C1E7F;
    font-size: .88rem;
    margin-bottom: 6px;
  }
  .feature-3d p {
    font-size: .78rem;
    color: #4b5563;
    margin: 0;
  }

  /* Pricing 3D */
  .price-3d {
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 18px;
    padding: 22px 16px;
    text-align: center;
    height: 100%;
    box-shadow: 0 10px 22px rgba(12,30,127,.1);
    transition: all .25s;
  }
  .price-3d:hover {
    transform: translateY(-6px) scale(1.03);
    border-color: #0C1E7F;
    box-shadow: 0 20px 40px rgba(12,30,127,.25);
  }
  .price-3d.best {
    border: 3px solid #d97706;
    background: linear-gradient(135deg,#fff,#fef9c3);
    position: relative;
  }
  .price-3d.best::before {
    content: "⭐ BEST";
    position: absolute;
    top: -12px; left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg,#d97706,#b45309);
    color: #fff;
    font-size: .65rem;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 20px;
    box-shadow: 0 4px 8px rgba(217,119,6,.4);
  }
  .price-amt {
    font-size: 2.4rem;
    font-weight: 900;
    color: #0C1E7F;
    line-height: 1;
    text-shadow: 0 2px 4px rgba(12,30,127,.15);
  }
  .price-per {
    font-weight: 800;
    color: #0f172a;
    margin-top: 6px;
    font-size: .95rem;
  }
  .price-desc {
    font-size: .72rem;
    color: #6b7280;
    margin-top: 4px;
  }

  /* Steps */
  .step-3d {
    display: flex;
    gap: 14px;
    padding: 16px;
    background: linear-gradient(135deg,#f8faff,#eff6ff);
    border-radius: 14px;
    margin-bottom: 12px;
    align-items: flex-start;
    box-shadow: 0 4px 10px rgba(12,30,127,.06);
    transition: all .2s;
  }
  .step-3d:hover {
    transform: translateX(6px);
    box-shadow: 0 8px 20px rgba(12,30,127,.12);
  }
  .step-num-3d {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    color: #fff;
    font-size: 1rem;
    box-shadow: 0 6px 12px rgba(0,0,0,.2);
  }

  /* CTA 3D button */
  .cta-3d {
    width: 100%;
    padding: 20px;
    border: none;
    border-radius: 16px;
    font-weight: 900;
    font-size: 1.1rem;
    color: #fff;
    cursor: pointer;
    letter-spacing: .5px;
    transition: all .1s;
    transform: translateY(0);
    background: linear-gradient(135deg, #3A7728, #16a34a);
    box-shadow:
      0 10px 0 #1a3a12,
      0 20px 40px rgba(58,119,40,.5);
  }
  .cta-3d:hover { transform: translateY(-3px); box-shadow: 0 13px 0 #1a3a12, 0 24px 48px rgba(58,119,40,.6); }
  .cta-3d:active { transform: translateY(6px); box-shadow: 0 4px 0 #1a3a12, 0 8px 16px rgba(58,119,40,.4); }
  .cta-3d.orange {
    background: linear-gradient(135deg, #d97706, #b45309);
    box-shadow: 0 10px 0 #5c2b04, 0 20px 40px rgba(217,119,6,.5);
  }
  .cta-3d.orange:hover { box-shadow: 0 13px 0 #5c2b04, 0 24px 48px rgba(217,119,6,.6); }
  .cta-3d.orange:active { box-shadow: 0 4px 0 #5c2b04, 0 8px 16px rgba(217,119,6,.4); }

  @media (max-width: 576px) {
    .hero-card { padding: 34px 22px 28px; border-radius: 22px; }
    .hero-title { font-size: 1.8rem; }
    .price-amt { font-size: 1.9rem; }
  }
</style>
</head>
<body>

<div class="bg-shape s1"></div>
<div class="bg-shape s2"></div>
<div class="bg-shape s3"></div>

<div class="hero-scene">
  <div class="hero-card" id="heroCard">

    <!-- Flag -->
    <div class="flag-3d">
      <div class="f1"></div><div class="f2"></div><div class="f3"></div>
      <div class="f4"></div><div class="f5"></div>
    </div>

    <h1 class="hero-title">Test Me Mr 🇬🇲</h1>

    <div class="hero-badge-wrap">
      <div class="hero-badge">#1 EDUCATIONAL WEB APP FOR GAMBIAN SCHOOLS</div>
    </div>

    <p class="hero-welcome">
      Welcome, <b><?= e($user['name']) ?></b> 🎉<br>
      The first and best digital learning platform for <b>Gambian school students</b>.
    </p>

    <!-- WHAT IS IT -->
    <div class="section-3d">
      <div class="section-title">📖 What is Test Me Mr?</div>
      <p style="font-size:.9rem;color:#4b5563;margin-bottom:10px;">
        <b>Test Me Mr</b> is the <b>first</b> and <b>best</b> educational web app created
        specifically for students in <b>The Gambia</b>. Whether you are preparing for the
        <b>NAT (National Assessment Test)</b> for Grade 3, 6 or 8, or the <b>GABECE</b>
        exam for Grade 9, you get thousands of real practice questions — right on your phone.
      </p>
      <p style="font-size:.9rem;color:#4b5563;margin:0;">
        No textbooks to carry. No expensive classes. Just open the app, pick a subject,
        and start practising. <b>Instant scores. Letter grades. See what you missed.</b>
      </p>
    </div>

    <!-- WHAT YOU GET -->
    <div class="section-3d">
      <div class="section-title">🎓 What you get</div>

      <div style="font-size:.72rem;color:#6b7280;font-weight:700;letter-spacing:.5px;margin-bottom:6px;">📚 GRADES</div>
      <div class="mb-3">
        <span class="chip-3d chip-grade">🎒 NAT Grade 3</span>
        <span class="chip-3d chip-grade">📘 NAT Grade 6</span>
        <span class="chip-3d chip-grade">🎓 NAT Grade 8</span>
        <span class="chip-3d chip-grade">🏆 GABECE</span>
      </div>

      <div style="font-size:.72rem;color:#6b7280;font-weight:700;letter-spacing:.5px;margin-bottom:6px;">📖 SUBJECTS</div>
      <div class="mb-3">
        <span class="chip-3d chip-subject">➗ Maths</span>
        <span class="chip-3d chip-subject">📝 English</span>
        <span class="chip-3d chip-subject">🔬 Science</span>
        <span class="chip-3d chip-subject">🌍 SES</span>
      </div>

      <div style="font-size:.82rem;color:#0C1E7F;font-weight:800;">
        ⚡ 6,000+ real exam questions
      </div>
    </div>

    <!-- FEATURES -->
    <div class="section-3d">
      <div class="section-title">✨ Why Test Me Mr is the best</div>
      <div class="row g-2">
        <div class="col-6"><div class="feature-3d"><div class="feature-icon-3d">⚡</div><h6>Instant Results</h6><p>Score, mark &amp; grade in seconds.</p></div></div>
        <div class="col-6"><div class="feature-3d"><div class="feature-icon-3d">🎯</div><h6>A+ to F</h6><p>Know where you stand before the real exam.</p></div></div>
        <div class="col-6"><div class="feature-3d"><div class="feature-icon-3d">⏱️</div><h6>Optional Timer</h6><p>5–25 minutes or no timer at all.</p></div></div>
        <div class="col-6"><div class="feature-3d"><div class="feature-icon-3d">🔁</div><h6>Unlimited Retakes</h6><p>Fresh random questions every time.</p></div></div>
      </div>
    </div>

    <!-- PRICING -->
    <div class="section-3d">
      <div class="section-title">💰 Simple Pricing — One Grade</div>
      <div class="row g-2">
        <div class="col-4"><div class="price-3d"><div class="price-amt">D25</div><div class="price-per">1 Day</div><div class="price-desc">All subjects · 1 grade</div></div></div>
        <div class="col-4"><div class="price-3d"><div class="price-amt">D75</div><div class="price-per">1 Week</div><div class="price-desc">All subjects · 1 grade</div></div></div>
        <div class="col-4"><div class="price-3d best"><div class="price-amt">D225</div><div class="price-per">1 Month</div><div class="price-desc">All subjects · 1 grade</div></div></div>
      </div>
    </div>

    <!-- HOW TO USE -->
    <div class="section-3d">
      <div class="section-title">🚀 How to use Test Me Mr</div>

      <div class="step-3d">
        <div class="step-num-3d" style="background:linear-gradient(135deg,#0C1E7F,#1e40af)">1</div>
        <div>
          <div style="font-weight:800;color:#0f172a;font-size:.88rem;">Subscribe to a Grade</div>
          <div style="font-size:.78rem;color:#6b7280;margin-top:2px;">Pick your grade and choose D25 / D75 / D225.</div>
        </div>
      </div>

      <div class="step-3d">
        <div class="step-num-3d" style="background:linear-gradient(135deg,#d97706,#b45309)">2</div>
        <div>
          <div style="font-weight:800;color:#0f172a;font-size:.88rem;">Pay with Wave, Afrimoney or QMoney</div>
          <div style="font-size:.78rem;color:#6b7280;margin-top:2px;">Send payment, upload screenshot, wait for admin approval.</div>
        </div>
      </div>

      <div class="step-3d">
        <div class="step-num-3d" style="background:linear-gradient(135deg,#3A7728,#16a34a)">3</div>
        <div>
          <div style="font-weight:800;color:#0f172a;font-size:.88rem;">Write a Test</div>
          <div style="font-size:.78rem;color:#6b7280;margin-top:2px;">Pick subject, number of questions (5–50) and optional timer.</div>
        </div>
      </div>

      <div class="step-3d">
        <div class="step-num-3d" style="background:linear-gradient(135deg,#CE1126,#a30d1e)">4</div>
        <div>
          <div style="font-weight:800;color:#0f172a;font-size:.88rem;">See Your Grade &amp; Keep Practising</div>
          <div style="font-size:.78rem;color:#6b7280;margin-top:2px;">Get score, %, letter grade — and retake to improve!</div>
        </div>
      </div>
    </div>

    <!-- STATUS -->
    <?php if (!empty($active_grades) || $subscribed): ?>
      <div class="section-3d" style="background:linear-gradient(135deg,#eff6ff,#f0fdf4)">
        <div class="section-title">📊 Your Status</div>
        <div class="row g-3">
          <div class="col-4">
            <div style="font-size:.7rem;color:#6b7280;">Subscription</div>
            <div style="font-weight:800;color:<?= $subscribed ? '#3A7728' : '#CE1126' ?>;font-size:.9rem;">
              <?= $subscribed ? '✅ Active' : '❌ Not active' ?>
            </div>
          </div>
          <?php if (!empty($active_grades)): ?>
            <div class="col-4">
              <div style="font-size:.7rem;color:#6b7280;">Grade(s)</div>
              <div style="font-weight:800;color:#0C1E7F;font-size:.85rem;">
                <?= implode(' · ', array_map('e', $active_grades)) ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if ($subscribed): ?>
            <div class="col-4">
              <div style="font-size:.7rem;color:#6b7280;">Days Left</div>
              <div style="font-weight:800;color:#d97706;font-size:.9rem;"><?= sub_days_left($user) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- CTA -->
    <form method="post" style="margin-top:8px;">
      <?php if ($subscribed): ?>
        <button type="submit" class="cta-3d">🚀 Start Learning Now</button>
      <?php else: ?>
        <button type="submit" class="cta-3d orange">💳 Subscribe Now to Start</button>
      <?php endif; ?>
    </form>

    <div style="text-align:center;color:#6b7280;font-size:.75rem;margin-top:18px;">
      Made with ❤️ for Gambian students 🇬🇲
    </div>

  </div>
</div>

<script>
/* ============================================================
   3D TILT ON MOUSE MOVE (desktop)
   ============================================================ */
(function () {
  var card = document.getElementById('heroCard');
  if (!card) return;

  var baseRX = 2, baseRY = -2;
  var rect = null;

  document.addEventListener('mousemove', function (e) {
    rect = card.getBoundingClientRect();
    var x = e.clientX - rect.left;
    var y = e.clientY - rect.top;

    if (x < -200 || y < -200 || x > rect.width + 200 || y > rect.height + 200) return;

    var rx = baseRX + ((y - rect.height / 2) / rect.height) * -6;
    var ry = baseRY + ((x - rect.width / 2) / rect.width) * 6;

    card.style.transform = 'rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
  });

  /* Mobile gentle auto-tilt */
  if (window.innerWidth < 768) {
    var t = 0;
    setInterval(function () {
      t += 0.02;
      var rx = baseRX + Math.sin(t) * 3;
      var ry = baseRY + Math.cos(t * 0.7) * 3;
      card.style.transform = 'rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
    }, 60);
  }
})();
</script>

</body>
</html>