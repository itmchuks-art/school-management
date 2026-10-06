<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/login.php');
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

$SUBJECT_COLS = [];
foreach (['subject_id','subject'] as $sc) if (in_array($sc, $cols, true)) $SUBJECT_COLS[] = $sc;

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

/* Load subjects */
$subjects = [];
try {
    $sc = $pdo->query('DESCRIBE subjects')->fetchAll(PDO::FETCH_COLUMN);
    $S_NAME = in_array('name', $sc, true) ? 'name' : 'id';
    $S_GRD  = in_array('class_level', $sc, true) ? 'class_level'
            : (in_array('grade', $sc, true) ? 'grade' : null);
    $sql = "SELECT id, $S_NAME AS name" . ($S_GRD ? ", $S_GRD AS grade" : "")
         . " FROM subjects ORDER BY " . ($S_GRD ? "$S_GRD, " : "") . "$S_NAME";
    $subjects = $pdo->query($sql)->fetchAll();
} catch (Exception $e) { $subjects = []; }

/* Handle POST */
$error = '';
$saved = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qtext  = trim($_POST['question_text'] ?? '');
    $a      = trim($_POST['option_a'] ?? '');
    $b      = trim($_POST['option_b'] ?? '');
    $c      = trim($_POST['option_c'] ?? '');
    $d      = trim($_POST['option_d'] ?? '');
    $ansRaw = trim($_POST['correct'] ?? '');
    $exp    = trim($_POST['explanation'] ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 0);

    $grades = $_POST['grades'] ?? [];
    if (!is_array($grades)) $grades = [$grades];
    $grades = array_values(array_filter($grades, fn($g) => in_array($g, CLASS_LEVELS, true)));

    $db_ans = to_db_answer($ansRaw, $use_index);

    if ($qtext === '' || $a === '' || $b === '' || $c === '' || $d === '') {
        $error = 'Fill in the question and all 4 options.';
    } elseif ($db_ans === null) {
        $error = 'Choose the correct answer (A, B, C or D).';
    } elseif ($C_SUBJECT && $subject_id <= 0) {
        $error = 'Choose a subject.';
    } elseif (empty($grades)) {
        $error = 'Choose at least one grade.';
    } else {
        $fields_base = [
            $C_QUESTION => $qtext,
            $C_OPT_A => $a, $C_OPT_B => $b, $C_OPT_C => $c, $C_OPT_D => $d,
        ];
        foreach ($SUBJECT_COLS as $sc) $fields_base[$sc] = $subject_id;
        if ($use_index) $fields_base[$C_CORRECT_IX] = $db_ans;
        else            $fields_base[$C_CORRECT]    = $db_ans;
        if ($C_EXPLAIN) $fields_base[$C_EXPLAIN] = $exp;

        try {
            $pdo->beginTransaction();
            foreach ($grades as $g) {
                $fields = $fields_base;
                foreach ($GRADE_COLS as $gc) $fields[$gc] = $g;
                $keys = array_keys($fields);
                $ph   = implode(',', array_fill(0, count($keys), '?'));
                $pdo->prepare('INSERT INTO questions (' . implode(',', $keys) . ") VALUES ($ph)")
                    ->execute(array_values($fields));
                $saved++;
            }
            $pdo->commit();
            flash("✅ Question added to $saved grade(s).", 'success');
            redirect(SITE_URL . '/admin/add_question.php');
        } catch (Exception $ex) {
            $pdo->rollBack();
            $error = 'Database error: ' . $ex->getMessage();
        }
    }
}

/* Recently posted (last 10) */
$recent = [];
try {
    $sql = 'SELECT q.*, s.name AS subject_name FROM questions q'
         . ($C_SUBJECT ? ' LEFT JOIN subjects s ON s.id = q.' . $C_SUBJECT : '')
         . ' ORDER BY q.id DESC LIMIT 10';
    $recent = $pdo->query($sql)->fetchAll();
} catch (Exception $e) { $recent = []; }

include __DIR__ . '/../includes/header.php';
?>

<style>
  .grade-check{display:flex;align-items:center;gap:8px;padding:12px 16px;border:2px solid #e5e7eb;border-radius:12px;background:#fff;cursor:pointer;font-weight:700;transition:all .15s;}
  .grade-check:hover{border-color:#0C1E7F;background:#f8faff;}
  .grade-check input{width:20px;height:20px;cursor:pointer;}
  .grade-check.checked{border-color:#0C1E7F;background:linear-gradient(135deg,#eff6ff,#f0fdf4);box-shadow:0 4px 12px rgba(12,30,127,.12);}
  .opt-input{border:2px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:1rem;}
  .opt-input:focus{border-color:#0C1E7F;box-shadow:0 0 0 .2rem rgba(12,30,127,.15);}
  .correct-pick{display:inline-block;padding:8px 16px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;font-weight:800;margin-right:8px;transition:all .15s;}
  .correct-pick:hover{border-color:#3A7728;}
  .correct-pick input{display:none;}
  .correct-pick.selected{border-color:#3A7728;background:linear-gradient(135deg,#dcfce7,#bbf7d0);color:#14532d;}
  .warn-box{background:#fef3c7;border-left:4px solid #d97706;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-top:6px;}
  .warn-box ul{margin:4px 0 0;padding-left:20px;}
</style>

<div class="container py-4" style="max-width:900px">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="fw-bold mb-0" style="color:#0C1E7F">➕ Add Question Manually</h3>
    <div class="d-flex gap-2">
      <a href="<?= SITE_URL ?>/admin/questions.php" class="btn btn-outline-secondary btn-sm">
        ← All Questions
      </a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card p-4 shadow-sm mb-4" style="border-radius:18px">

    <h5 class="fw-bold mb-3" style="color:#0C1E7F">1️⃣ Subject &amp; Grade(s)</h5>

    <?php if ($C_SUBJECT): ?>
      <div class="mb-4">
        <label class="form-label fw-bold">Subject</label>
        <select name="subject_id" class="form-select form-select-lg" required>
          <option value="">-- Choose subject --</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= (isset($_POST['subject_id']) && (int)$_POST['subject_id'] === (int)$s['id']) ? 'selected' : '' ?>>
              <?= e($s['name']) ?><?= !empty($s['grade']) ? ' (' . e($s['grade']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="mb-4">
      <label class="form-label fw-bold">
        Post to Grade(s) <span class="badge bg-primary">tick 1 or more</span>
      </label>
      <div class="row g-2">
        <?php
        $grade_icons = ['NAT Grade 3'=>'🎒','NAT Grade 6'=>'📘','NAT Grade 8'=>'🎓','GABECE'=>'🏆'];
        $pre_checked = $_POST['grades'] ?? [];
        if (!is_array($pre_checked)) $pre_checked = [];
        foreach (CLASS_LEVELS as $g):
        ?>
          <div class="col-6 col-md-3">
            <label class="grade-check <?= in_array($g, $pre_checked, true) ? 'checked' : '' ?>">
              <input type="checkbox" name="grades[]" value="<?= e($g) ?>" class="grade-cb"
                     <?= in_array($g, $pre_checked, true) ? 'checked' : '' ?>>
              <span><?= $grade_icons[$g] ?? '🎓' ?></span>
              <span><?= e($g) ?></span>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <h5 class="fw-bold mb-3 mt-2" style="color:#0C1E7F">2️⃣ Question</h5>

    <div class="mb-3">
      <label class="form-label fw-bold">Question</label>
      <textarea name="question_text" id="qtext" class="form-control" rows="3" required
                oninput="checkText()"><?= e($_POST['question_text'] ?? '') ?></textarea>
      <div id="warnBox"></div>
    </div>

    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label fw-bold">Option A</label>
        <input name="option_a" id="optA" class="form-control opt-input" required
               value="<?= e($_POST['option_a'] ?? '') ?>" oninput="checkText()">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">Option B</label>
        <input name="option_b" id="optB" class="form-control opt-input" required
               value="<?= e($_POST['option_b'] ?? '') ?>" oninput="checkText()">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">Option C</label>
        <input name="option_c" id="optC" class="form-control opt-input" required
               value="<?= e($_POST['option_c'] ?? '') ?>" oninput="checkText()">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">Option D</label>
        <input name="option_d" id="optD" class="form-control opt-input" required
               value="<?= e($_POST['option_d'] ?? '') ?>" oninput="checkText()">
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label fw-bold">Correct Answer</label>
      <div class="d-flex flex-wrap gap-2">
        <?php
        $pre_correct = strtoupper($_POST['correct'] ?? '');
        foreach (['A','B','C','D'] as $L):
        ?>
          <label class="correct-pick <?= $pre_correct === $L ? 'selected' : '' ?>">
            <input type="radio" name="correct" value="<?= $L ?>" class="correct-cb" required
                   <?= $pre_correct === $L ? 'checked' : '' ?>>
            <?= $L ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($C_EXPLAIN): ?>
      <div class="mt-3">
        <label class="form-label fw-bold">Explanation (optional)</label>
        <textarea name="explanation" class="form-control" rows="2"><?= e($_POST['explanation'] ?? '') ?></textarea>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-success w-100 py-3 fw-bold mt-4"
            style="border-radius:12px;background:linear-gradient(135deg,#3A7728,#16a34a);border:none">
      ✅ Save Question
    </button>
  </form>

  <!-- ============================================================
       RECENTLY POSTED QUESTIONS
       ============================================================ -->
  <?php if (!empty($recent)): ?>
    <div class="card p-4 shadow-sm" style="border-radius:16px">
      <h5 class="fw-bold mb-3" style="color:#0C1E7F">
        🕐 Recently Posted
        <span class="badge bg-secondary">last 10</span>
      </h5>

      <?php foreach ($recent as $q):
        $qid = (int)$q['id'];
        $ansVal = null;
        if ($C_CORRECT_IX !== null && isset($q[$C_CORRECT_IX])) $ansVal = $q[$C_CORRECT_IX];
        elseif ($C_CORRECT !== null && isset($q[$C_CORRECT])) $ansVal = $q[$C_CORRECT];
        $corr = is_numeric($ansVal) ? ['A','B','C','D'][(int)$ansVal] ?? '' : strtoupper((string)$ansVal);

        $grade_val = '';
        foreach ($GRADE_COLS as $gc) if (!empty($q[$gc])) { $grade_val = $q[$gc]; break; }
      ?>
        <div class="border rounded-3 p-3 mb-2 bg-white">
          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div class="flex-grow-1">
              <div class="fw-bold">#<?= $qid ?> — <?= e($q[$C_QUESTION] ?? '') ?></div>
              <div class="small text-muted mb-2">
                <?= e($q['subject_name'] ?? '') ?>
                <?php if ($grade_val): ?> · <?= e($grade_val) ?><?php endif; ?>
              </div>
              <div class="small">
                <div <?= $corr==='A'?'class="fw-bold text-success"':'' ?>>A. <?= e($q[$C_OPT_A] ?? '') ?></div>
                <div <?= $corr==='B'?'class="fw-bold text-success"':'' ?>>B. <?= e($q[$C_OPT_B] ?? '') ?></div>
                <div <?= $corr==='C'?'class="fw-bold text-success"':'' ?>>C. <?= e($q[$C_OPT_C] ?? '') ?></div>
                <div <?= $corr==='D'?'class="fw-bold text-success"':'' ?>>D. <?= e($q[$C_OPT_D] ?? '') ?></div>
              </div>
            </div>
            <a href="<?= SITE_URL ?>/admin/edit_question.php?id=<?= $qid ?>"
               class="btn btn-sm btn-outline-primary">✏️ Edit</a>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="text-end mt-2">
        <a href="<?= SITE_URL ?>/admin/questions.php" class="btn btn-sm btn-link">
          View all questions →
        </a>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
/* ============================================================
   SPELLING / SENTENCE CHECKER
   ============================================================ */
function checkText() {
    var fields = {
        'Question':  document.getElementById('qtext').value,
        'Option A':  document.getElementById('optA').value,
        'Option B':  document.getElementById('optB').value,
        'Option C':  document.getElementById('optC').value,
        'Option D':  document.getElementById('optD').value,
    };

    var warnings = [];

    /* Common misspellings */
    var typos = {
        'recieve':'receive','seperate':'separate','definately':'definitely',
        'occured':'occurred','untill':'until','wich':'which','thier':'their',
        'alot':'a lot','becuase':'because','accomodation':'accommodation',
        'arguement':'argument','embarass':'embarrass','goverment':'government',
        'neccessary':'necessary','occassion':'occasion','publically':'publicly',
        'rythm':'rhythm','sucessful':'successful','tommorow':'tomorrow',
        'wether':'whether','war ':'way ','studing':'studying','useing':'using'
    };

    Object.entries(fields).forEach(function ([name, text]) {
        if (!text) return;
        var t = text.toLowerCase();

        /* Duplicate words: "the the" */
        var dup = text.match(/\b(\w+)\s+\1\b/gi);
        if (dup) warnings.push('<b>' + name + ':</b> repeated word "' + dup[0] + '"');

        /* Double spaces */
        if (/  +/.test(text)) warnings.push('<b>' + name + ':</b> has double spaces');

        /* Common typos */
        Object.entries(typos).forEach(function ([wrong, right]) {
            var re = new RegExp('\\b' + wrong.trim() + '\\b', 'i');
            if (re.test(text)) warnings.push('<b>' + name + ':</b> "' + wrong.trim() + '" → try "' + right.trim() + '"');
        });

        /* Leading/trailing whitespace */
        if (text !== text.trim()) warnings.push('<b>' + name + ':</b> has extra spaces at start/end');
    });

    /* Question-specific */
    var q = fields['Question'].trim();
    if (q && !/[?.!]$/.test(q)) {
        warnings.push('<b>Question:</b> ends without <code>?</code> or <code>.</code>');
    }
    if (q && q[0] && q[0] !== q[0].toUpperCase()) {
        warnings.push('<b>Question:</b> starts with a small letter — capitalise it?');
    }

    var box = document.getElementById('warnBox');
    if (warnings.length === 0) {
        box.innerHTML = '';
        return;
    }
    var html = '<div class="warn-box">⚠️ <b>Check these:</b><ul>';
    warnings.forEach(function (w) { html += '<li>' + w + '</li>'; });
    html += '</ul></div>';
    box.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', checkText);

(function () {
  document.querySelectorAll('.grade-cb').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var lab = cb.closest('.grade-check');
      if (lab) lab.classList.toggle('checked', cb.checked);
    });
  });

  document.querySelectorAll('.correct-cb').forEach(function (cb) {
    cb.addEventListener('change', function () {
      document.querySelectorAll('.correct-pick').forEach(function (p) {
        p.classList.remove('selected');
      });
      cb.closest('.correct-pick').classList.add('selected');
    });
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>