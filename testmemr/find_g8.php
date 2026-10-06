<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Find G8 Questions</title>";
echo "<style>
body{font-family:system-ui;background:#f5f7ff;padding:20px;line-height:1.6}
.box{max-width:1000px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
h1{color:#1e40af}h2{color:#0C1E7F;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-top:24px}
code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}
.ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}.warn{color:#d97706;font-weight:600}
table{width:100%;border-collapse:collapse;margin:12px 0}
th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:13px}
th{background:#f9fafb}
.bad{background:#fee2e2}.good{background:#d1fae5}
</style></head><body><div class='box'>";
echo "<h1>🔍 Find G8 Questions — Where Did They Go?</h1>";

/* ============ 1. Search for one specific G8 question ============ */
echo "<h2>1. Search for a specific SES G8 question</h2>";

$search = 'total land area of The Gambia';
echo "<p>Searching for: <code>{$search}</code></p>";

$st = $pdo->prepare("SELECT q.id, q.subject_id, q.class_level, s.name AS subj_name, s.class_level AS subj_grade
                     FROM questions q
                     LEFT JOIN subjects s ON s.id = q.subject_id
                     WHERE q.question LIKE ?
                     LIMIT 10");
$st->execute(["%$search%"]);
$found = $st->fetchAll();

if (!$found) {
    echo "<p class='err'>❌ Not found. The question may not have been uploaded, OR the text is different.</p>";
} else {
    echo "<table><tr><th>Q ID</th><th>Stored subject_id</th><th>Question class_level</th><th>Linked to subject</th><th>Subject grade</th></tr>";
    foreach ($found as $f) {
        $bad = ($f['class_level'] !== $f['subj_grade']) ? 'bad' : 'good';
        echo "<tr class='{$bad}'>";
        echo "<td>{$f['id']}</td>";
        echo "<td>{$f['subject_id']}</td>";
        echo "<td><code>" . htmlspecialchars($f['class_level']) . "</code></td>";
        echo "<td>" . htmlspecialchars($f['subj_name'] ?? 'NONE') . "</td>";
        echo "<td><code>" . htmlspecialchars($f['subj_grade'] ?? 'NONE') . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p class='warn'>If the left and right columns don't match, the questions are filed under the wrong grade!</p>";
}

/* ============ 2. All questions per subject_id ============ */
echo "<h2>2. Question count for every subject</h2>";
$rows = $pdo->query("SELECT s.id, s.name, s.class_level,
                     (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) AS cnt
                     FROM subjects s ORDER BY s.id")->fetchAll();
echo "<table><tr><th>Subject ID</th><th>Name</th><th>Class Level</th><th>Questions</th></tr>";
foreach ($rows as $r) {
    echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['name']) . "</td>";
    echo "<td><code>" . htmlspecialchars($r['class_level']) . "</code></td>";
    echo "<td>" . (int)$r['cnt'] . "</td></tr>";
}
echo "</table>";

/* ============ 3. All distinct class_levels in questions ============ */
echo "<h2>3. Distinct class_level in questions table</h2>";
$distinct = $pdo->query("SELECT class_level, COUNT(*) AS cnt FROM questions GROUP BY class_level")->fetchAll();
echo "<table><tr><th>class_level</th><th>Total questions</th></tr>";
foreach ($distinct as $d) {
    echo "<tr><td><code>" . htmlspecialchars($d['class_level']) . "</code></td><td>" . (int)$d['cnt'] . "</td></tr>";
}
echo "</table>";

/* ============ 4. Find MISMATCHES between question class_level and subject class_level ============ */
echo "<h2>4. ⚠️ Mismatched questions (bad filing)</h2>";
echo "<p>These questions are inside a subject whose grade doesn't match the question's own grade.</p>";
$mismatch = $pdo->query("SELECT q.class_level AS q_grade, s.name AS subj_name, s.class_level AS subj_grade, COUNT(*) AS cnt
                         FROM questions q
                         LEFT JOIN subjects s ON s.id = q.subject_id
                         WHERE q.class_level <> s.class_level
                         GROUP BY q.class_level, s.name, s.class_level
                         ORDER BY cnt DESC
                         LIMIT 30")->fetchAll();
if (!$mismatch) {
    echo "<p class='ok'>✅ All questions correctly filed!</p>";
} else {
    echo "<table><tr><th>Question grade</th><th>Inside subject</th><th>Subject grade</th><th>Count</th></tr>";
    foreach ($mismatch as $m) {
        echo "<tr class='bad'>";
        echo "<td><code>" . htmlspecialchars($m['q_grade']) . "</code></td>";
        echo "<td>" . htmlspecialchars($m['subj_name'] ?? 'ORPHAN') . "</td>";
        echo "<td><code>" . htmlspecialchars($m['subj_grade'] ?? 'NONE') . "</code></td>";
        echo "<td>" . (int)$m['cnt'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p class='warn'>These are the questions that need to be moved to the correct subject.</p>";
}

echo "<p style='background:#fef3c7;padding:12px;border-radius:8px;margin-top:20px'>";
echo "<b>📸 Send me a screenshot of sections 1, 2, and 4</b> and I'll give you the exact fix.";
echo "</p>";

echo "</div></body></html>";