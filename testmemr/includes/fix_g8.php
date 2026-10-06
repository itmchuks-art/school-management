<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Fix G8 Questions</title>";
echo "<style>body{font-family:system-ui;background:#f5f7ff;padding:20px;line-height:1.6}";
echo ".box{max-width:800px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}";
echo "h1{color:#1e40af}h2{color:#0C1E7F;border-bottom:2px solid #e5e7eb;padding-bottom:6px}";
echo "code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}";
echo ".ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}.warn{color:#d97706;font-weight:600}";
echo "table{width:100%;border-collapse:collapse;margin:12px 0}";
echo "th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}";
echo "th{background:#f9fafb}";
echo ".action-btn{background:#16a34a;color:#fff;padding:12px 24px;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;margin-top:12px}";
echo ".action-btn:hover{background:#15803d}";
echo "</style></head><body><div class='box'>";
echo "<h1>🔧 Fix NAT Grade 8 Questions</h1>";

$do_fix = isset($_GET['fix']) && $_GET['fix'] == '1';

/* ============ STEP 1: What class_levels exist for questions? ============ */
echo "<h2>1. All class_level values in the questions table</h2>";
$rows = $pdo->query("SELECT class_level, COUNT(*) AS total FROM questions GROUP BY class_level ORDER BY class_level")->fetchAll();
echo "<table><tr><th>class_level</th><th>Count</th></tr>";
foreach ($rows as $r) {
    $hl = (stripos($r['class_level'], '8') !== false) ? ' style="background:#fef3c7"' : '';
    echo "<tr{$hl}><td><code>" . htmlspecialchars($r['class_level']) . "</code></td><td>{$r['total']}</td></tr>";
}
echo "</table>";

/* ============ STEP 2: What does the subjects table say? ============ */
echo "<h2>2. Grade 8 subjects in the subjects table</h2>";
$subs = $pdo->query("SELECT id, name, class_level FROM subjects WHERE class_level LIKE '%8%' ORDER BY name")->fetchAll();
if (!$subs) {
    echo "<p class='err'>❌ No subjects found with class_level containing '8'!</p>";
} else {
    echo "<table><tr><th>ID</th><th>Name</th><th>class_level</th></tr>";
    foreach ($subs as $s) {
        echo "<tr><td>{$s['id']}</td><td>" . htmlspecialchars($s['name']) . "</td>";
        echo "<td><code>" . htmlspecialchars($s['class_level']) . "</code></td></tr>";
    }
    echo "</table>";
}

/* ============ STEP 3: Count questions per G8 subject ============ */
echo "<h2>3. Question count per Grade 8 subject</h2>";
$st = $pdo->query("SELECT s.id, s.name, s.class_level, 
    (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) AS total
    FROM subjects s WHERE s.class_level LIKE '%8%' ORDER BY s.name");
$g8subs = $st->fetchAll();
echo "<table><tr><th>ID</th><th>Subject</th><th>class_level</th><th>Questions</th></tr>";
foreach ($g8subs as $s) {
    $cls = (int)$s['total'] === 0 ? 'err' : 'ok';
    echo "<tr><td>{$s['id']}</td><td>" . htmlspecialchars($s['name']) . "</td>";
    echo "<td><code>" . htmlspecialchars($s['class_level']) . "</code></td>";
    echo "<td><span class='{$cls}'>{$s['total']}</span></td></tr>";
}
echo "</table>";

/* ============ STEP 4: Auto-fix ============ */
echo "<h2>4. Fix Actions</h2>";

if ($do_fix) {
    echo "<p class='ok'>✅ Running fixes...</p>";
    $fixed_any = false;

    /* Find all distinct class_level values containing '8' that are NOT exactly 'NAT Grade 8' */
    $bad = $pdo->query("SELECT DISTINCT class_level FROM questions 
                        WHERE class_level LIKE '%8%' 
                        AND class_level <> 'NAT Grade 8'
                        AND class_level NOT LIKE 'NAT Grade 8'")->fetchAll();

    foreach ($bad as $b) {
        $wrong = $b['class_level'];
        $upd = $pdo->prepare("UPDATE questions SET class_level = 'NAT Grade 8' WHERE class_level = ?");
        $upd->execute([$wrong]);
        $n = $upd->rowCount();
        echo "<p>✅ Changed <b>{$n}</b> question(s) from <code>" . htmlspecialchars($wrong) . "</code> → <code>NAT Grade 8</code></p>";
        $fixed_any = true;
    }

    /* Also fix any orphan questions - those not linked to any subject */
    $orphans = $pdo->query("SELECT COUNT(*) FROM questions q 
                            LEFT JOIN subjects s ON s.id = q.subject_id 
                            WHERE s.id IS NULL")->fetchColumn();
    if ($orphans > 0) {
        echo "<p class='warn'>⚠️ Found <b>{$orphans}</b> orphan questions (subject_id doesn't match any subject).</p>";
        echo "<p>These may need to be re-imported.</p>";
    }

    if (!$fixed_any) {
        echo "<p class='warn'>⚠️ No mismatched class_levels found.</p>";
    }

    /* Check G8 subjects again */
    echo "<h3>After fix:</h3>";
    $st = $pdo->query("SELECT s.id, s.name, s.class_level, 
        (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) AS total
        FROM subjects s WHERE s.class_level LIKE '%8%' ORDER BY s.name");
    echo "<table><tr><th>Subject</th><th>Questions</th></tr>";
    foreach ($st->fetchAll() as $s) {
        $cls = (int)$s['total'] === 0 ? 'err' : 'ok';
        echo "<tr><td>" . htmlspecialchars($s['name']) . "</td><td><span class='{$cls}'>{$s['total']}</span></td></tr>";
    }
    echo "</table>";

    echo "<p style='background:#d1fae5;padding:12px;border-radius:8px;margin-top:16px'>";
    echo "✅ Done! Now check the <a href='admin/subjects.php'>Subjects page</a> — G8 counts should be correct.";
    echo "</p>";

} else {
    /* Show the fix button */
    $bad = $pdo->query("SELECT DISTINCT class_level FROM questions 
                        WHERE class_level LIKE '%8%' 
                        AND class_level <> 'NAT Grade 8'
                        AND class_level NOT LIKE 'NAT Grade 8'")->fetchAll();

    if ($bad) {
        echo "<p class='warn'>⚠️ Found " . count($bad) . " wrong class_level value(s) for Grade 8:</p>";
        echo "<ul>";
        foreach ($bad as $b) {
            echo "<li><code>" . htmlspecialchars($b['class_level']) . "</code></li>";
        }
        echo "</ul>";
        echo "<p>Click the button below to fix them automatically:</p>";
        echo "<a href='?fix=1'><button class='action-btn'>🔧 Fix Now</button></a>";
    } else {
        echo "<p class='ok'>✅ No mismatched Grade 8 class_levels found.</p>";
        echo "<p>If G8 questions still show 0 on the Subjects page, the issue is the <b>subject_id</b> — questions aren't linked to the right G8 subject row.</p>";
        echo "<p>Send me a screenshot of Section 1, 2 and 3 above and I'll tell you the next step.</p>";
    }
}

echo "</div></body></html>";