<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

/* Refresh user */
$st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([(int)$user['id']]);
$user = $st->fetch();

/* ⚠️ If subscription has expired — send them to the no-subscription page */
if (!is_subscribed($user)) {
    redirect(SITE_URL . '/student/no_subscription.php');
}

$active_grades = [];
if (!empty($user['subscribed_grades'])) {
    $active_grades = array_map('trim', explode(',', $user['subscribed_grades']));
    $active_grades = array_values(array_filter($active_grades));
}
if (($user['grade'] ?? '') === 'ALL' || strpos($user['subscription_plan'] ?? '', 'all_') === 0) {
    $active_grades = CLASS_LEVELS;
}
if (empty($active_grades) && !empty($user['grade']) && $user['grade'] !== 'ALL') {
    $active_grades = [$user['grade']];
}

/* Retake */
if (!empty($_GET['retake']) && !empty($active_grades)) {
    $retake_id = (int)$_GET['retake'];
    $placeholders = implode(',', array_fill(0, count($active_grades), '?'));
    try {
        $chk = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND (class_level IN ($placeholders) OR grade IN ($placeholders)) LIMIT 1");
        $chk->execute(array_merge([$retake_id], $active_grades, $active_grades));
        if ($chk->fetch()) {
            unset($_SESSION['exam_questions'],$_SESSION['exam_subject'],$_SESSION['exam_started'],$_SESSION['exam_subject_row']);
            $_SESSION['exam_subject_id'] = $retake_id;
            $_SESSION['exam_count']      = 10;
            $_SESSION['exam_minutes']    = 0;
            redirect(SITE_URL . '/student/take_test.php?start=1');
        }
    } catch (Exception $e) {}
}

$subjects = [];
try {
    if (!empty($active_grades)) {
        $in = implode(',', array_fill(0, count($active_grades), '?'));
        $st = $pdo->prepare("SELECT * FROM subjects WHERE class_level IN ($in) OR grade IN ($in) ORDER BY class_level, name");
        $st->execute(array_merge($active_grades, $active_grades));
        $subjects = $st->fetchAll();
    }
    if (empty($subjects)) {
        $subjects = $pdo->query('SELECT * FROM subjects ORDER BY name')->fetchAll();
    }
} catch (Exception $e) {}

foreach ($subjects as &$s) $s['grade'] = $s['class_level'] ?? ($s['grade'] ?? '');
unset($s);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $count      = max(5, min(50, (int)($_POST['count'] ?? 10)));
    $minutes    = max(0, min(25, (int)($_POST['minutes'] ?? 0)));
    if ($subject_id <= 0) {
        $error = 'Choose a subject.';
    } else {
        unset($_SESSION['exam_questions'],$_SESSION['exam_subject'],$_SESSION['exam_started'],$_SESSION['exam_subject_row'],$_SESSION['exam_ends_at']);
        $_SESSION['exam_subject_id'] = $subject_id;
        $_SESSION['exam_count']      = $count;
        $_SESSION['exam_minutes']    = $minutes;
        redirect(SITE_URL . '/student/take_test.php?start=1');
    }
}

$past = [];
try {
    $ph = $pdo->prepare('SELECT a.*, s.name AS subject_name FROM attempts a LEFT JOIN subjects s ON s.id = a.subject_id WHERE a.user_id = ? ORDER BY a.id DESC LIMIT 10');
    $ph->execute([(int)$user['id']]);
    $past = $ph->fetchAll();
} catch (Exception $e) {}

function list_grade(int $p): array {
    if ($p >= 90) return ['A+', '#3A7728'];
    if ($p >= 80) return ['A',  '#3A7728'];
    if ($p >= 70) return ['B',  '#3A7728'];
    if ($p >= 60) return ['C',  '#d97706'];
    if ($p >= 50) return ['D',  '#d97706'];
    if ($p >= 40) return ['E',  '#CE1126'];
    return              ['F',  '#CE1126'];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
  body{background:radial-gradient(circle at 20% 10%,#1e3a8a 0%,#0C1E7F 40%,#050b33 100%);min-height:100vh;color:#fff;}
  .bg-blob{position:fixed;border-radius:50%;opacity:.35;filter:blur(2px);animation:blobfloat 12s ease-in-out infinite;z-index:0;pointer-events:none;}
  .blob-1{width:340px;height:340px;background:radial-gradient(circle at 30% 30%,#CE1126,#7a0b18);top:-100px;left:-100px;}
  .blob-2{width:260px;height:260px;background:radial-gradient(circle at 30% 30%,#3A7728,#1a3a12);bottom:-90px;right:-90px;animation-delay:3s;}
  @keyframes blobfloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-40px) rotate(25deg)}}

  .glass{background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,245,255,.94));border-radius:24px;padding:28px;box-shadow:0 30px 60px -15px rgba(0,0,0,.5),0 12px 24px -8px rgba(12,30,127,.4),inset 0 2px 0 rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.7);color:#0f172a;}

  .form-select-3d{width:100%;padding:14px 16px;border:2px solid #d1d5db;border-radius:12px;font-size:1rem;background:#fff;transition:all .2s;box-shadow:inset 0 2px 4px rgba(0,0,0,.04);}
  .form-select-3d:focus{outline:none;border-color:#0C1E7F;box-shadow:inset 0 2px 4px rgba(0,0,0,.04),0 0 0 4px rgba(12,30,127,.15);}

  .label-3d{font-weight:900;font-size:.78rem;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:block;}

  .btn-3d{display:inline-block;padding:16px 24px;border-radius:14px;font-weight:900;border:none;color:#fff;transition:transform .1s,box-shadow .1s;width:100%;font-size:1.05rem;cursor:pointer;}
  .btn-3d.green{background:linear-gradient(135deg,#3A7728,#16a34a);box-shadow:0 8px 0 #1a3a12,0 14px 28px rgba(58,119,40,.4);}
  .btn-3d.green:active{transform:translateY(6px);box-shadow:0 2px 0 #1a3a12;}
  .btn-3d.blue{background:linear-gradient(135deg,#0C1E7F,#1e40af);box-shadow:0 5px 0 #050b33,0 8px 16px rgba(12,30,127,.3);font-size:.8rem;width:auto;padding:8px 16px;border-radius:10px;text-decoration:none;}
  .btn-3d.blue:active{transform:translateY(3px);box-shadow:0 2px 0 #050b33;}

  .hist-3d{background:linear-gradient(135deg,#f8faff,#eff6ff);border-radius:14px;padding:14px 16px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 6px 14px rgba(12,30,127,.1);border-left:5px solid #0C1E7F;gap:8px;flex-wrap:wrap;}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4" style="max-width:800px;position:relative;z-index:2;">

  <div class="text-center mb-4">
    <h2 class="fw-bold text-white mb-1">📝 Write a Test</h2>
    <p style="color:rgba(255,255,255,.8);">Choose a subject, number of questions, and how long.</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (empty($subjects)): ?>
    <div class="glass text-center">⚠️ No subjects available.</div>
  <?php else: ?>
    <form method="post" class="glass mb-4">
      <?php if (count($active_grades) > 1): ?>
        <div class="mb-4">
          <label class="label-3d">Grade</label>
          <select id="gradePicker" class="form-select-3d">
            <?php foreach ($active_grades as $i => $g): ?>
              <option value="<?= e($g) ?>" <?= $i === 0 ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <div class="mb-4">
          <label class="label-3d">Grade</label>
          <div style="padding:14px 16px;background:#f1f5f9;border:2px solid #e5e7eb;border-radius:12px;font-weight:800;">🎓 <?= e($active_grades[0] ?? '') ?></div>
        </div>
      <?php endif; ?>

      <div class="mb-4">
        <label class="label-3d">Subject</label>
        <select name="subject_id" id="subjectPicker" class="form-select-3d" required>
          <option value="" data-grade="">-- Choose subject --</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>" data-grade="<?= e($s['grade'] ?? '') ?>"><?= e($s['name']) ?><?= !empty($s['grade']) ? ' (' . e($s['grade']) . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-4">
        <label class="label-3d">📊 Number of questions</label>
        <select name="count" class="form-select-3d">
          <option value="5">5 questions</option>
          <option value="10" selected>10 questions</option>
          <option value="15">15 questions</option>
          <option value="20">20 questions</option>
          <option value="25">25 questions</option>
          <option value="30">30 questions</option>
          <option value="35">35 questions</option>
          <option value="40">40 questions</option>
          <option value="45">45 questions</option>
          <option value="50">50 questions</option>
        </select>
      </div>

      <div class="mb-4">
        <label class="label-3d">⏱️ Time limit</label>
        <select name="minutes" class="form-select-3d">
          <option value="0" selected>🚫 No timer — take your time</option>
          <option value="5">5 minutes</option>
          <option value="10">10 minutes</option>
          <option value="15">15 minutes</option>
          <option value="20">20 minutes</option>
          <option value="25">25 minutes</option>
        </select>
      </div>

      <button class="btn-3d green">🚀 Start Test</button>
    </form>

    <script>
    (function () {
      var g = document.getElementById('gradePicker');
      var s = document.getElementById('subjectPicker');
      if (!s) return;
      function filter() {
        var grade = g ? g.value : <?= json_encode($active_grades[0] ?? '') ?>;
        s.querySelectorAll('option').forEach(function (o) {
          if (!o.value) return;
          var og = o.getAttribute('data-grade') || '';
          var show = (grade === '' || og === grade);
          o.hidden = !show; o.disabled = !show;
        });
        if (s.selectedOptions[0] && s.selectedOptions[0].disabled) {
          s.value = '';
          var first = s.querySelector('option:not([disabled]):not([value=""])');
          if (first) s.value = first.value;
        }
      }
      if (g) g.addEventListener('change', filter);
      filter();
    })();
    </script>
  <?php endif; ?>

  <?php if ($past): ?>
    <div class="glass">
      <h5 class="mb-3" style="color:#0C1E7F;font-weight:900;">📜 Recent Tests</h5>
      <?php foreach ($past as $a):
        [$letter, $col] = list_grade((int)$a['percent']);
      ?>
        <div class="hist-3d" style="border-left-color:<?= $col ?>;">
          <div>
            <div class="fw-bold"><?= e($a['subject_name'] ?? '—') ?></div>
            <div style="font-size:.72rem;color:#6b7280;"><?= e($a['grade']) ?> · <?= e(fmt_date($a['finished_at'] ?? $a['started_at'])) ?></div>
          </div>
          <div class="text-end">
            <div class="fw-bold" style="color:<?= $col ?>;font-size:1.1rem;"><?= $letter ?> · <?= (int)$a['percent'] ?>%</div>
            <div style="font-size:.72rem;color:#6b7280;"><?= (int)$a['score'] ?>/<?= (int)$a['total'] ?></div>
          </div>
          <a href="?retake=<?= (int)$a['subject_id'] ?>" class="btn-3d blue">🔁 Retake</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>