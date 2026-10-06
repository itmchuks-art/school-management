<?php
require_once __DIR__ . '/../includes/db.php';
$user = require_student($pdo);

$st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([(int)$user['id']]);
$user = $st->fetch();

/* ⚠️ If subscription has expired — send them to the no-subscription page */
if (!is_subscribed($user)) {
    redirect(SITE_URL . '/student/no_subscription.php');
}

/* ---------- Helpers ---------- */
function correct_letter($val, bool $one_based = false): string {
    if ($val === null || $val === '') return '';
    if (is_numeric($val)) {
        $n = (int)$val;
        if ($one_based) return [1=>'A',2=>'B',3=>'C',4=>'D'][$n] ?? '';
        return [0=>'A',1=>'B',2=>'C',3=>'D'][$n] ?? '';
    }
    $val = strtoupper(trim((string)$val));
    return in_array($val, ['A','B','C','D'], true) ? $val : '';
}

function grade_letter(int $p): array {
    if ($p >= 90) return ['letter'=>'A+','meaning'=>'Excellent','color'=>'#3A7728'];
    if ($p >= 80) return ['letter'=>'A', 'meaning'=>'Very Good','color'=>'#3A7728'];
    if ($p >= 70) return ['letter'=>'B', 'meaning'=>'Good',     'color'=>'#16a34a'];
    if ($p >= 60) return ['letter'=>'C', 'meaning'=>'Fair',     'color'=>'#d97706'];
    if ($p >= 50) return ['letter'=>'D', 'meaning'=>'Pass',     'color'=>'#d97706'];
    if ($p >= 40) return ['letter'=>'E', 'meaning'=>'Weak',     'color'=>'#CE1126'];
    return              ['letter'=>'F', 'meaning'=>'Fail',     'color'=>'#CE1126'];
}

/* ---------- Detect columns ---------- */
$cols = $pdo->query('DESCRIBE questions')->fetchAll(PDO::FETCH_COLUMN);
$pick = function ($list) use ($cols) {
    foreach ($list as $c) if (in_array($c, $cols, true)) return $c;
    return null;
};
$C_Q      = $pick(['question_text','question','q','text']);
$C_A      = $pick(['option_a','a']);
$C_B      = $pick(['option_b','b']);
$C_C      = $pick(['option_c','c']);
$C_D      = $pick(['option_d','d']);
$C_ANS    = $pick(['correct_option','correct','answer','ans']);
$C_ANS_IX = $pick(['correct_index']);
$C_SUB    = $pick(['subject_id','subject']);

$use_index = ($C_ANS_IX !== null);
$C_ANSWER  = $use_index ? $C_ANS_IX : $C_ANS;

$one_based = false;
if ($use_index) {
    try {
        $mn = (int)$pdo->query("SELECT MIN($C_ANS_IX) FROM questions")->fetchColumn();
        $mx = (int)$pdo->query("SELECT MAX($C_ANS_IX) FROM questions")->fetchColumn();
        $one_based = ($mn >= 1 && $mx <= 4);
    } catch (Exception $e) {}
}

/* ============================================================
   SUBMIT
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    $questions = $_SESSION['exam_questions'] ?? [];
    $subject   = $_SESSION['exam_subject_row'] ?? null;

    if (empty($questions)) {
        flash('Your test session expired.', 'warning');
        redirect(SITE_URL . '/student/exam.php');
    }

    $auto_submitted = !empty($_POST['auto_submitted']);
    $score = 0; $total = count($questions); $answers = []; $failed = [];

    foreach ($questions as $q) {
        $qid     = (int)$q['id'];
        $picked  = strtoupper(trim($_POST['q'][$qid] ?? ''));
        $correct = correct_letter($q[$C_ANSWER] ?? null, $one_based);
        $answers[$qid] = ['picked' => $picked, 'correct' => $correct];

        if ($picked !== '' && $picked === $correct) {
            $score++;
        } else {
            $failed[] = [
                'qid' => $qid, 'question' => $q[$C_Q] ?? '',
                'a' => $q[$C_A] ?? '', 'b' => $q[$C_B] ?? '', 'c' => $q[$C_C] ?? '', 'd' => $q[$C_D] ?? '',
                'picked' => $picked,
            ];
        }
    }

    $percent = $total > 0 ? (int)round(($score / $total) * 100) : 0;
    $grade   = grade_letter($percent);

    try {
        $pdo->prepare('INSERT INTO attempts (user_id, subject_id, grade, score, total, percent, answers, finished_at) VALUES (?,?,?,?,?,?,?,NOW())')
            ->execute([(int)$user['id'], (int)($_SESSION['exam_subject'] ?? 0), (string)($subject['class_level'] ?? $subject['grade'] ?? ''), $score, $total, $percent, json_encode($answers)]);
    } catch (Exception $e) {}

    $_SESSION['last_result'] = [
        'score' => $score, 'total' => $total, 'percent' => $percent, 'grade' => $grade,
        'subject' => $subject, 'auto' => $auto_submitted, 'failed' => $failed,
    ];

    unset($_SESSION['exam_questions'],$_SESSION['exam_subject'],$_SESSION['exam_started'],$_SESSION['exam_subject_row'],$_SESSION['exam_subject_id'],$_SESSION['exam_count'],$_SESSION['exam_minutes'],$_SESSION['exam_ends_at'],$_SESSION['exam_one_based']);

    redirect(SITE_URL . '/student/take_test.php?result=1');
}

/* ============================================================
   RESULT PAGE
   ============================================================ */
$show_result = isset($_GET['result']) && !empty($_SESSION['last_result']);

if ($show_result) {
    $result  = $_SESSION['last_result'];
    $percent = (int)$result['percent'];
    $grade   = $result['grade'] ?? grade_letter($percent);
    $auto    = !empty($result['auto']);
    $failed  = $result['failed'] ?? [];

    include __DIR__ . '/../includes/header.php';
    ?>

    <canvas id="confettiCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999"></canvas>

    <div class="container py-4" style="max-width:900px;position:relative;z-index:2;">

      <?php
        if ($percent >= 90)      { $title='Outstanding! 🏆';    $msg='You nailed it!';            $emoji='🏆'; $sound='excellent'; $bg='#0C1E7F'; }
        elseif ($percent >= 75) { $title='Excellent work! 🌟';  $msg='Great job!';                $emoji='🌟'; $sound='excellent'; $bg='#3A7728'; }
        elseif ($percent >= 60) { $title='Good job! 👍';        $msg='Right track — practice more.'; $emoji='👍'; $sound='good';    $bg='#16a34a'; }
        elseif ($percent >= 40) { $title='Keep practising 📚';  $msg='Try again — you can do it!'; $emoji='📚'; $sound='okay';     $bg='#d97706'; }
        else                    { $title='Do not give up! 💪';  $msg='Every attempt makes you stronger.'; $emoji='💪'; $sound='tryagain'; $bg='#CE1126'; }

        $sid = (int)($result['subject']['id'] ?? 0);
      ?>

      <?php if ($auto): ?>
        <div class="alert alert-warning">⏰ <b>Time is up!</b> Your test was submitted automatically.</div>
      <?php endif; ?>

      <div class="card p-4 shadow-sm mb-4 border-0 text-center"
           style="border-radius:18px;background:<?= $bg ?>;color:#fff">
        <div class="display-1 mb-2"><?= $emoji ?></div>
        <div class="fs-2 fw-bold mb-1"><?= $title ?></div>

        <div class="my-3">
          <div class="d-inline-flex align-items-center justify-content-center"
               style="width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.18);border:6px solid #fff;box-shadow:0 12px 36px rgba(0,0,0,.3)">
            <div style="font-size:5.5rem;font-weight:900;line-height:1;color:#fff;"><?= e($grade['letter']) ?></div>
          </div>
          <div class="fw-bold mt-3" style="font-size:1.35rem;"><?= e($grade['meaning']) ?></div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-4"><div class="small opacity-75">Score</div><div class="fs-3 fw-bold"><?= (int)$result['score'] ?>/<?= (int)$result['total'] ?></div></div>
          <div class="col-4"><div class="small opacity-75">Mark</div><div class="fs-3 fw-bold"><?= $percent ?>%</div></div>
          <div class="col-4"><div class="small opacity-75">Grade</div><div class="fs-3 fw-bold"><?= e($grade['letter']) ?></div></div>
        </div>

        <div class="mt-4 p-3 rounded-3" style="background:rgba(255,255,255,.15)">
          <div class="fw-bold mb-1">💬 Comment</div>
          <div><?= $msg ?></div>
        </div>
      </div>

      <?php if (!empty($failed)): ?>
        <div class="card p-4 mb-4 shadow-sm border-0" style="border-radius:16px">
          <h5 class="fw-bold mb-2" style="color:#CE1126;">❌ Questions You Failed <span class="badge bg-danger"><?= count($failed) ?></span></h5>
          <p class="text-muted small mb-3">Focus on these topics — study them and try again!</p>

          <?php foreach ($failed as $i => $f): ?>
            <div class="card p-3 mb-2 border-0" style="background:#fff5f5;border-left:5px solid #CE1126 !important;border-radius:12px">
              <div class="fw-bold mb-2"><span class="badge bg-danger">Q<?= $i + 1 ?></span> <?= e($f['question']) ?></div>
              <?php foreach (['A'=>'a','B'=>'b','C'=>'c','D'=>'d'] as $L => $key):
                $is_pick = ($f['picked'] === $L);
              ?>
                <div class="small" style="<?= $is_pick ? 'color:#CE1126;font-weight:700' : 'color:#4b5563' ?>">
                  <?= $L ?>. <?= e($f[$key]) ?><?php if ($is_pick): ?> <span class="badge bg-danger">❌ Your answer</span><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-success text-center">🎉 <b>Perfect!</b> You didn't fail any question.</div>
      <?php endif; ?>

      <div class="card p-4 mb-4 shadow-sm border-0 text-center"
           style="border-radius:18px;background:linear-gradient(135deg,#0C1E7F 0%,#3A7728 100%);color:#fff">
        <div class="display-4 mb-2">📝</div>
        <h3 class="fw-bold mb-2">Test Me More, Mr!</h3>
        <p class="mb-4" style="opacity:.9;font-size:.95rem;">Retake the test with fresh questions!</p>
        <div class="d-flex gap-2 flex-wrap justify-content-center">
          <?php if ($sid > 0): ?>
            <a href="<?= SITE_URL ?>/student/exam.php?retake=<?= $sid ?>" class="btn btn-lg fw-bold" style="border-radius:14px;background:#fff;color:#0C1E7F;border:none;padding:14px 28px;">🔁 Test Me More, Mr!</a>
          <?php endif; ?>
          <a href="<?= SITE_URL ?>/student/exam.php" class="btn btn-lg btn-outline-light fw-bold" style="border-radius:14px;padding:14px 28px;">📚 Try Another Subject</a>
        </div>
      </div>

      <div class="d-flex gap-2 mb-4 flex-wrap justify-content-center">
        <a href="<?= SITE_URL ?>/student/dashboard.php" class="btn btn-outline-secondary" style="border-radius:10px;">← Dashboard</a>
        <button class="btn btn-outline-primary" style="border-radius:10px;" onclick="replaySounds()">🔊 Replay Sounds</button>
      </div>
    </div>

    <script>
    (function () {
      const PERCENT  = <?= (int)$percent ?>;
      const FEEDBACK = <?= json_encode($sound) ?>;
      let audioCtx = null;
      function getCtx(){ if(!audioCtx) audioCtx=new (window.AudioContext||window.webkitAudioContext)(); if(audioCtx.state==='suspended') audioCtx.resume(); return audioCtx; }
      function tone(freq,start,dur,type='sine',vol=0.25){ const c=getCtx(); const o=c.createOscillator(); const g=c.createGain(); o.type=type; o.frequency.value=freq; g.gain.setValueAtTime(0,c.currentTime+start); g.gain.linearRampToValueAtTime(vol,c.currentTime+start+0.02); g.gain.exponentialRampToValueAtTime(0.001,c.currentTime+start+dur); o.connect(g); g.connect(c.destination); o.start(c.currentTime+start); o.stop(c.currentTime+start+dur); }
      function clap(start,vol=0.5){ const c=getCtx(); const d=0.15; const buf=c.createBuffer(1,c.sampleRate*d,c.sampleRate); const dt=buf.getChannelData(0); for(let i=0;i<dt.length;i++) dt[i]=(Math.random()*2-1)*Math.pow(1-i/dt.length,2); const s=c.createBufferSource(); s.buffer=buf; const g=c.createGain(); g.gain.value=vol; const f=c.createBiquadFilter(); f.type='bandpass'; f.frequency.value=1500; s.connect(f); f.connect(g); g.connect(c.destination); s.start(c.currentTime+start); }
      function flowerPop(start){ tone(1400,start,0.08,'sine',0.25); tone(2100,start+0.02,0.10,'triangle',0.18); }
      function playResult(){
        if(FEEDBACK==='excellent'){ [523.25,659.25,783.99,1046.50].forEach((f,i)=>tone(f,i*0.15,0.35,'sine',0.22)); [0,0.35,0.7,1.0,1.3].forEach(t=>clap(t,0.35)); [1.4,1.55,1.7].forEach(t=>flowerPop(t)); }
        else if(FEEDBACK==='good'){ tone(523.25,0,0.25,'sine',0.22); tone(659.25,0.18,0.35,'sine',0.22); clap(0.4,0.3); clap(0.65,0.3); flowerPop(0.9); }
        else if(FEEDBACK==='okay'){ tone(440,0,0.2,'sine',0.18); tone(554,0.18,0.3,'sine',0.18); flowerPop(0.6); }
        else { tone(330,0,0.25,'sine',0.15); tone(392,0.2,0.3,'sine',0.15); flowerPop(0.7); }
      }
      playResult();
      window.replaySounds = playResult;

      const canvas = document.getElementById('confettiCanvas');
      const ctx2d  = canvas.getContext('2d');
      let particles = [];
      function resize(){ canvas.width=window.innerWidth; canvas.height=window.innerHeight; }
      resize(); window.addEventListener('resize', resize);
      const FLOWERS = ['🌸','🌺','🌼','🌻','💐','✨','🎉','⭐','🌹','🏵️'];
      function spawn(x,y,n){ for(let i=0;i<n;i++){ const a=Math.random()*Math.PI*2; const s=3+Math.random()*6; particles.push({x,y,vx:Math.cos(a)*s,vy:Math.sin(a)*s-3,life:1,decay:0.008+Math.random()*0.008,size:20+Math.random()*20,rotation:Math.random()*Math.PI*2,rotationSpeed:(Math.random()-0.5)*0.2,emoji:FLOWERS[Math.floor(Math.random()*FLOWERS.length)]}); } }
      function draw(){ ctx2d.clearRect(0,0,canvas.width,canvas.height); particles.forEach(p=>{ p.x+=p.vx; p.y+=p.vy; p.vy+=0.18; p.vx*=0.99; p.life-=p.decay; p.rotation+=p.rotationSpeed; if(p.life>0){ ctx2d.save(); ctx2d.globalAlpha=p.life; ctx2d.translate(p.x,p.y); ctx2d.rotate(p.rotation); ctx2d.font=p.size+'px serif'; ctx2d.textAlign='center'; ctx2d.textBaseline='middle'; ctx2d.fillText(p.emoji,0,0); ctx2d.restore(); } }); particles=particles.filter(p=>p.life>0); requestAnimationFrame(draw); }
      let bursts=0; const maxBursts = PERCENT>=75?5:(PERCENT>=50?3:1);
      function launch(){ if(bursts>=maxBursts) return; bursts++; spawn(canvas.width*(0.2+Math.random()*0.6), canvas.height*(0.2+Math.random()*0.3), 40); if(bursts<maxBursts) setTimeout(launch,500); }
      setTimeout(launch,200); draw();
      setTimeout(()=>{ ctx2d.clearRect(0,0,canvas.width,canvas.height); particles=[]; },8000);
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php';
    exit;
}

/* ============================================================
   START NEW TEST
   ============================================================ */
if (isset($_GET['start']) || empty($_SESSION['exam_questions'])) {
    $subject_id = (int)($_SESSION['exam_subject_id'] ?? 0);
    $count      = (int)($_SESSION['exam_count'] ?? 10);
    $minutes    = (int)($_SESSION['exam_minutes'] ?? 0);

    if ($subject_id <= 0) {
        flash('Choose a subject first.', 'warning');
        redirect(SITE_URL . '/student/exam.php');
    }

    $st = $pdo->prepare("SELECT * FROM questions WHERE $C_SUB = ? ORDER BY RAND() LIMIT $count");
    $st->execute([$subject_id]);
    $qs = $st->fetchAll();

    if (empty($qs)) {
        flash('No questions available.', 'warning');
        redirect(SITE_URL . '/student/exam.php');
    }

    $_SESSION['exam_questions'] = $qs;
    $_SESSION['exam_subject']   = $subject_id;
    $_SESSION['exam_started']   = time();
    $_SESSION['exam_minutes']   = $minutes;
    $_SESSION['exam_ends_at']   = $minutes > 0 ? time() + ($minutes * 60) : null;

    $st = $pdo->prepare('SELECT * FROM subjects WHERE id = ? LIMIT 1');
    $st->execute([$subject_id]);
    $_SESSION['exam_subject_row'] = $st->fetch() ?: null;
}

$questions = $_SESSION['exam_questions'] ?? [];
$subject   = $_SESSION['exam_subject_row'] ?? null;
$minutes   = (int)($_SESSION['exam_minutes'] ?? 0);
$has_timer = ($minutes > 0 && !empty($_SESSION['exam_ends_at']));

if (empty($questions)) redirect(SITE_URL . '/student/exam.php');

$seconds_left = $has_timer ? max(0, (int)$_SESSION['exam_ends_at'] - time()) : 0;

include __DIR__ . '/../includes/header.php';
?>

<?php if ($has_timer): ?>
<style>
  .timer-bar{position:sticky;top:0;z-index:100;background:linear-gradient(135deg,#0C1E7F,#1e40af);color:#fff;border-radius:14px;padding:14px 18px;margin-bottom:16px;box-shadow:0 8px 24px rgba(12,30,127,.30);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
  .timer-bar.warning{background:linear-gradient(135deg,#d97706,#b45309);}
  .timer-bar.danger{background:linear-gradient(135deg,#CE1126,#a30d1e);animation:pulse 1s infinite;}
  @keyframes pulse{0%,100%{opacity:1;}50%{opacity:.85;}}
  .timer-value{font-size:1.8rem;font-weight:900;letter-spacing:2px;}
  .timer-label{font-size:.75rem;opacity:.8;text-transform:uppercase;letter-spacing:1px;}
</style>
<?php endif; ?>

<div class="container py-4" style="max-width:900px">

  <?php if ($has_timer): ?>
    <div class="timer-bar" id="timerBar">
      <div>
        <div class="timer-label">⏱️ Time Remaining</div>
        <div class="timer-value" id="timerValue">--:--</div>
      </div>
      <div class="text-end small">
        <div><?= count($questions) ?> questions</div>
        <div><?= e($subject['name'] ?? '') ?></div>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap">
      <div>📝 <b><?= e($subject['name'] ?? 'Test') ?></b> — <?= count($questions) ?> questions</div>
      <div class="small text-muted">🚫 No timer — take your time</div>
    </div>
  <?php endif; ?>

  <form method="post" id="examForm">
    <input type="hidden" name="auto_submitted" id="autoSubmitted" value="0">

    <?php foreach ($questions as $i => $q):
      $qid = (int)$q['id'];
    ?>
      <div class="card p-3 mb-3 border-0 shadow-sm">
        <div class="fw-bold mb-2"><span class="badge bg-secondary">Q<?= $i + 1 ?></span> <?= e($q[$C_Q] ?? '') ?></div>
        <div class="row g-2">
          <?php foreach (['A'=>$C_A,'B'=>$C_B,'C'=>$C_C,'D'=>$C_D] as $L => $col): ?>
            <div class="col-12 col-md-6">
              <label class="d-flex align-items-center gap-2 p-2 rounded-3 border" style="cursor:pointer">
                <input type="radio" name="q[<?= $qid ?>]" value="<?= $L ?>">
                <span><b><?= $L ?>.</b> <?= e($q[$col] ?? '') ?></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <button type="submit" name="submit_test" value="1"
            class="btn btn-success w-100 py-3 fw-bold"
            onclick="return confirm('Submit your answers?');"
            style="border-radius:12px;background:linear-gradient(135deg,#3A7728,#16a34a);border:none">
      ✅ Submit Test
    </button>

    <a href="<?= SITE_URL ?>/student/exam.php" class="btn btn-outline-secondary w-100 mt-2" style="border-radius:12px">✖ Cancel</a>
  </form>

</div>

<?php if ($has_timer): ?>
<script>
(function () {
  var secondsLeft = <?= (int)$seconds_left ?>;
  var timerEl     = document.getElementById('timerValue');
  var barEl       = document.getElementById('timerBar');
  var form        = document.getElementById('examForm');
  var autoFlag    = document.getElementById('autoSubmitted');
  var submitted   = false;

  function fmt(s) { var m = Math.floor(s / 60); var sec = s % 60; return (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec; }

  function tick() {
    if (submitted) return;
    timerEl.textContent = fmt(secondsLeft);
    if (secondsLeft <= 60) { barEl.classList.add('danger'); barEl.classList.remove('warning'); }
    else if (secondsLeft <= 180) { barEl.classList.add('warning'); barEl.classList.remove('danger'); }
    if (secondsLeft <= 0) { autoSubmit(); return; }
    secondsLeft--;
    setTimeout(tick, 1000);
  }

  function autoSubmit() {
    if (submitted) return;
    submitted = true;
    timerEl.textContent = '00:00';
    autoFlag.value = '1';
    form.submit();
  }

  window.addEventListener('beforeunload', function (e) { if (!submitted && secondsLeft > 0) { e.preventDefault(); e.returnValue = ''; } });
  form.addEventListener('submit', function () { submitted = true; });
  tick();
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>