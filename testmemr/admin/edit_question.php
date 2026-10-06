<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/login.php');
}

$qid = (int)($_GET['id'] ?? 0);
if ($qid <= 0) {
    flash('Invalid question.', 'danger');
    redirect(SITE_URL . '/admin/questions.php');
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
$C_EXPLAIN    = $pick(['explanation','reason','note']);
$C_SUBJECT    = $pick(['subject_id','subject']);

$GRADE_COLS   = [];
foreach (['grade','class_level','exam_type'] as $gc) if (in_array($gc, $cols, true)) $GRADE_COLS[] = $gc;

$use_index = ($C_CORRECT_IX !== null);

/* Load question */
$st = $pdo->prepare('SELECT * FROM questions WHERE id = ? LIMIT 1');
$st->execute([$qid]);
$q = $st->fetch();

if (!$q) {
    flash('Question not found.', 'danger');
    redirect(SITE_URL . '/admin/questions.php');
}

/* Current grade — take first non-empty grade col */
$current_grade = '';
foreach ($GRADE_COLS as $gc) if (!empty($q[$gc])) { $current_grade = $q[$gc]; break; }

/* Current answer as letter */
$ansVal = null;
if ($use_index) $ansVal = $q[$C_CORRECT_IX] ?? null;
else            $ansVal = $q[$C_CORRECT] ?? null;
$current_letter = is_numeric($ansVal) ? ['A','B','C','D'][(int)$ansVal] ?? '' : strtoupper((string)$ansVal);

/* Load subjects */
$subjects = [];
try {
    $sc = $pdo->query('DESCRIBE subjects')->fetchAll(PDO::FETCH_COLUMN);
    $S_NAME = in_array('name', $sc, true) ? 'name' : 'id';
    $S_GRD  = in_array('class_level', $sc, true) ? 'class_level'
            : (in_array('grade', $sc, true) ? 'grade' : null);
    $sql = "SELECT id, $S_NAME AS name" . ($S_GRD ? ", $S_GRD AS grade" : "") . " FROM subjects";
    $subjects = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {}

/* Handle POST */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qtext  = trim($_POST['question_text'] ?? '');
    $a      = trim($_POST['option_a'] ?? '');
    $b      = trim($_POST['option_b'] ?? '');
    $c      = trim($_POST['option_c'] ?? '');
    $d      = trim($_POST['option_d'] ?? '');
    $ansRaw = strtoupper(trim($_POST['correct'] ?? ''));
    $exp    = trim($_POST['explanation'] ?? '');
    $grade  = trim($_POST['grade'] ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 0);

    if ($qtext === '' || $a === '' || $b === '' || $c === '' || $d === '') {
        $error = 'Fill in all fields.';
    } elseif (!in_array($ansRaw, ['A','B','C','D'], true)) {
        $error = 'Choose the correct answer.';
    } elseif ($grade === '') {
        $error = 'Choose a grade.';
    } else {
        $db_ans = $use_index ? (string)['A'=>0,'B'=>1,'C'=>2,'D'=>3][$ansRaw] : $ansRaw;

        $fields = [
            $C_QUESTION => $qtext,
            $C_OPT_A => $a, $C_OPT_B => $b, $C_OPT_C => $c, $C_OPT_D => $d,
        ];
        if ($use_index) $fields[$C_CORRECT_IX] = $db_ans;
        else            $fields[$C_CORRECT]    = $db_ans;
        if ($C_EXPLAIN) $fields[$C_EXPLAIN] = $exp;
        if ($C_SUBJECT && $subject_id > 0) $fields[$C_SUBJECT] = $subject_id;

        /* Set every grade column to the new grade */
        foreach ($GRADE_COLS as $gc) $fields[$gc] = $grade;

        $sets = []; $vals = [];
        foreach ($fields as $k => $v) { $sets[] = "$k = ?"; $vals[] = $v; }
        $vals[] = $qid;

        try {
            $pdo->prepare('UPDATE questions SET ' . implode(', ', $sets) . ' WHERE id = ?')
                ->execute($vals);
            flash('✏️ Question #' . $qid . ' updated.', 'success');
            redirect(SITE_URL . '/admin/questions.php');
        } catch (Exception $ex) {
            $error = 'Error: ' . $ex->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width:800px">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold mb-0" style="color:#0C1E7F">✏️ Edit Question #<?= $qid ?></h3>
    <a href="<?= SITE_URL ?>/admin/questions.php" class="btn btn-outline-secondary btn-sm">
      ← Back
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card p-4 shadow-sm" style="border-radius:18px">

    <?php if ($C_SUBJECT): ?>
      <div class="mb-3">
        <label class="form-label fw-bold">Subject</label>
        <select name="subject_id" class="form-select" required>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>"
                    <?= (int)($q[$C_SUBJECT] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
              <?= e($s['name']) ?><?= !empty($s['grade']) ? ' (' . e($s['grade']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label fw-bold">Grade</label>
      <select name="grade" class="form-select" required>
        <?php foreach (CLASS_LEVELS as $g): ?>
          <option value="<?= e($g) ?>" <?= $current_grade === $g ? 'selected' : '' ?>>
            <?= e($g) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Question</label>
      <textarea name="question_text" class="form-control" rows="3" required><?= e($q[$C_QUESTION] ?? '') ?></textarea>
    </div>

    <div class="row g-2">
      <?php foreach (['A'=>$C_OPT_A,'B'=>$C_OPT_B,'C'=>$C_OPT_C,'D'=>$C_OPT_D] as $L => $col): ?>
        <div class="col-md-6">
          <label class="form-label fw-bold">Option <?= $L ?></label>
          <input name="option_<?= strtolower($L) ?>" class="form-control"
                 value="<?= e($q[$col] ?? '') ?>" required>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-3">
      <label class="form-label fw-bold">Correct Answer</label>
      <div class="d-flex gap-2">
        <?php foreach (['A','B','C','D'] as $L): ?>
          <label class="border rounded-3 px-3 py-2 fw-bold"
                 style="cursor:pointer;<?= $current_letter === $L ? 'background:#dcfce7;border-color:#3A7728 !important' : '' ?>">
            <input type="radio" name="correct" value="<?= $L ?>"
                   <?= $current_letter === $L ? 'checked' : '' ?> required>
            <?= $L ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($C_EXPLAIN): ?>
      <div class="mt-3">
        <label class="form-label fw-bold">Explanation</label>
        <textarea name="explanation" class="form-control" rows="2"><?= e($q[$C_EXPLAIN] ?? '') ?></textarea>
      </div>
    <?php endif; ?>

    <button class="btn btn-primary w-100 py-3 fw-bold mt-4"
            style="border-radius:12px;background:linear-gradient(135deg,#0C1E7F,#1e40af);border:none">
      💾 Save Changes
    </button>
  </form>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>