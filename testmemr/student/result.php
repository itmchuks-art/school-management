<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

$st = $pdo->prepare('SELECT a.*, s.name AS subject_name FROM attempts a
                     LEFT JOIN subjects s ON s.id=a.subject_id
                     WHERE a.user_id=? ORDER BY a.id DESC');
$st->execute([(int)$user['id']]);
$rows = $st->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
  .result-banner {
    background: linear-gradient(135deg, #CE1126 0%, #0C1E7F 50%, #3A7728 100%);
    color: #fff;
    border-radius: 18px;
    padding: 22px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
  }
  .result-banner h3 { margin: 0; font-weight: 800; position: relative; z-index: 2; }
  .result-banner::after {
    content: "";
    position: absolute;
    right: -40px; top: -40px;
    width: 140px; height: 140px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
  }
  .result-card {
    background: #fff;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.05);
    border-left: 5px solid #0C1E7F;
  }
  .result-card.pass { border-left-color: #3A7728; }
  .result-card.fail { border-left-color: #CE1126; }
  .result-card .score-circle {
    width: 56px; height: 56px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800;
    color: #fff;
    font-size: 0.9rem;
    flex-shrink: 0;
  }
  .result-card.pass .score-circle { background: linear-gradient(135deg, #3A7728, #16a34a); }
  .result-card.fail .score-circle { background: linear-gradient(135deg, #CE1126, #f43f5e); }
  .result-card .info { flex: 1; }
  .result-card .info .subject { font-weight: 700; font-size: 1rem; }
  .result-card .info .date { font-size: 0.8rem; color: #6b7280; }
  .result-card .pct { font-weight: 800; font-size: 1.3rem; color: #0C1E7F; }
  .result-card.pass .pct { color: #3A7728; }
  .result-card.fail .pct { color: #CE1126; }
</style>

<div class="result-banner">
  <h3>📊 My Results 🇬🇲</h3>
</div>

<?php if (!$rows): ?>
  <div class="alert alert-light border text-muted">
    No attempts yet. <a href="subjects.php">Take your first test →</a>
  </div>
<?php else: ?>
  <?php foreach ($rows as $r):
      $pct = $r['total'] ? round($r['score'] / $r['total'] * 100) : 0;
      $cls = $pct >= 50 ? 'pass' : 'fail';
  ?>
    <div class="result-card <?= $cls ?>">
      <div class="score-circle"><?= (int)$r['score'] ?>/<?= (int)$r['total'] ?></div>
      <div class="info">
        <div class="subject"><?= e($r['subject_name'] ?? '—') ?></div>
        <div class="date"><?= e(fmt_date($r['created_at'])) ?></div>
      </div>
      <div class="pct"><?= $pct ?>%</div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>