<?php
require_once __DIR__ . '/../includes/db.php';

/* Admin check */
if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . '/login.php?admin=1');
    exit;
}

/* Gather stats */
try {
    $counts = [
        'students'  => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'subjects'  => (int)$pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
        'questions' => (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn(),
        'pending'   => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn(),
    ];
} catch (Throwable $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<style>
  .admin-banner {
    background: linear-gradient(135deg, #CE1126 0%, #0C1E7F 50%, #3A7728 100%);
    color: #fff;
    border-radius: 20px;
    padding: 28px 22px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(12,30,127,0.25);
    margin-bottom: 22px;
  }
  .admin-banner::before {
    content: "";
    position: absolute;
    top: -50px; right: -50px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
  }
  .admin-banner h2 { margin: 0; position: relative; z-index: 2; font-weight: 800; }
  .admin-banner p  { margin: 6px 0 0; position: relative; z-index: 2; opacity: 0.92; font-size: 0.92rem; }

  .stat-card {
    border-radius: 16px;
    padding: 18px;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    transition: transform 0.15s;
  }
  .stat-card:hover { transform: translateY(-3px); }
  .stat-card .num { font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: 4px; }
  .stat-card .lbl { font-size: 0.8rem; opacity: 0.9; font-weight: 600; text-transform: uppercase; }
  .stat-card .ico { position: absolute; right: 12px; top: 10px; font-size: 2rem; opacity: 0.25; }
  .sc-blue   { background: linear-gradient(135deg, #0C1E7F, #1e40af); }
  .sc-red    { background: linear-gradient(135deg, #CE1126, #f43f5e); }
  .sc-green  { background: linear-gradient(135deg, #3A7728, #16a34a); }
  .sc-gold   { background: linear-gradient(135deg, #d97706, #facc15); }

  .admin-action {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #fff;
    color: #0f172a;
    border-radius: 14px;
    padding: 16px 18px;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(0,0,0,0.05);
    border-left: 5px solid #0C1E7F;
    transition: transform 0.15s, box-shadow 0.15s;
    margin-bottom: 12px;
  }
  .admin-action:hover {
    color: #0C1E7F;
    transform: translateX(4px);
    box-shadow: 0 8px 20px rgba(12,30,127,0.12);
  }
  .admin-action .a-ico {
    font-size: 1.6rem;
    background: #f1f5f9;
    width: 46px; height: 46px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 12px;
    flex-shrink: 0;
  }
  .admin-action .a-info { flex: 1; }
  .admin-action .a-info .a-title { font-size: 1rem; font-weight: 700; }
  .admin-action .a-info .a-sub { font-size: 0.78rem; color: #6b7280; }
  .aa-red    { border-left-color: #CE1126; }
  .aa-blue   { border-left-color: #0C1E7F; }
  .aa-green  { border-left-color: #3A7728; }
  .aa-gold   { border-left-color: #d97706; }
  .aa-purple { border-left-color: #7c3aed; }
</style>

<div class="admin-banner">
  <h2>Admin Dashboard 🇬🇲</h2>
  <p>Manage Test Me Mr — students, subjects, questions, payments</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card sc-blue">
      <span class="ico">👥</span>
      <div class="num"><?= $counts['students'] ?></div>
      <div class="lbl">Students</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card sc-green">
      <span class="ico">📚</span>
      <div class="num"><?= $counts['subjects'] ?></div>
      <div class="lbl">Subjects</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card sc-red">
      <span class="ico">📝</span>
      <div class="num"><?= $counts['questions'] ?></div>
      <div class="lbl">Questions</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card sc-gold">
      <span class="ico">💳</span>
      <div class="num"><?= $counts['pending'] ?></div>
      <div class="lbl">Pending Pay</div>
    </div>
  </div>
</div>

<h5 class="mb-3">⚡ Quick Actions</h5>

<a href="<?= SITE_URL ?>/admin/subjects.php" class="admin-action aa-blue">
  <div class="a-ico">📚</div>
  <div class="a-info">
    <div class="a-title">Manage Subjects</div>
    <div class="a-sub">Add or delete subjects per grade</div>
  </div>
  <div>→</div>
</a>

<a href="<?= SITE_URL ?>/admin/questions.php" class="admin-action aa-red">
  <div class="a-ico">📝</div>
  <div class="a-info">
    <div class="a-title">Manage Questions</div>
    <div class="a-sub">Add, edit, delete, or bulk CSV upload</div>
  </div>
  <div>→</div>
</a>

<a href="<?= SITE_URL ?>/admin/import_questions.php" class="admin-action aa-purple">
  <div class="a-ico">⚡</div>
  <div class="a-info">
    <div class="a-title">Auto-Import Questions</div>
    <div class="a-sub">Bulk upload CSV safely</div>
  </div>
  <div>→</div>
</a>

<a href="<?= SITE_URL ?>/admin/g8_paste.php" class="admin-action aa-purple">
  <div class="a-ico">📋</div>
  <div class="a-info">
    <div class="a-title">Paste Questions (G8 + GABECE)</div>
    <div class="a-sub">Paste CSV content directly — no files needed</div>
  </div>
  <div>→</div>
</a>

<a href="<?= SITE_URL ?>/admin/students.php" class="admin-action aa-green">
  <div class="a-ico">👥</div>
  <div class="a-info">
    <div class="a-title">View Students</div>
    <div class="a-sub">See all registered students</div>
  </div>
  <div>→</div>
</a>

<a href="<?= SITE_URL ?>/admin/payments.php" class="admin-action aa-gold">
  <div class="a-ico">💳</div>
  <div class="a-info">
    <div class="a-title">Approve Payments</div>
    <div class="a-sub">Confirm subscriptions</div>
  </div>
  <div>→</div>
</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>