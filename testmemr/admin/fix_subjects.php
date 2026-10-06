<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/login.php');
}

/* Handle move */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move') {
    $qids   = $_POST['question_ids'] ?? [];
    $target = (int)($_POST['target_subject'] ?? 0);

    if (empty($qids) || $target <= 0) {
        flash('Select questions and a target subject.', 'danger');
        redirect(SITE_URL . '/admin/fix_subjects.php');
    }

    $chk = $pdo->prepare('SELECT id FROM subjects WHERE id = ? LIMIT 1');
    $chk->execute([$target]);
    if (!$chk->fetch()) {
        flash('Target subject not found.', 'danger');
        redirect(SITE_URL . '/admin/fix_subjects.php');
    }

    $ids = array_map('intval', $qids);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    try {
        $st = $pdo->prepare("UPDATE questions SET subject_id = ? WHERE id IN ($in)");
        $st->execute(array_merge([$target], $ids));
        flash('✅ Moved ' . $st->rowCount() . ' question(s).');
    } catch (Exception $e) {
        flash('Error: ' . $e->getMessage(), 'danger');
    }

    redirect(SITE_URL . '/admin/fix_subjects.php?filter_subject=' . (int)($_POST['filter_subject'] ?? 0));
}

/* Detect columns */
$cols = $pdo->query('DESCRIBE questions')->fetchAll(PDO::FETCH_COLUMN);
$pick = function ($list) use ($cols) {
    foreach ($list as $c) if (in_array($c, $cols, true)) return $c;
    return null;
};

$C_QUESTION   = $pick(['question_text','question','q','text']);
$C_OPT_A      = $pick(['option_a','a']);
$C_OPT_B      = $pick(['option_b','b']);
$C_OPT_C      = $pick(['option_c','c']);
$C_OPT_D      = $pick(['option_d','d']);
$C_CORRECT    = $pick(['correct_option','correct','answer','ans']);
$C_CORRECT_IX = $pick(['correct_index']);
$C_SUBJECT    = $pick(['subject_id','subject']);

/* Load subjects */
$subjects = [];
try {
    $sc = $pdo->query('DESCRIBE subjects')->fetchAll(PDO::FETCH_COLUMN);
    $S_NAME = in_array('name', $sc, true) ? 'name' : 'id';
    $S_GRD  = in_array('class_level', $sc, true) ? 'class_level' : (in_array('grade', $sc, true) ? 'grade' : null);
    $sql = "SELECT id, $S_NAME AS name" . ($S_GRD ? ", $S_GRD AS grade" : "") . " FROM subjects ORDER BY " . ($S_GRD ? "$S_GRD, " : "") . "$S_NAME";
    $subjects = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {}

/* Filter */
$filter_subject = (int)($_GET['filter_subject'] ?? 0);
$filter_grade   = trim($_GET['filter_grade'] ?? '');

/* Pagination */
$per_page = 25;
$page = max(1, (int)($_GET['page'] ?? 1));

/* Build WHERE */
$where = []; $params = [];
if ($filter_subject > 0 && $C_SUBJECT) { $where[] = 'q.' . $C_SUBJECT . ' = ?'; $params[] = $filter_subject; }
if ($filter_grade !== '' && !empty($GRADE_COLS = [])) {}
if ($filter_grade !== '') {
    $gc_arr = [];
    foreach (['grade','class_level','exam_type'] as $gc) if (in_array($gc, $cols, true)) $gc_arr[] = $gc;
    if (!empty($gc_arr)) {
        $gc_conds = [];
        foreach ($gc_arr as $gc) { $gc_conds[] = 's.' . $gc . ' = ?'; $params[] = $filter_grade; }
        $where[] = '(' . implode(' OR ', $gc_conds) . ')';
    }
}

$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$from_sql  = ' FROM questions q' . ($C_SUBJECT ? ' LEFT JOIN subjects s ON s.id = q.' . $C_SUBJECT : '');

/* Count */
$st = $pdo->prepare('SELECT COUNT(*)' . $from_sql . $where_sql);
$st->execute($params);
$filtered_count = (int)$st->fetchColumn();

$total_pages = max(1, (int)ceil($filtered_count / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

/* Fetch */
$sql = 'SELECT q.*, s.name AS subject_name' . $from_sql . $where_sql
     . " ORDER BY q.id DESC LIMIT $per_page OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$questions = $st->fetchAll();

$total = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();

$base_qs = [];
if ($filter_subject) $base_qs['filter_subject'] = $filter_subject;
if ($filter_grade)   $base_qs['filter_grade']   = $filter_grade;
$base_qs_str = http_build_query($base_qs);

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:26px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .input-3d{width:100%;padding:12px 16px;border:2px solid #d1d5db;border-radius:12px;font-size:.95rem;background:#fff;transition:all .2s;}
  .input-3d:focus{outline:none;border-color:#0C1E7F;box-shadow:0 0 0 4px rgba(12,30,127,.15);}

  .btn-3d{display:inline-block;padding:12px 20px;border-radius:12px;font-weight:800;border:none;color:#fff;transition:transform .1s,box-shadow .1s;font-size:.85rem;cursor:pointer;text-decoration:none;text-align:center;}
  .btn-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);box-shadow:0 6px 0 #050b33,0 10px 20px rgba(12,30,127,.35);}
  .btn-3d.blue:active{transform:translateY(4px);box-shadow:0 2px 0 #050b33;}

  .q-card{background:linear-gradient(135deg,#fff,#f8faff);border-radius:16px;padding:16px 18px;margin-bottom:12px;box-shadow:0 10px 20px -8px rgba(12,30,127,.18);border-left:5px solid #0C1E7F;transition:transform .2s;}
  .q-card:hover{transform:translateY(-2px);}
  .q-card.checked{border-left-color:#d97706;background:linear-gradient(135deg,#fffbeb,#fef3c7);}

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
    <h3 class="fw-bold text-white mb-0">🔧 Fix Question Subjects</h3>
    <a href="<?= SITE_URL ?>/admin/questions.php" class="btn btn-outline-light btn-sm">← Questions</a>
  </div>

  <div class="glass mb-4">
    <form method="get">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="small fw-bold">Subject</label>
          <select name="filter_subject" class="input-3d">
            <option value="">All subjects</option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $filter_subject === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['name']) ?><?= !empty($s['grade']) ? ' (' . e($s['grade']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="small fw-bold">Grade</label>
          <select name="filter_grade" class="input-3d">
            <option value="">All grades</option>
            <?php foreach (CLASS_LEVELS as $g): ?>
              <option value="<?= e($g) ?>" <?= $filter_grade === $g ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button class="btn-3d blue flex-grow-1">🔍 Filter</button>
          <a href="<?= SITE_URL ?>/admin/fix_subjects.php" class="btn-3d" style="background:#e5e7eb;color:#374151;">✖</a>
        </div>
      </div>
    </form>
    <div class="small text-muted mt-3">
      Showing <b><?= count($questions) ?></b> of <b><?= $filtered_count ?></b> question(s) · <b><?= number_format($total) ?></b> total
    </div>
  </div>

  <form method="post">
    <input type="hidden" name="action" value="move">
    <input type="hidden" name="filter_subject" value="<?= (int)$filter_subject ?>">

    <?php if (empty($questions)): ?>
      <div class="glass text-center">
        <div class="fs-1 mb-2">📭</div>
        <p class="text-muted mb-0">No questions found.</p>
      </div>
    <?php else: ?>
      <?php foreach ($questions as $q):
        $qid = (int)$q['id'];
        $ansVal = null;
        if ($C_CORRECT_IX !== null && isset($q[$C_CORRECT_IX])) $ansVal = $q[$C_CORRECT_IX];
        elseif ($C_CORRECT !== null && isset($q[$C_CORRECT])) $ansVal = $q[$C_CORRECT];
        $corr = is_numeric($ansVal) ? ['A','B','C','D'][(int)$ansVal] ?? '' : strtoupper((string)$ansVal);
      ?>
        <div class="q-card" id="qcard-<?= $qid ?>">
          <div class="d-flex gap-3">
            <div class="flex-shrink-0 pt-1">
              <input type="checkbox" name="question_ids[]" value="<?= $qid ?>" class="form-check-input q-check" style="width:22px;height:22px;cursor:pointer">
            </div>
            <div class="flex-grow-1">
              <div class="small text-muted mb-1">
                <span class="badge bg-secondary">#<?= $qid ?></span>
                <span class="badge bg-primary"><?= e($q['subject_name'] ?? '—') ?></span>
              </div>
              <div class="fw-bold mb-2"><?= e($q[$C_QUESTION] ?? '') ?></div>
              <div class="small">
                <div>A. <?= e($q[$C_OPT_A] ?? '') ?></div>
                <div>B. <?= e($q[$C_OPT_B] ?? '') ?></div>
                <div>C. <?= e($q[$C_OPT_C] ?? '') ?></div>
                <div>D. <?= e($q[$C_OPT_D] ?? '') ?></div>
              </div>
              <div class="small text-success mt-1">✓ Correct: <?= e($corr) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- PAGINATION -->
      <?php if ($total_pages > 1): ?>
        <div class="glass p-3 mt-3 mb-3">
          <div class="pager">
            <?php $qs_prefix = $base_qs_str ? $base_qs_str . '&' : ''; ?>
            <?php if ($page > 1): ?>
              <a href="?<?= $qs_prefix ?>page=<?= $page - 1 ?>">← Prev</a>
            <?php else: ?>
              <span class="disabled">← Prev</span>
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

            <?php if ($page < $total_pages): ?>
              <a href="?<?= $qs_prefix ?>page=<?= $page + 1 ?>">Next →</a>
            <?php else: ?>
              <span class="disabled">Next →</span>
            <?php endif; ?>
          </div>
          <div class="text-center mt-2 small text-muted">
            Page <b><?= $page ?></b> of <b><?= $total_pages ?></b>
          </div>
        </div>
      <?php endif; ?>

      <!-- MOVE BAR -->
      <div class="glass p-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-8">
            <label class="small fw-bold">🎯 Move selected to:</label>
            <select name="target_subject" class="input-3d" required>
              <option value="">-- Choose target subject --</option>
              <?php foreach ($subjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?><?= !empty($s['grade']) ? ' (' . e($s['grade']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <button class="btn-3d blue w-100" onclick="return confirmMove();">🚚 Move Selected</button>
          </div>
        </div>
        <div class="small text-muted mt-2 text-center">Selected: <b id="selCount">0</b> question(s)</div>
      </div>
    <?php endif; ?>
  </form>

</div>

<script>
function updateCount() {
  var n = document.querySelectorAll('.q-check:checked').length;
  var el = document.getElementById('selCount');
  if (el) el.textContent = n;

  document.querySelectorAll('.q-check').forEach(function (cb) {
    var card = cb.closest('.q-card');
    if (card) card.classList.toggle('checked', cb.checked);
  });
}

function confirmMove() {
  var n = document.querySelectorAll('.q-check:checked').length;
  if (n === 0) { alert('Select at least one question.'); return false; }
  return confirm('Move ' + n + ' question(s)?');
}

document.querySelectorAll('.q-check').forEach(function (cb) {
  cb.addEventListener('change', updateCount);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>