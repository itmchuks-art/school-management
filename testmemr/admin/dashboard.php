<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/login.php');
}

$total_students  = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_pending   = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
$total_paid      = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='approved'")->fetchColumn();
$active_subs     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE subscription_end > NOW()")->fetchColumn();
$total_questions = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
$total_subjects  = (int)$pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  .blob-3{width:180px;height:180px;background:radial-gradient(circle at 30% 30%,#fbbf24,#b45309);top:45%;right:5%;animation-delay:5s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:26px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;transition:transform .25s,box-shadow .25s;}
  .glass:hover{box-shadow:0 40px 80px -20px rgba(0,0,0,.55),0 18px 36px -10px rgba(12,30,127,.5);}

  .hero-3d{background:linear-gradient(135deg,#CE1126 0%,#0C1E7F 50%,#3A7728 100%);border-radius:28px;padding:32px 28px;position:relative;overflow:hidden;box-shadow:0 40px 80px -20px rgba(12,30,127,.6),0 20px 40px -10px rgba(206,17,38,.4);color:#fff;}
  .hero-3d::before{content:"";position:absolute;top:-80px;right:-80px;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.1);}
  .hero-3d::after{content:"";position:absolute;bottom:-100px;left:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.07);}
  .hero-3d h2{font-weight:900;font-size:1.8rem;position:relative;z-index:2;margin:0;letter-spacing:-.5px;}
  .hero-3d p{position:relative;z-index:2;opacity:.9;margin:4px 0 0;font-size:.9rem;}

  .stat-3d{background:linear-gradient(135deg,#fff,#f8faff);border-radius:20px;padding:20px 18px;box-shadow:0 15px 30px -10px rgba(12,30,127,.25),0 4px 8px rgba(12,30,127,.1),inset 0 1px 0 rgba(255,255,255,.9);transition:transform .25s,box-shadow .25s;border-left:5px solid;color:#0f172a;}
  .stat-3d:hover{transform:translateY(-6px) scale(1.02);box-shadow:0 28px 56px -15px rgba(12,30,127,.35);}
  .stat-3d.blue{border-left-color:#0C1E7F;}
  .stat-3d.green{border-left-color:#3A7728;}
  .stat-3d.gold{border-left-color:#d97706;}
  .stat-3d.red{border-left-color:#CE1126;}
  .stat-label{font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;font-weight:800;}
  .stat-value{font-size:2rem;font-weight:900;line-height:1;margin-top:6px;}
  .stat-value.blue{color:#0C1E7F;}
  .stat-value.green{color:#3A7728;}
  .stat-value.gold{color:#d97706;}
  .stat-value.red{color:#CE1126;}

  .action-card-3d{display:block;padding:24px 18px;border-radius:20px;text-decoration:none;color:#0f172a;height:100%;transition:transform .25s,box-shadow .25s;box-shadow:0 15px 30px -10px rgba(12,30,127,.25),0 4px 8px rgba(12,30,127,.1),inset 0 1px 0 rgba(255,255,255,.9);border:3px solid;position:relative;overflow:hidden;}
  .action-card-3d::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,transparent 0%,rgba(255,255,255,.3) 100%);opacity:0;transition:opacity .3s;}
  .action-card-3d:hover{transform:translateY(-8px) scale(1.03) rotateX(3deg);box-shadow:0 35px 60px -15px rgba(12,30,127,.4),0 12px 24px rgba(12,30,127,.25);}
  .action-card-3d:hover::before{opacity:1;}
  .action-card-3d .ico{font-size:2.4rem;display:block;margin-bottom:10px;filter:drop-shadow(0 4px 8px rgba(0,0,0,.15));}
  .action-card-3d .title{font-weight:900;font-size:1rem;color:#0C1E7F;margin-bottom:4px;}
  .action-card-3d .sub{font-size:.78rem;color:#6b7280;line-height:1.35;}
  .action-card-3d.border-blue{border-color:#0C1E7F;background:linear-gradient(135deg,#fff,#eff6ff);}
  .action-card-3d.border-red{border-color:#CE1126;background:linear-gradient(135deg,#fff,#fff1f2);}
  .action-card-3d.border-green{border-color:#3A7728;background:linear-gradient(135deg,#fff,#f0fdf4);}
  .action-card-3d.border-gold{border-color:#d97706;background:linear-gradient(135deg,#fff,#fffbeb);}
  .action-card-3d.border-gray{border-color:#6b7280;background:linear-gradient(135deg,#fff,#f8fafc);}

  .badge-3d{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.68rem;font-weight:800;box-shadow:0 4px 8px rgba(0,0,0,.15);}
  .badge-3d.warn{background:linear-gradient(135deg,#d97706,#b45309);color:#fff;}
  .badge-3d.info{background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;}
  .badge-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);color:#fff;}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>
<div class="bg-blob blob-3"></div>

<div class="container py-4" style="max-width:1100px;position:relative;z-index:2;">

  <!-- HERO -->
  <div class="hero-3d mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h2>🛡️ Admin Dashboard</h2>
        <p>Welcome back, <b><?= e($_SESSION['admin_name'] ?? 'Admin') ?></b></p>
      </div>
      <a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-outline-light btn-sm">🚪 Logout</a>
    </div>
  </div>

  <!-- STATS ROW 1 -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="stat-3d blue">
        <div class="stat-label">👥 Students</div>
        <div class="stat-value blue"><?= $total_students ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-3d green">
        <div class="stat-label">✅ Active Subs</div>
        <div class="stat-value green"><?= $active_subs ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-3d gold">
        <div class="stat-label">⏳ Pending</div>
        <div class="stat-value gold"><?= $total_pending ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-3d red">
        <div class="stat-label">💳 Approved</div>
        <div class="stat-value red"><?= $total_paid ?></div>
      </div>
    </div>
  </div>

  <!-- STATS ROW 2 -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-3d blue">
        <div class="stat-label">❓ Questions</div>
        <div class="stat-value blue"><?= number_format($total_questions) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-3d green">
        <div class="stat-label">📚 Subjects</div>
        <div class="stat-value green"><?= $total_subjects ?></div>
      </div>
    </div>
  </div>

  <!-- QUICK ACTIONS -->
  <h5 class="fw-bold mb-3 text-white">⚡ Quick Actions</h5>

  <div class="row g-3">

    <div class="col-6 col-md-4">
      <a href="<?= SITE_URL ?>/admin/payments.php" class="action-card-3d border-green">
        <span class="ico">💳</span>
        <div class="title">Payments</div>
        <div class="sub">Approve Wave/Afrimoney/QMoney payments.</div>
        <?php if ($total_pending > 0): ?>
          <span class="badge-3d warn mt-2"><?= $total_pending ?> pending</span>
        <?php endif; ?>
      </a>
    </div>

    <div class="col-6 col-md-4">
      <a href="<?= SITE_URL ?>/admin/students.php" class="action-card-3d border-blue">
        <span class="ico">👥</span>
        <div class="title">Students</div>
        <div class="sub">View, cancel, or delete students.</div>
      </a>
    </div>

    <div class="col-6 col-md-4">
      <a href="<?= SITE_URL ?>/admin/subjects.php" class="action-card-3d border-red">
        <span class="ico">📚</span>
        <div class="title">Subjects</div>
        <div class="sub">Add or edit subjects students can unlock.</div>
      </a>
    </div>

    <div class="col-6 col-md-4">
      <a href="<?= SITE_URL ?>/admin/questions.php" class="action-card-3d border-gold">
        <span class="ico">❓</span>
        <div class="title">All Questions</div>
        <div class="sub">Browse, filter, import and delete.</div>
        <span class="badge-3d info mt-2"><?= number_format($total_questions) ?> total</span>
      </a>
    </div>

    <div class="col-6 col-md-4">
      <a href="<?= SITE_URL ?>/admin/add_question.php" class="action-card-3d border-green">
        <span class="ico">➕</span>
        <div class="title">Add Question</div>
        <div class="sub">Post one question to multiple grades at once.</div>
      </a>
    </div>

    <div class="col-6 col-md-4">
      <a href="<?= SITE_URL ?>/admin/fix_subjects.php" class="action-card-3d border-gold">
        <span class="ico">🔧</span>
        <div class="title">Fix Subjects</div>
        <div class="sub">Move questions to the correct subject.</div>
      </a>
    </div>

    <div class="col-6 col-md-4">
      <a href="<?= SITE_URL ?>/admin/logout.php" class="action-card-3d border-red">
        <span class="ico">🚪</span>
        <div class="title">Logout</div>
        <div class="sub">End your admin session.</div>
      </a>
    </div>

  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>