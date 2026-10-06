<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/login.php');
}

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
$C_EXPLAIN    = $pick(['explanation','reason','note']);
$C_SUBJECT    = $pick(['subject_id','subject']);

$GRADE_COLS = [];
foreach (['grade','class_level','exam_type'] as $gc) {
    if (in_array($gc, $cols, true)) $GRADE_COLS[] = $gc;
}

$use_index = ($C_CORRECT_IX !== null);

function to_db_answer($val, bool $use_index): ?string {
    $val = trim((string)$val);
    if ($val === '') return null;
    if (is_numeric($val)) {
        $n = (int)$val;
        if ($n >= 0 && $n <= 3) return $use_index ? (string)$n : ['A','B','C','D'][$n];
        return null;
    }
    $val = strtoupper($val);
    if (in_array($val, ['A','B','C','D'], true)) {
        if ($use_index) return (string)['A'=>0,'B'=>1,'C'=>2,'D'=>3][$val];
        return $val;
    }
    return null;
}

function db_to_letter($val): string {
    if ($val === null || $val === '') return '';
    if (is_numeric($val)) return [0=>'A',1=>'B',2=>'C',3=>'D'][(int)$val] ?? '';
    return in_array(strtoupper($val), ['A','B','C','D'], true) ? strtoupper($val) : '';
}

/* ============================================================
   PAGINATION
   ============================================================ */
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$csv_report = null;

/* ============================================================
   HANDLE POST
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* CSV IMPORT */
    if ($action === 'csv') {
        if (empty($_FILES['csv']['tmp_name'])) {
            flash('Upload a CSV file.', 'danger');
            redirect(SITE_URL . '/admin/questions.php');
        }

        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) { flash('Could not read file.', 'danger'); redirect(SITE_URL . '/admin/questions.php'); }

        $header = array_map(fn($h) => strtolower(trim($h)), fgetcsv($fh));

        $fields = [$C_QUESTION=>null,$C_OPT_A=>null,$C_OPT_B=>null,$C_OPT_C=>null,$C_OPT_D=>null];
        if ($C_SUBJECT) $fields[$C_SUBJECT] = null;
        foreach ($GRADE_COLS as $gc) $fields[$gc] = null;
        if ($use_index) $fields[$C_CORRECT_IX] = null;
        else            $fields[$C_CORRECT]    = null;
        if ($C_EXPLAIN) $fields[$C_EXPLAIN] = null;

        $insert_cols  = array_keys($fields);
        $placeholders = implode(',', array_fill(0, count($insert_cols), '?'));
        $ins = $pdo->prepare('INSERT INTO questions (' . implode(',', $insert_cols) . ") VALUES ($placeholders)");

        $imported = 0; $skipped = 0; $errors = [];

        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 5) continue;
            $data = [];
            foreach ($header as $i => $col) $data[$col] = trim((string)($row[$i] ?? ''));

            $q = $data['question'] ?? $data['question_text'] ?? '';
            $a = $data['option_a'] ?? $data['a'] ?? '';
            $b = $data['option_b'] ?? $data['b'] ?? '';
            $c = $data['option_c'] ?? $data['c'] ?? '';
            $d = $data['option_d'] ?? $data['d'] ?? '';
            $ansRaw = $data['correct_index'] ?? $data['correct'] ?? $data['answer'] ?? '';
            $db_ans = to_db_answer($ansRaw, $use_index);
            $sub_id = (int)($data['subject_id'] ?? 0);
            $sub_nm = trim($data['subject'] ?? '');
            $grade = '';
            foreach (['class_level','grade','exam_type'] as $gk) if (!empty($data[$gk])) { $grade = $data[$gk]; break; }
            $exp = $data['explanation'] ?? '';

            if ($q === '' || $a === '' || $b === '' || $c === '' || $d === '') { $skipped++; continue; }
            if ($db_ans === null) { $skipped++; continue; }
            if ($sub_id === 0 && $sub_nm === '') { $skipped++; continue; }
            if ($grade === '') { $skipped++; continue; }

            if ($sub_id === 0 && $sub_nm !== '') {
                $st = $pdo->prepare('SELECT id FROM subjects WHERE LOWER(name) = LOWER(?) LIMIT 1');
                $st->execute([$sub_nm]);
                $sub_id = (int)$st->fetchColumn();
                if ($sub_id === 0) { $skipped++; continue; }
            }

            $values = [$C_QUESTION=>$q,$C_OPT_A=>$a,$C_OPT_B=>$b,$C_OPT_C=>$c,$C_OPT_D=>$d];
            if ($C_SUBJECT) $values[$C_SUBJECT] = $sub_id;
            foreach ($GRADE_COLS as $gc) $values[$gc] = $grade;
            if ($use_index) $values[$C_CORRECT_IX] = $db_ans;
            else            $values[$C_CORRECT]    = $db_ans;
            if ($C_EXPLAIN) $values[$C_EXPLAIN] = $exp;

            $final = [];
            foreach ($insert_cols as $k) $final[] = $values[$k] ?? null;
            try { $ins->execute($final); $imported++; }
            catch (Exception $e) { $errors[] = $e->getMessage(); $skipped++; }
        }
        fclose($fh);
        $csv_report = ['imported'=>$imported,'skipped'=>$skipped,'errors'=>$errors];
        flash("✅ Imported $imported question(s). Skipped $skipped.");
    }
    elseif ($action === 'delete') {
        $qid = (int)($_POST['question_id'] ?? 0);
        if ($qid > 0) { $pdo->prepare('DELETE FROM questions WHERE id = ?')->execute([$qid]); flash('🗑️ Deleted.'); }
        redirect(SITE_URL . '/admin/questions.php' . (!empty($_POST['back_qs']) ? '?' . $_POST['back_qs'] : ''));
    }
    elseif ($action === 'delete_filtered') {
        $del_subject = (int)($_POST['del_subject'] ?? 0);
        $del_grade   = trim($_POST['del_grade'] ?? '');
        if ($del_subject > 0 || $del_grade !== '') {
            $where = []; $params = [];
            if ($C_SUBJECT && $del_subject > 0) { $where[] = "$C_SUBJECT = ?"; $params[] = $del_subject; }
            if ($del_grade !== '' && !empty($GRADE_COLS)) {
                $gc_conds = [];
                foreach ($GRADE_COLS as $gc) { $gc_conds[] = "$gc = ?"; $params[] = $del_grade; }
                $where[] = '(' . implode(' OR ', $gc_conds) . ')';
            }
            if ($where) {
                $st = $pdo->prepare('DELETE FROM questions WHERE ' . implode(' AND ', $where));
                $st->execute($params);
                flash('🗑️ Deleted ' . $st->rowCount() . ' question(s).', 'warning');
            }
        }
        redirect(SITE_URL . '/admin/questions.php');
    }
}

/* ============================================================
   LOAD SUBJECTS
   ============================================================ */
$subjects = [];
try {
    $sc = $pdo->query('DESCRIBE subjects')->fetchAll(PDO::FETCH_COLUMN);
    $S_NAME = in_array('name', $sc, true) ? 'name' : 'id';
    $S_GRD  = in_array('class_level', $sc, true) ? 'class_level' : (in_array('grade', $sc, true) ? 'grade' : null);
    $sql = "SELECT id, $S_NAME AS name" . ($S_GRD ? ", $S_GRD AS grade" : "") . " FROM subjects ORDER BY " . ($S_GRD ? "$S_GRD, " : "") . "$S_NAME";
    $subjects = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {}

$filter_subject = (int)($_GET['subject'] ?? 0);
$filter_grade   = trim($_GET['grade'] ?? '');
$filter_search  = trim($_GET['q'] ?? '');

/* Build WHERE */
$where = []; $params = [];
if ($C_SUBJECT && $filter_subject > 0) { $where[] = 'q.' . $C_SUBJECT . ' = ?'; $params[] = $filter_subject; }
if ($filter_grade !== '' && in_array($filter_grade, CLASS_LEVELS, true) && !empty($GRADE_COLS)) {
    $gc_conds = [];
    foreach ($GRADE_COLS as $gc) { $gc_conds[] = 'q.' . $gc . ' = ?'; $params[] = $filter_grade; }
    $where[] = '(' . implode(' OR ', $gc_conds) . ')';
}
if ($filter_search !== '') { $where[] = 'q.' . $C_QUESTION . ' LIKE ?'; $params[] = '%' . $filter_search . '%'; }

$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

/* Count total filtered */
$count_sql = 'SELECT COUNT(*) FROM questions q' . $where_sql;
$st = $pdo->prepare($count_sql);
$st->execute($params);
$filtered_count = (int)$st->fetchColumn();

$total_pages = max(1, (int)ceil($filtered_count / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

/* Fetch page */
$sql = 'SELECT q.*, s.name AS subject_name FROM questions q'
     . ($C_SUBJECT ? ' LEFT JOIN subjects s ON s.id = q.' . $C_SUBJECT : '')
     . $where_sql
     . " ORDER BY q.id DESC LIMIT $per_page OFFSET $offset";

$st = $pdo->prepare($sql);
$st->execute($params);
$questions = $st->fetchAll();

$total = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();

/* Preserve query string for pagination links */
$qs_params = [];
if ($filter_subject)         $qs_params['subject'] = $filter_subject;
if ($filter_grade)           $qs_params['grade']   = $filter_grade;
if ($filter_search !== '')   $qs_params['q']       = $filter_search;

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

  .input-3d{width:100%;padding:12px 16px;border:2px solid #d1d5db;border-radius:12px;font-size:.95rem;background:#fff;transition:all .2s;box-shadow:inset 0 2px 4px rgba(0,0,0,.04);}
  .input-3d:focus{outline:none;border-color:#0C1E7F;box-shadow:inset 0 2px 4px rgba(0,0,0,.04),0 0 0 4px rgba(12,30,127,.15);}
  .label-3d{font-weight:900;font-size:.75rem;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block;}

  .btn-3d{display:inline-block;padding:12px 20px;border-radius:12px;font-weight:800;border:none;color:#fff;transition:transform .1s,box-shadow .1s;font-size:.85rem;cursor:pointer;text-decoration:none;text-align:center;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 6px 0 #1a3a12,0 10px 20px rgba(58,119,40,.35);}
  .btn-3d.green:active{transform:translateY(4px);box-shadow:0 2px 0 #1a3a12;}
  .btn-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);box-shadow:0 6px 0 #050b33,0 10px 20px rgba(12,30,127,.35);}
  .btn-3d.blue:active{transform:translateY(4px);box-shadow:0 2px 0 #050b33;}
  .btn-3d.gold{background:linear-gradient(135deg,#d97706,#b45309);box-shadow:0 6px 0 #5c2b04,0 10px 20px rgba(217,119,6,.35);}
  .btn-3d.gold:active{transform:translateY(4px);box-shadow:0 2px 0 #5c2b04;}
  .btn-3d.red{background:linear-gradient(135deg,#CE1126,#a30d1e);box-shadow:0 5px 0 #630810,0 8px 16px rgba(206,17,38,.3);}
  .btn-3d.red:active{transform:translateY(3px);box-shadow:0 2px 0 #630810;}

  .q-row{background:linear-gradient(135deg,#fff,#f8faff);border-radius:18px;padding:18px;margin-bottom:12px;box-shadow:0 15px 30px -10px rgba(12,30,127,.2),0 4px 8px rgba(12,30,127,.08);border-left:5px solid #0C1E7F;transition:transform .2s;}
  .q-row:hover{transform:translateY(-3px);box-shadow:0 22px 44px -12px rgba(12,30,127,.28);}

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

  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="fw-bold text-white mb-0">❓ Questions</h3>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= SITE_URL ?>/admin/add_question.php" class="btn-3d green">➕ Add Manually</a>
      <a href="<?= SITE_URL ?>/admin/fix_subjects.php" class="btn-3d gold">🔧 Fix Subjects</a>
      <a href="<?= SITE_URL ?>/admin/dashboard.php" class="btn btn-outline-light btn-sm">← Dashboard</a>
    </div>
  </div>

  <?php if ($csv_report): ?>
    <div class="alert alert-info">
      <b>✅ Import complete</b> — <?= (int)$csv_report['imported'] ?> imported · <?= (int)$csv_report['skipped'] ?> skipped
    </div>
  <?php endif; ?>

  <!-- CSV IMPORT -->
  <div class="glass mb-4">
    <h5 class="fw-bold mb-3" style="color:#3A7728;">📤 Import CSV</h5>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="csv">
      <div class="row g-2 align-items-end">
        <div class="col-md-9">
          <label class="label-3d">CSV file</label>
          <input type="file" name="csv" class="input-3d" accept=".csv" required>
        </div>
        <div class="col-md-3">
          <button class="btn-3d green w-100">📤 Import</button>
        </div>
      </div>
    </form>
  </div>

  <!-- FILTER -->
  <div class="glass mb-4">
    <h5 class="fw-bold mb-3" style="color:#0C1E7F;">🔍 Filter</h5>
    <form method="get">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="label-3d">Subject</label>
          <select name="subject" class="input-3d">
            <option value="">All subjects</option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $filter_subject === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['name']) ?><?= !empty($s['grade']) ? ' (' . e($s['grade']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="label-3d">Grade</label>
          <select name="grade" class="input-3d">
            <option value="">All grades</option>
            <?php foreach (CLASS_LEVELS as $g): ?>
              <option value="<?= e($g) ?>" <?= $filter_grade === $g ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="label-3d">Search text</label>
          <input type="text" name="q" class="input-3d" value="<?= e($filter_search) ?>" placeholder="e.g. water">
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button class="btn-3d blue flex-grow-1">🔍 Filter</button>
          <a href="<?= SITE_URL ?>/admin/questions.php" class="btn-3d" style="background:#e5e7eb;color:#374151;">✖</a>
        </div>
      </div>
    </form>
  </div>

  <!-- LIST HEADER -->
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-bold text-white mb-0">
      📋 Questions
      <span class="badge bg-light text-dark"><?= count($questions) ?> shown</span>
      <span class="badge bg-light text-dark"><?= number_format($filtered_count) ?> matched</span>
      <span class="badge bg-light text-dark"><?= number_format($total) ?> total</span>
    </h5>
    <?php if ($filter_subject > 0 || $filter_grade !== ''): ?>
      <form method="post" onsubmit="return confirm('⚠️ Delete ALL filtered questions? This cannot be undone.');">
        <input type="hidden" name="action" value="delete_filtered">
        <input type="hidden" name="del_subject" value="<?= $filter_subject ?>">
        <input type="hidden" name="del_grade" value="<?= e($filter_grade) ?>">
        <button class="btn-3d red">🗑️ Delete All Filtered</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (empty($questions)): ?>
    <div class="glass text-center">
      <div class="fs-1 mb-2">📭</div>
      <p class="text-muted mb-0">No questions found with these filters.</p>
    </div>
  <?php else: ?>
    <?php foreach ($questions as $q):
      $qid = (int)$q['id'];
      $ansVal = null;
      if ($C_CORRECT_IX !== null && isset($q[$C_CORRECT_IX])) $ansVal = $q[$C_CORRECT_IX];
      elseif ($C_CORRECT !== null && isset($q[$C_CORRECT])) $ansVal = $q[$C_CORRECT];
      $corr = db_to_letter($ansVal);
      $grade_display = '';
      foreach ($GRADE_COLS as $gc) if (!empty($q[$gc])) { $grade_display = $q[$gc]; break; }
    ?>
      <div class="q-row">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
          <div class="flex-grow-1">
            <div class="fw-bold">#<?= $qid ?> — <?= e($q[$C_QUESTION] ?? '') ?></div>
            <div class="small text-muted mb-2"><?= e($q['subject_name'] ?? '') ?><?php if ($grade_display): ?> · <?= e($grade_display) ?><?php endif; ?></div>
            <div class="small">
              <div <?= $corr==='A'?'class="fw-bold text-success"':'' ?>>A. <?= e($q[$C_OPT_A] ?? '') ?></div>
              <div <?= $corr==='B'?'class="fw-bold text-success"':'' ?>>B. <?= e($q[$C_OPT_B] ?? '') ?></div>
              <div <?= $corr==='C'?'class="fw-bold text-success"':'' ?>>C. <?= e($q[$C_OPT_C] ?? '') ?></div>
              <div <?= $corr==='D'?'class="fw-bold text-success"':'' ?>>D. <?= e($q[$C_OPT_D] ?? '') ?></div>
            </div>
          </div>
          <div class="d-flex flex-column gap-2">
            <a href="<?= SITE_URL ?>/admin/edit_question.php?id=<?= $qid ?>" class="btn-3d blue">✏️ Edit</a>
            <form method="post" onsubmit="return confirm('Delete this question?');" class="d-inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="question_id" value="<?= $qid ?>">
              <input type="hidden" name="back_qs" value="<?= e($back_qs) ?>">
              <button class="btn-3d red">🗑️</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
      <div class="glass p-3 mt-3">
        <div class="pager">
          <?php
          $qs_prefix = $base_qs ? $base_qs . '&' : '';
          ?>

          <!-- Previous -->
          <?php if ($page > 1): ?>
            <a href="?<?= $qs_prefix ?>page=<?= $page - 1 ?>" title="Previous page">← Prev</a>
          <?php else: ?>
            <span class="disabled">← Prev</span>
          <?php endif; ?>

          <!-- First -->
          <?php if ($page > 3): ?>
            <a href="?<?= $qs_prefix ?>page=1">1</a>
            <?php if ($page > 4): ?><span class="info">…</span><?php endif; ?>
          <?php endif; ?>

          <!-- Page numbers -->
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

          <!-- Last -->
          <?php if ($page < $total_pages - 2): ?>
            <?php if ($page < $total_pages - 3): ?><span class="info">…</span><?php endif; ?>
            <a href="?<?= $qs_prefix ?>page=<?= $total_pages ?>"><?= $total_pages ?></a>
          <?php endif; ?>

          <!-- Next -->
          <?php if ($page < $total_pages): ?>
            <a href="?<?= $qs_prefix ?>page=<?= $page + 1 ?>" title="Next page">Next →</a>
          <?php else: ?>
            <span class="disabled">Next →</span>
          <?php endif; ?>
        </div>

        <div class="text-center mt-2 small text-muted">
          Page <b><?= $page ?></b> of <b><?= $total_pages ?></b>
          · showing <?= count($questions) ?> of <?= number_format($filtered_count) ?> questions
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>