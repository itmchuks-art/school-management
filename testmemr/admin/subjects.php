<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/login.php');
}

/* Handle add/delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $grade = trim($_POST['grade'] ?? '');

        if ($name === '' || !in_array($grade, CLASS_LEVELS, true)) {
            flash('Fill in name and grade.', 'danger');
        } else {
            $cols = $pdo->query('DESCRIBE subjects')->fetchAll(PDO::FETCH_COLUMN);
            $fields = ['name' => $name];
            if (in_array('class_level', $cols, true)) $fields['class_level'] = $grade;
            if (in_array('grade', $cols, true))       $fields['grade'] = $grade;

            $keys = array_keys($fields);
            $ph   = implode(',', array_fill(0, count($keys), '?'));
            $pdo->prepare('INSERT INTO subjects (' . implode(',', $keys) . ") VALUES ($ph)")
                ->execute(array_values($fields));
            flash('✅ Subject added.');
        }
        redirect(SITE_URL . '/admin/subjects.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['subject_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
            flash('🗑️ Subject deleted.');
        }
        redirect(SITE_URL . '/admin/subjects.php');
    }
}

$subjects = [];
try {
    $cols = $pdo->query('DESCRIBE subjects')->fetchAll(PDO::FETCH_COLUMN);
    $S_NAME = in_array('name', $cols, true) ? 'name' : 'id';
    $S_GRD  = in_array('class_level', $cols, true) ? 'class_level'
            : (in_array('grade', $cols, true) ? 'grade' : null);

    $sql = "SELECT s.*, (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) AS q_count FROM subjects s"
         . " ORDER BY " . ($S_GRD ? "$S_GRD, " : "") . "$S_NAME";
    $subjects = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:26px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .input-3d{width:100%;padding:14px 16px;border:2px solid #d1d5db;border-radius:12px;font-size:1rem;background:#fff;transition:all .2s;box-shadow:inset 0 2px 4px rgba(0,0,0,.04);}
  .input-3d:focus{outline:none;border-color:#0C1E7F;box-shadow:inset 0 2px 4px rgba(0,0,0,.04),0 0 0 4px rgba(12,30,127,.15);}
  .label-3d{font-weight:900;font-size:.78rem;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:block;}

  .btn-3d{display:inline-block;padding:14px 22px;border-radius:12px;font-weight:900;border:none;color:#fff;transition:transform .1s,box-shadow .1s;font-size:.9rem;cursor:pointer;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 6px 0 #1a3a12,0 10px 20px rgba(58,119,40,.4);}
  .btn-3d.green:active{transform:translateY(4px);box-shadow:0 2px 0 #1a3a12;}
  .btn-3d.red{background:linear-gradient(135deg,#CE1126,#a30d1e);box-shadow:0 5px 0 #630810,0 8px 16px rgba(206,17,38,.3);}
  .btn-3d.red:active{transform:translateY(3px);box-shadow:0 2px 0 #630810;}

  .subj-row{background:linear-gradient(135deg,#fff,#f8faff);border-radius:18px;padding:16px 20px;margin-bottom:12px;box-shadow:0 15px 30px -10px rgba(12,30,127,.2),0 4px 8px rgba(12,30,127,.08);border-left:5px solid;transition:transform .2s;}
  .subj-row:hover{transform:translateX(5px);box-shadow:0 20px 40px -12px rgba(12,30,127,.28);}
  .subj-row.maths{border-left-color:#0C1E7F;}
  .subj-row.english{border-left-color:#CE1126;}
  .subj-row.science{border-left-color:#3A7728;}
  .subj-row.ses{border-left-color:#d97706;}
  .subj-row.default{border-left-color:#6b7280;}

  .subj-name{font-weight:900;font-size:1.05rem;color:#0C1E7F;}

  .badge-3d{display:inline-block;padding:5px 14px;border-radius:20px;font-size:.72rem;font-weight:800;color:#fff;box-shadow:0 4px 8px rgba(0,0,0,.1);}
  .badge-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);}
  .badge-3d.gold{background:linear-gradient(135deg,#d97706,#b45309);}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4" style="max-width:1000px;position:relative;z-index:2;">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="fw-bold text-white mb-0">📚 Subjects</h3>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="btn btn-outline-light btn-sm">← Dashboard</a>
  </div>

  <!-- ADD SUBJECT -->
  <div class="glass mb-4">
    <h5 class="fw-bold mb-3" style="color:#0C1E7F;">➕ Add Subject</h5>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="label-3d">Subject Name</label>
          <input name="name" class="input-3d" placeholder="e.g. Maths" required>
        </div>
        <div class="col-md-4">
          <label class="label-3d">Grade</label>
          <select name="grade" class="input-3d" required>
            <option value="">-- Choose --</option>
            <?php foreach (CLASS_LEVELS as $g): ?>
              <option value="<?= e($g) ?>"><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <button class="btn-3d green w-100">➕ Add Subject</button>
        </div>
      </div>
    </form>
  </div>

  <!-- LIST -->
  <h5 class="fw-bold text-white mb-3">📋 All Subjects <span class="badge bg-light text-dark"><?= count($subjects) ?></span></h5>

  <?php if (empty($subjects)): ?>
    <div class="glass text-center">
      <div class="fs-1 mb-2">📭</div>
      <p class="text-muted mb-0">No subjects yet.</p>
    </div>
  <?php else: ?>
    <?php foreach ($subjects as $s):
      $name = $s['name'] ?? '';
      $cls = 'default';
      if (stripos($name, 'math') !== false) $cls = 'maths';
      elseif (stripos($name, 'english') !== false) $cls = 'english';
      elseif (stripos($name, 'science') !== false) $cls = 'science';
      elseif (stripos($name, 'ses') !== false || stripos($name, 'social') !== false) $cls = 'ses';
      $grade = $s['class_level'] ?? $s['grade'] ?? '';
    ?>
      <div class="subj-row <?= $cls ?>">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div>
            <div class="subj-name"><?= e($name) ?></div>
            <div class="small text-muted">#<?= (int)$s['id'] ?></div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="badge-3d blue"><?= e($grade) ?></span>
            <span class="badge-3d gold"><?= (int)$s['q_count'] ?> questions</span>
            <form method="post" onsubmit="return confirm('Delete this subject? Questions will NOT be deleted.');" class="d-inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="subject_id" value="<?= (int)$s['id'] ?>">
              <button class="btn-3d red" style="padding:8px 14px;">🗑️</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>