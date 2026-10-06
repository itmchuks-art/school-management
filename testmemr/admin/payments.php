<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $action     = $_POST['action'] ?? '';
    $admin_name = $_SESSION['admin_name'] ?? 'Admin';

    if ($payment_id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $st = $pdo->prepare('SELECT * FROM payments WHERE id = ? LIMIT 1');
        $st->execute([$payment_id]);
        $payment = $st->fetch();

        if (!$payment) { flash('Payment not found.', 'danger'); redirect(SITE_URL . '/admin/payments.php'); }

        if ($action === 'reject') {
            $pdo->prepare('UPDATE payments SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?')
                ->execute(['rejected', $admin_name, $payment_id]);
            flash('❌ Payment #' . $payment_id . ' rejected by ' . $admin_name . '.', 'warning');
            redirect(SITE_URL . '/admin/payments.php');
        }

        $plan = PLANS[$payment['plan']] ?? null;
        if (!$plan) { flash('Unknown plan.', 'danger'); redirect(SITE_URL . '/admin/payments.php'); }

        $user_st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $user_st->execute([(int)$payment['user_id']]);
        $u = $user_st->fetch();

        if (!$u) { flash('User not found.', 'danger'); redirect(SITE_URL . '/admin/payments.php'); }

        $base = (!empty($u['subscription_end']) && strtotime($u['subscription_end']) > time()) ? strtotime($u['subscription_end']) : time();
        $new_end = date('Y-m-d H:i:s', $base + ($plan['days'] * 86400));

        $existing_grades = [];
        if (!empty($u['subscribed_grades'])) {
            $existing_grades = array_map('trim', explode(',', $u['subscribed_grades']));
            $existing_grades = array_values(array_filter($existing_grades));
        }
        $new_grades = [];
        if (!empty($payment['subscribed_grades'])) {
            $new_grades = array_map('trim', explode(',', $payment['subscribed_grades']));
            $new_grades = array_values(array_filter($new_grades));
        }

        $all_grades = ($plan['type'] === 'all') ? CLASS_LEVELS : array_values(array_unique(array_merge($existing_grades, $new_grades)));
        $final_grades = implode(',', $all_grades);

        $pdo->prepare('UPDATE users SET subscription_plan = ?, subscription_end = ?, subscribed_grades = ? WHERE id = ?')
            ->execute([$payment['plan'], $new_end, $final_grades ?: null, (int)$u['id']]);

        $pdo->prepare('UPDATE payments SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?')
            ->execute(['approved', $admin_name, $payment_id]);

        flash('✅ Payment #' . $payment_id . ' approved by ' . $admin_name . '. Grades: ' . $final_grades);
        redirect(SITE_URL . '/admin/payments.php');
    }
}

/* Filter */
$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['all','pending','approved','rejected'], true)) $filter = 'pending';

/* Pagination */
$per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));

/* Build WHERE */
$where_sql = '';
$params = [];
if ($filter !== 'all') {
    $where_sql = ' WHERE p.status = ?';
    $params[] = $filter;
}

/* Count */
$count_sql = 'SELECT COUNT(*) FROM payments p' . $where_sql;
$st = $pdo->prepare($count_sql);
$st->execute($params);
$filtered_count = (int)$st->fetchColumn();

$total_pages = max(1, (int)ceil($filtered_count / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

/* Fetch */
$sql = 'SELECT p.*, u.name AS user_name, u.phone AS user_phone FROM payments p LEFT JOIN users u ON u.id = p.user_id'
     . $where_sql . " ORDER BY p.id DESC LIMIT $per_page OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$payments = $st->fetchAll();

$counts = [
    'pending'  => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn(),
    'approved' => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='approved'")->fetchColumn(),
    'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='rejected'")->fetchColumn(),
];

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:26px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .filter-tab{padding:10px 20px;border-radius:14px;text-decoration:none;font-weight:800;font-size:.85rem;margin-right:8px;margin-bottom:8px;display:inline-block;transition:all .25s;border:2px solid;box-shadow:0 6px 14px rgba(0,0,0,.15);}
  .filter-tab:hover{transform:translateY(-3px);}
  .filter-tab.pending{color:#d97706;border-color:#d97706;background:#fff;}
  .filter-tab.pending.active{background:linear-gradient(135deg,#d97706,#b45309);color:#fff;box-shadow:0 8px 0 #5c2b04,0 12px 24px rgba(217,119,6,.35);}
  .filter-tab.approved{color:#3A7728;border-color:#3A7728;background:#fff;}
  .filter-tab.approved.active{background:linear-gradient(135deg,#3A7728,#16a34a);color:#fff;box-shadow:0 8px 0 #1a3a12,0 12px 24px rgba(58,119,40,.35);}
  .filter-tab.rejected{color:#CE1126;border-color:#CE1126;background:#fff;}
  .filter-tab.rejected.active{background:linear-gradient(135deg,#CE1126,#a30d1e);color:#fff;box-shadow:0 8px 0 #630810,0 12px 24px rgba(206,17,38,.35);}
  .filter-tab.all{color:#0C1E7F;border-color:#0C1E7F;background:#fff;}
  .filter-tab.all.active{background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;box-shadow:0 8px 0 #050b33,0 12px 24px rgba(12,30,127,.35);}

  .pay-row{background:linear-gradient(135deg,#fff,#f8faff);border-radius:18px;padding:18px;margin-bottom:14px;box-shadow:0 15px 30px -10px rgba(12,30,127,.2),0 4px 8px rgba(12,30,127,.08);border-left:5px solid;transition:transform .2s;}
  .pay-row:hover{transform:translateY(-3px);box-shadow:0 22px 44px -12px rgba(12,30,127,.3);}
  .pay-row.pending{border-left-color:#d97706;}
  .pay-row.approved{border-left-color:#3A7728;}
  .pay-row.rejected{border-left-color:#CE1126;}

  .btn-3d{display:inline-block;padding:10px 18px;border-radius:12px;font-weight:800;border:none;color:#fff;transition:transform .1s,box-shadow .1s;font-size:.85rem;cursor:pointer;text-decoration:none;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 5px 0 #1a3a12,0 8px 16px rgba(58,119,40,.35);}
  .btn-3d.green:active{transform:translateY(3px);box-shadow:0 2px 0 #1a3a12;}
  .btn-3d.outline-red{background:#fff;color:#CE1126;border:2px solid #CE1126;box-shadow:0 4px 8px rgba(206,17,38,.15);}
  .btn-3d.outline-red:active{transform:translateY(3px);}

  .pay-badge{padding:6px 14px;border-radius:20px;font-size:.72rem;font-weight:800;color:#fff;display:inline-block;}
  .pay-badge.pending{background:linear-gradient(135deg,#d97706,#b45309);}
  .pay-badge.approved{background:linear-gradient(135deg,#3A7728,#16a34a);}
  .pay-badge.rejected{background:linear-gradient(135deg,#CE1126,#a30d1e);}

  .proof-thumb{width:60px;height:60px;object-fit:cover;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,.15);}

  .approver-tag{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#0C1E7F;padding:6px 14px;border-radius:20px;font-size:.75rem;font-weight:700;border:1px solid #93c5fd;box-shadow:0 3px 8px rgba(12,30,127,.1);}

  /* Pagination */
  .pager{display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;margin-top:20px;padding-top:16px;border-top:2px dashed rgba(12,30,127,.15);}
  .pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:42px;height:42px;padding:0 14px;border-radius:12px;font-weight:800;font-size:.9rem;text-decoration:none;transition:transform .1s,box-shadow .1s;border:2px solid #0C1E7F;background:#fff;color:#0C1E7F;box-shadow:0 4px 0 #0C1E7F,0 6px 12px rgba(12,30,127,.15);}
  .pager a:hover{transform:translateY(-2px);box-shadow:0 6px 0 #0C1E7F,0 10px 18px rgba(12,30,127,.25);}
  .pager a:active{transform:translateY(3px);box-shadow:0 1px 0 #0C1E7F;}
  .pager .current{background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;border-color:#050b33;box-shadow:0 4px 0 #050b33,0 6px 12px rgba(12,30,127,.3);}
  .pager .disabled{opacity:.35;pointer-events:none;}
  .pager .info{border:none;background:transparent;box-shadow:none;color:#6b7280;font-size:.85rem;min-width:auto;}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4" style="max-width:1100px;position:relative;z-index:2;">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="fw-bold text-white mb-0">💳 Payment Approvals</h3>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="btn btn-outline-light btn-sm">← Dashboard</a>
  </div>

  <div class="mb-4">
    <a href="?filter=pending"  class="filter-tab pending  <?= $filter === 'pending'  ? 'active' : '' ?>">⏳ Pending (<?= $counts['pending'] ?>)</a>
    <a href="?filter=approved" class="filter-tab approved <?= $filter === 'approved' ? 'active' : '' ?>">✅ Approved (<?= $counts['approved'] ?>)</a>
    <a href="?filter=rejected" class="filter-tab rejected <?= $filter === 'rejected' ? 'active' : '' ?>">❌ Rejected (<?= $counts['rejected'] ?>)</a>
    <a href="?filter=all"      class="filter-tab all      <?= $filter === 'all'      ? 'active' : '' ?>">📋 All</a>
  </div>

  <?php if (empty($payments)): ?>
    <div class="glass text-center">
      <div class="fs-1 mb-2">📭</div>
      <p class="text-muted mb-0">No payments in this list.</p>
    </div>
  <?php else: ?>
    <?php foreach ($payments as $p):
      $label = PLANS[$p['plan']]['label'] ?? $p['plan'];
    ?>
      <div class="pay-row <?= e($p['status']) ?>">
        <div class="row g-3 align-items-center">
          <div class="col-12 col-md-4">
            <div class="fw-bold mb-1">
              #<?= (int)$p['id'] ?> —
              <?php if (!empty($p['user_name'])): ?>
                <?= e($p['user_name']) ?>
              <?php else: ?>
                <span class="text-muted fst-italic">Unknown</span>
              <?php endif; ?>
            </div>
            <?php if (!empty($p['user_phone'])): ?>
              <div class="small text-muted"><?= e($p['user_phone']) ?></div>
            <?php endif; ?>
          </div>

          <div class="col-6 col-md-3">
            <div class="small text-muted">Plan</div>
            <div class="fw-bold" style="font-size:.9rem;"><?= e($label) ?></div>
            <?php if (!empty($p['subscribed_grades'])): ?>
              <div class="small text-muted" style="font-size:.72rem;">📚 <?= e($p['subscribed_grades']) ?></div>
            <?php endif; ?>
          </div>

          <div class="col-6 col-md-2">
            <div class="small text-muted">Amount</div>
            <div class="fw-bold" style="color:#0C1E7F;font-size:1.1rem;">D<?= (int)$p['amount'] ?></div>
          </div>

          <div class="col-6 col-md-2">
            <div class="small text-muted">Method / Ref</div>
            <div class="small"><?= e($p['method']) ?></div>
            <div class="small" style="font-size:.72rem;color:#6b7280;"><?= e($p['reference']) ?></div>
          </div>

          <div class="col-6 col-md-1 text-center">
            <?php if (!empty($p['screenshot']) && file_exists(__DIR__ . '/../' . $p['screenshot'])): ?>
              <a href="<?= SITE_URL ?>/<?= e($p['screenshot']) ?>" target="_blank">
                <img src="<?= SITE_URL ?>/<?= e($p['screenshot']) ?>" class="proof-thumb">
              </a>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="pay-badge <?= e($p['status']) ?>">
              <?= $p['status'] === 'pending' ? '⏳ Pending' : ($p['status'] === 'approved' ? '✅ Approved' : '❌ Rejected') ?>
            </span>
            <?php if ($p['status'] !== 'pending'): ?>
              <span class="small text-muted"><?= e(fmt_date($p['approved_at'] ?? $p['created_at'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($p['approved_by'])): ?>
              <span class="approver-tag">👤 Approved by <b><?= e($p['approved_by']) ?></b></span>
            <?php endif; ?>
          </div>

          <?php if ($p['status'] === 'pending'): ?>
            <div class="d-flex gap-2">
              <form method="post" class="d-inline" onsubmit="return confirm('Approve this payment?');">
                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button class="btn-3d green">✅ Approve</button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Reject this payment?');">
                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button class="btn-3d outline-red">❌ Reject</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
      <div class="glass p-3 mt-3">
        <div class="pager">
          <?php $qs_prefix = 'filter=' . $filter . '&'; ?>

          <?php if ($page > 1): ?>
            <a href="?<?= $qs_prefix ?>page=<?= $page - 1 ?>">← Prev</a>
          <?php else: ?>
            <span class="disabled">← Prev</span>
          <?php endif; ?>

          <?php if ($page > 3): ?>
            <a href="?<?= $qs_prefix ?>page=1">1</a>
            <?php if ($page > 4): ?><span class="info">…</span><?php endif; ?>
          <?php endif; ?>

          <?php
          $start = max(1, $page - 2);
          $end   = min($total_pages, $page + 2);
          for ($i = $start; $i <= $end; $i++):
          ?>
            <?php if ($i === $page): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?<?= $qs_prefix ?>page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($page < $total_pages - 2): ?>
            <?php if ($page < $total_pages - 3): ?><span class="info">…</span><?php endif; ?>
            <a href="?<?= $qs_prefix ?>page=<?= $total_pages ?>"><?= $total_pages ?></a>
          <?php endif; ?>

          <?php if ($page < $total_pages): ?>
            <a href="?<?= $qs_prefix ?>page=<?= $page + 1 ?>">Next →</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
          <?php endif; ?>
        </div>

        <div class="text-center mt-2 small text-muted">
          Page <b><?= $page ?></b> of <b><?= $total_pages ?></b>
          · showing <?= count($payments) ?> of <?= $filtered_count ?> payments
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>