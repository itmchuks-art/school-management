<?php
require_once __DIR__ . '/../includes/db.php';
require_admin();

$results = [];
$ran = false;
$csv_dir = __DIR__ . '/../csvs';

/* ============================================================
   Filename → target subject map
   Pattern matched against lowercase filename (without .csv)
   ============================================================ */
$map = [
    /* ---------- ENGLISH ---------- */
    'english_nat3'    => ['name' => 'English', 'grade' => 'NAT Grade 3'],
    'english_nat6'    => ['name' => 'English', 'grade' => 'NAT Grade 6'],
    'english_nat8'    => ['name' => 'English', 'grade' => 'NAT Grade 8'],
    'english_gabece'  => ['name' => 'English', 'grade' => 'GABECE'],

    /* ---------- MATHS ---------- */
    'maths_nat3'      => ['name' => 'Maths',   'grade' => 'NAT Grade 3'],
    'maths_nat6'      => ['name' => 'Maths',   'grade' => 'NAT Grade 6'],
    'maths_nat8'      => ['name' => 'Maths',   'grade' => 'NAT Grade 8'],
    'maths_gabece'    => ['name' => 'Maths',   'grade' => 'GABECE'],

    /* ---------- SCIENCE ---------- */
    'science_nat3'    => ['name' => 'Science', 'grade' => 'NAT Grade 3'],
    'science_nat6'    => ['name' => 'Science', 'grade' => 'NAT Grade 6'],
    'science_nat8'    => ['name' => 'Science', 'grade' => 'NAT Grade 8'],
    'science_gabece'  => ['name' => 'Science', 'grade' => 'GABECE'],

    /* ---------- SES ---------- */
    'ses_nat3'        => ['name' => 'SES',     'grade' => 'NAT Grade 3'],
    'ses_nat6'        => ['name' => 'SES',     'grade' => 'NAT Grade 6'],
    'ses_nat8'        => ['name' => 'SES',     'grade' => 'NAT Grade 8'],
    'ses_gabece'      => ['name' => 'SES',     'grade' => 'GABECE'],
];

/* ---------- Handle upload ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    $ran = true;

    if (!is_dir($csv_dir)) {
        $results[] = ['file' => '(folder missing)', 'status' => 'error',
                      'msg' => 'Create the folder: testmemr/csvs/'];
    } else {
        $files = glob($csv_dir . '/*.csv');
        sort($files);

        if (!$files) {
            $results[] = ['file' => '(no CSVs)', 'status' => 'error',
                          'msg' => 'No .csv files found in testmemr/csvs/'];
        }

        foreach ($files as $file) {
            $base = strtolower(basename($file, '.csv'));

            /* Find which pattern matches */
            $matched = null;
            foreach ($map as $pattern => $info) {
                if (strpos($base, $pattern) !== false) { $matched = $info; break; }
            }
            if (!$matched) {
                $results[] = ['file' => basename($file), 'status' => 'skip',
                              'msg' => 'Filename not recognised — rename to e.g. english_nat8_batch1.csv'];
                continue;
            }

            /* Find target subject in DB */
            $st = $pdo->prepare('SELECT id, name, class_level FROM subjects WHERE name=? AND class_level=? LIMIT 1');
            $st->execute([$matched['name'], $matched['grade']]);
            $subj = $st->fetch();
            if (!$subj) {
                $results[] = ['file' => basename($file), 'status' => 'error',
                              'msg' => "Subject not in database: {$matched['name']} — {$matched['grade']}. Add it in Subjects page first."];
                continue;
            }

            /* Read CSV */
            $fh = fopen($file, 'r');
            if (!$fh) {
                $results[] = ['file' => basename($file), 'status' => 'error', 'msg' => 'Cannot open file'];
                continue;
            }

            $inserted = 0; $dup = 0; $bad = 0; $row = 0;

            $ins = $pdo->prepare('INSERT INTO questions
                (subject_id, class_level, exam_type, year, question,
                 option_a, option_b, option_c, option_d, correct_index)
                VALUES (?,?,?,?,?,?,?,?,?,?)');
            $chk = $pdo->prepare('SELECT id FROM questions WHERE subject_id=? AND question=? LIMIT 1');

            while (($data = fgetcsv($fh)) !== false) {
                $row++;
                if ($row === 1 && !is_numeric($data[0] ?? '')) continue;
                if (count($data) < 10) { $bad++; continue; }

                $qtext = trim($data[4] ?? '');
                if ($qtext === '') { $bad++; continue; }

                $chk->execute([(int)$subj['id'], $qtext]);
                if ($chk->fetch()) { $dup++; continue; }

                try {
                    $ins->execute([
                        (int)$subj['id'],
                        $subj['class_level'],
                        trim($data[2] ?? 'NAT'),
                        trim($data[3] ?? ''),
                        $qtext,
                        trim($data[5] ?? ''), trim($data[6] ?? ''),
                        trim($data[7] ?? ''), trim($data[8] ?? ''),
                        (int)($data[9] ?? 0),
                    ]);
                    $inserted++;
                } catch (Throwable $e) { $bad++; }
            }
            fclose($fh);

            $results[] = [
                'file'     => basename($file),
                'status'   => 'ok',
                'subject'  => $subj['name'] . ' — ' . $subj['class_level'],
                'inserted' => $inserted,
                'dup'      => $dup,
                'bad'      => $bad,
            ];
        }
    }
}

/* ---------- Show what's waiting ---------- */
$pending = [];
if (is_dir($csv_dir)) {
    $pending = glob($csv_dir . '/*.csv');
    sort($pending);
}

include __DIR__ . '/../includes/header.php';
?>

<style>
  .up-banner {
    background: linear-gradient(135deg, #0C1E7F, #1e40af);
    color: #fff; border-radius: 18px; padding: 22px; margin-bottom: 20px;
    box-shadow: 0 10px 28px rgba(12,30,127,.25);
    position: relative; overflow: hidden;
  }
  .up-banner::after {
    content:""; position:absolute; right:-50px; top:-50px;
    width:160px; height:160px; background:rgba(255,255,255,.1); border-radius:50%;
  }
  .up-banner h3 { margin:0; font-weight:800; position:relative; z-index:2; }
  .up-banner p  { margin:4px 0 0; opacity:.9; position:relative; z-index:2; font-size:.9rem; }

  .big-btn {
    background: linear-gradient(135deg, #3A7728, #16a34a);
    color: #fff; border: none; border-radius: 14px;
    padding: 20px; width: 100%; font-size: 1.2rem; font-weight: 800;
    letter-spacing: 1px; box-shadow: 0 10px 26px rgba(58,119,40,.35);
    cursor: pointer; transition: transform .15s;
    margin-top: 10px;
  }
  .big-btn:hover { transform: translateY(-2px); }
  .big-btn:disabled { background: #9ca3af; cursor: not-allowed; box-shadow: none; }

  .file-list {
    background: #f8fafc; border-radius: 12px; padding: 14px;
    max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 13px;
    margin-bottom: 14px;
  }
  .file-list div { padding: 3px 0; border-bottom: 1px solid #e5e7eb; }
  .file-list div:last-child { border-bottom: none; }
</style>

<div class="up-banner">
  <h3>⚡ Auto Upload Questions</h3>
  <p>Put CSV files in <code>testmemr/csvs/</code>, then click the big green button below.</p>
</div>

<?php if ($ran): ?>
  <div class="card mb-4 border-0 shadow-sm">
    <div class="card-header" style="background:#0C1E7F;color:#fff;font-weight:700;">
      ✅ Import Results
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead style="background:#f8fafc">
          <tr>
            <th>File</th>
            <th>Subject</th>
            <th>Inserted</th>
            <th>Duplicates</th>
            <th>Bad rows</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><code style="font-size:11px"><?= e($r['file']) ?></code></td>
            <td><?= e($r['subject'] ?? '—') ?></td>
            <td>
              <?php if (isset($r['inserted'])): ?>
                <b style="color:#16a34a"><?= (int)$r['inserted'] ?></b>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= isset($r['dup']) ? (int)$r['dup'] : '—' ?></td>
            <td><?= isset($r['bad']) ? (int)$r['bad'] : '—' ?></td>
            <td>
              <?php if ($r['status'] === 'ok'): ?>
                <span class="badge bg-success">✅ OK</span>
              <?php elseif ($r['status'] === 'skip'): ?>
                <span class="badge bg-warning text-dark">⏭ Skipped</span>
              <?php else: ?>
                <span class="badge bg-danger">❌ Error</span>
              <?php endif; ?>
              <?php if (!empty($r['msg'])): ?>
                <div class="small text-muted"><?= e($r['msg']) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <a href="<?= SITE_URL ?>/admin/auto_upload.php" class="btn btn-outline-primary mb-4">
    🔄 Upload more files
  </a>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header" style="background:#f8fafc;font-weight:700;">
    📁 CSV files found in <code>testmemr/csvs/</code>:
  </div>
  <div class="card-body">
    <?php if (!$pending): ?>
      <div class="alert alert-warning mb-3">
        <b>No CSV files yet.</b><br>
        Create folder <code>testmemr/csvs/</code>, then drop your CSV files inside.
      </div>
    <?php else: ?>
      <div class="file-list">
        <?php foreach ($pending as $p): ?>
          <div>📄 <?= e(basename($p)) ?> <span class="text-muted">(<?= number_format(filesize($p)) ?> bytes)</span></div>
        <?php endforeach; ?>
      </div>
      <p class="text-muted small">
        Found <b><?= count($pending) ?></b> file(s). Ready to import.
      </p>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="run" value="1">
      <button class="big-btn" <?= !$pending ? 'disabled' : '' ?>>
        🚀 IMPORT ALL FILES NOW
      </button>
    </form>

    <p class="text-muted small mt-3 mb-0">
      The script looks at each filename and matches it to a subject.
      Files with unrecognised names are skipped.
      Duplicate questions are skipped automatically.
    </p>
  </div>
</div>

<!-- Recognised filename patterns -->
<div class="card border-0 shadow-sm mt-4">
  <div class="card-header" style="background:#f8fafc;font-weight:700;">
    📖 Recognised Filename Patterns
  </div>
  <div class="card-body">
    <p class="small text-muted">Name your CSV files using these patterns (lowercase):</p>
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <b>English:</b>
        <ul class="small mb-2">
          <li><code>english_nat3_batch1.csv</code> → English — NAT Grade 3</li>
          <li><code>english_nat6_batch1.csv</code> → English — NAT Grade 6</li>
          <li><code>english_nat8_batch1.csv</code> → English — NAT Grade 8</li>
          <li><code>english_gabece_batch1.csv</code> → English — GABECE</li>
        </ul>
        <b>Maths:</b>
        <ul class="small mb-2">
          <li><code>maths_nat3_batch1.csv</code> → Maths — NAT Grade 3</li>
          <li><code>maths_nat6_batch1.csv</code> → Maths — NAT Grade 6</li>
          <li><code>maths_nat8_batch1.csv</code> → Maths — NAT Grade 8</li>
          <li><code>maths_gabece_batch1.csv</code> → Maths — GABECE</li>
        </ul>
      </div>
      <div class="col-12 col-md-6">
        <b>Science:</b>
        <ul class="small mb-2">
          <li><code>science_nat3_batch1.csv</code> → Science — NAT Grade 3</li>
          <li><code>science_nat6_batch1.csv</code> → Science — NAT Grade 6</li>
          <li><code>science_nat8_batch1.csv</code> → Science — NAT Grade 8</li>
          <li><code>science_gabece_batch1.csv</code> → Science — GABECE</li>
        </ul>
        <b>SES:</b>
        <ul class="small mb-0">
          <li><code>ses_nat3_batch1.csv</code> → SES — NAT Grade 3</li>
          <li><code>ses_nat6_batch1.csv</code> → SES — NAT Grade 6</li>
          <li><code>ses_nat8_batch1.csv</code> → SES — NAT Grade 8</li>
          <li><code>ses_gabece_batch1.csv</code> → SES — GABECE</li>
        </ul>
      </div>
    </div>
    <p class="text-muted small mt-3 mb-0">
      <b>Note:</b> the "batch1" part can be anything — the importer only looks for the <code>subject_grade</code> prefix.
    </p>
  </div>
</div>

<div class="mt-3">
  <a href="<?= SITE_URL ?>/admin/index.php" class="btn btn-link">← Back to Dashboard</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>