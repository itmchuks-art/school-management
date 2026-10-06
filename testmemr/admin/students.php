<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/login.php');
}

/* Handle actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action  = $_POST['action'] ?? '';

    if ($user_id > 0) {
        if ($action === 'delete') {
            try {
                $pdo->prepare('DELETE FROM payments WHERE user_id = ?')->execute([$user_id]);
                $pdo->prepare('DELETE FROM attempts WHERE user_id = ?')->execute([$user_id]);
            } catch (Exception $e) {}
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
            flash('🗑️ Student deleted.');
            redirect(SITE_URL . '/admin/students.php?tab=' . ($_POST['tab'] ?? 'all') . (!empty($_POST['back_qs']) ? '&' . $_POST['back_qs'] : ''));
        } elseif ($action === 'cancel_sub') {
            $pdo->prepare('UPDATE users SET subscription_plan = NULL, subscription_end = NULL, subscribed_grades = NULL WHERE id = ?')
                ->execute([$user_id]);
            flash('🚫 Subscription cancelled.');
            redirect(SITE_URL . '/admin/students.php?tab=expired');
        }
    }
}

$tab = $_GET['tab'] ?? 'active';
if (!in_array($tab, ['active', 'expired', 'all'], true)) $tab = 'active';

$q = trim($_GET['q'] ?? '');

/* Pagination */
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

/* Counts */
$count_active  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE subscription_end > NOW()")->fetchColumn();
$count_all     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$count_expired = $count_all - $count_active;

/* Build WHERE */
$where = []; $params = [];
if ($tab === 'active')       $where[] = 'subscription_end > NOW()';
elseif ($tab === 'expired')  $where[] = '(subscription_end IS NULL OR subscription_end <= NOW())';
if ($q !== '') { $where[] = '(name LIKE ? OR phone LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }

$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

/* Count filtered */
$st = $pdo->prepare('SELECT COUNT(*) FROM users' . $where_sql);
$st->execute($params);
$filtered_count = (int)$st->fetchColumn();

$total_pages = max(1, (int)ceil($filtered_count / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

/* Fetch page */
$sql = 'SELECT * FROM users' . $where_sql
     . " ORDER BY subscription_end IS NULL, subscription_end DESC, id DESC LIMIT $per_page OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$students = $st->fetchAll();

/* Preserve query for pagination */
$qs_params = ['tab' => $tab];
if ($q !== '') $qs_params['q'] = $q;
$base_qs = http_build_query($qs_params);
$back_qs = $base_qs ? $base_qs . '&page=' . $page : 'page=' . $page;

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:26px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .search-input{width:100%;padding:14px 18px;border:2px solid #d1d5db;border-radius:14px;font-size:1rem;background:#fff;transition:all .2s;box-shadow:inset 0 2px 4px rgba(0,0,0,.04);}
  .search-input:focus{outline:none;border-color:#0C1E7F;box-shadow:inset 0 2px 4px rgba(0,0,0,.04),0 0 0 4px rgba(12,30,127,.15);}

  .tab-3d{padding:12px 22px;border-radius:14px;text-decoration:none;font-weight:800;font-size:.88rem;margin-right:8px;margin-bottom:8px;display:inline-block;transition:all .25s;border:2px solid;box-shadow:0 6px 14px rgba(0,0,0,.15);}
  .tab-3d:hover{transform:translateY(-3px);}
  .tab-3d.active{color:#3A7728;border-color:#3A7728;background:#fff;}
  .tab-3d.active.on{background:linear-gradient(135deg,#3A7728,#16a34a);color:#fff;box-shadow:0 8px 0 #1a3a12,0 12px 24px rgba(58,119,40,.35);}
  .tab-3d.expired{color:#CE1126;border-color:#CE1126;background:#fff;}
  .tab-3d.expired.on{background:linear-gradient(135deg,#CE1126,#a30d1e);color:#fff;box-shadow:0 8px 0 #630810,0 12px 24px rgba(206,17,38,.35);}
  .tab-3d.all{color:#0C1E7F;border-color:#0C1E7F;background:#fff;}
  .tab-3d.all.on{background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;box-shadow:0 8px 0 #050b33,0 12px 24px rgba(12,30,127,.35);}

  .student-row{background:linear-gradient(135deg,#fff,#f8faff);border-radius:18px;padding:18px;margin-bottom:12px;box-shadow:0 15px 30px -10px rgba(12,30,127,.2),0 4px 8px rgba(12,30,127,.08);transition:transform .2s,box-shadow .2s;border-left:5px solid #0C1E7F;}
  .student-row:hover{transform:translateY(-3px);box-shadow:0 22px 44px -12px rgba(12,30,127,.3);}
  .student-row.active-sub{border-left-color:#3A7728;background:linear-gradient(135deg,#f0fdf4,#dcfce7);}
  .student-row.expired-sub{border-left-color:#CE1126;background:linear-gradient(135deg,#fff1f2,#fee2e2);}

  .avatar-mini{width:52px;height:52px;flex-shrink:0;border-radius:50%;background:linear-gradient(135deg,#CE1126 0%,#0C1E7F 50%,#3A7728 100%);display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;box-shadow:0 6px 12px rgba(12,30,127,.3);}
  .avatar-mini.expired{background:linear-gradient(135deg,#6b7280,#4b5563);}

  .btn-3d{display:inline-block;padding:8px 14px;border-radius:10px;font-weight:800;border:none;color:#fff;transition:transform .1s,box-shadow .1s;font-size:.78rem;cursor:pointer;text-decoration:none;}
  .btn-3d.gold{background:linear-gradient(135deg,#d97706,#b45309);box-shadow:0 4px 0 #5c2b04,0 6px 12px rgba(217,119,6,.3);}
  .btn-3d.gold:active{transform:translateY(3px);box-shadow:0 1px 0 #5c2b04;}
  .btn-3d.red{background:linear-gradient(135deg,#CE1126,#a30d1e);box-shadow:0 4px 0 #630810,0 6px 12px rgba(206,17,38,.3);}
  .btn-3d.red:active{transform:translateY(3px);box-shadow:0 1px 0 #630810;}
  .btn-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);box-shadow:0 4px 0 #050b33,0 6px 12px rgba(12,30,127,.3);}
  .btn-3d.blue:active{transform:translateY(3px);box-shadow:0 1px 0 #050b33;}

  .badge-3d{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.7rem;font-weight:800;color:#fff;}
  .badge-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);}
  .badge-3d.gray{background:linear-gradient(135deg,#6b7280,#4b5563);}
  .badge-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);}
  .badge-3d.gold{background:linear-gradient(135deg,#d97706,#b45309);}
  .badge-3d.red{background:linear-gradient(135deg,#CE1126,#a30d1e);}

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
    <h3 class="fw-bold text-white mb-0">👥 Students</h3>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="btn btn-outline-light btn-sm">← Dashboard</a>
  </div>

  <!-- TABS -->
  <div class="mb-4">
    <a href="?tab=active<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="tab-3d active <?= $tab === 'active' ? 'on' : '' ?>">✅ Active (<?= $count_active ?>)</a>
    <a href="?tab=expired<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="tab-3d expired <?= $tab === 'expired' ? 'on' : '' ?>">❌ No Active (<?= $count_expired ?>)</a>
    <a href="?tab=all<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="tab-3d all <?= $tab === 'all' ? 'on' : '' ?>">📋 All (<?= $count_all ?>)</a>
  </div>

  <!-- SEARCH -->
  <div class="glass mb-4">
    <form method="get">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div class="row g-2">
        <div class="col-md-9">
          <input type="text" name="q" class="search-input" placeholder="🔍 Search by name or phone..." value="<?= e($q) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button class="btn-3d blue flex-grow-1" style="padding:14px;">Search</button>
          <?php if ($q !== ''): ?>
            <a href="?tab=<?= e($tab) ?>" class="btn-3d" style="background:#e5e7eb;color:#374151;padding:14px 18px;text-decoration:none;border-radius:10px;">✖</a>
          <?php endif; ?>
        </div>
      </div>
    </form>
    <div class="small text-muted mt-3">
      Showing <b><?= count($students) ?></b> of <b><?= $filtered_count ?></b> student(s)
      <?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?>
    </div>
  </div>

  <?php if (empty($students)): ?>
    <div class="glass text-center">
      <div class="fs-1 mb-2">📭</div>
      <p class="text-muted mb-0">
        <?php if ($tab === 'active'): ?>No active subscribers right now.
        <?php elseif ($tab === 'expired'): ?>All students have active subscriptions — nice!
        <?php else: ?>No students found.<?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <?php foreach ($students as $s):
      $subbed = is_subscribed($s);
      $rowClass = $subbed ? 'active-sub' : 'expired-sub';
      $avatarClass = $subbed ? '' : 'expired';
    ?>
      <div class="student-row <?= $rowClass ?>">
        <div class="d-flex gap-3 align-items-center flex-wrap">
          <div class="avatar-mini <?= $avatarClass ?>"><?= $subbed ? '👤' : '😴' ?></div>
          <div class="flex-grow-1">
            <div class="fw-bold" style="color:#0C1E7F;"><?= e($s['name']) ?></div>
            <div class="small text-muted"><?= e($s['phone']) ?> · #<?= (int)$s['id'] ?></div>
          </div>
          <div>
            <?php if (($s['grade'] ?? '') === 'ALL'): ?>
              <span class="badge-3d gold">🌟 All Grades</span>
            <?php elseif (!empty($s['grade'])): ?>
              <span class="badge-3d blue"><?= e($s['grade']) ?></span>
            <?php else: ?>
              <span class="badge-3d gray">No grade</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <?php if ($subbed): ?>
              <span class="badge-3d green">✅ Active · <?= sub_days_left($s) ?> days left</span>
              <span class="small text-muted ms-2">until <?= e(fmt_date($s['subscription_end'])) ?></span>
            <?php else: ?>
              <span class="badge-3d red">❌ No active subscription</span>
              <?php if (!empty($s['subscription_end'])): ?>
                <span class="small text-muted ms-2">expired <?= e(fmt_date($s['subscription_end'])) ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2">
            <?php if ($subbed): ?>
              <form method="post" onsubmit="return confirm('Cancel this subscription?');" class="d-inline">
                <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
                <input type="hidden" name="action" value="cancel_sub">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <button class="btn-3d gold">🚫 Cancel Sub</button>
              </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('⚠️ PERMANENTLY delete this student and all their data?');" class="d-inline">
              <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="tab" value="<?= e($tab) ?>">
              <input type="hidden" name="back_qs" value="<?= e($back_qs) ?>">
              <button class="btn-3d red">🗑️ Delete</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
      <div class="glass p-3 mt-3">
        <div class="pager">
          <?php $qs_prefix = $base_qs ? $base_qs . '&' : ''; ?>

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
          · showing <?= count($students) ?> of <?= $filtered_count ?> students
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>