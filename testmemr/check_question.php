<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Check Question</title>";
echo "<style>body{font-family:system-ui;background:#f5f7ff;padding:16px;line-height:1.6}";
echo ".box{max-width:900px;margin:auto;background:#fff;padding:22px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}";
echo "h1{color:#1e40af;font-size:1.3rem}h2{color:#0C1E7F;font-size:1.05rem;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-top:22px}";
echo "code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}";
echo ".ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}.warn{color:#d97706;font-weight:600}";
echo "table{width:100%;border-collapse:collapse;margin:10px 0;font-size:13px}";
echo "th,td{padding:7px;border-bottom:1px solid #e5e7eb;text-align:left}";
echo "th{background:#f9fafb}";
echo ".box-item{background:#eff6ff;padding:10px 14px;border-radius:8px;margin:8px 0;font-size:13px}";
echo "</style></head><body><div class='box'>";
echo "<h1>🔍 Check Question Visibility</h1>";

/* 1. Your SES subjects */
echo "<h2>1. Your SES subjects</h2>";
$ses = $pdo->query("SELECT id, name, class_level FROM subjects WHERE LOWER(name)='ses' ORDER BY class_level")->fetchAll();
echo "<table><tr><th>ID</th><th>Name</th><th>Grade</th></tr>";
foreach ($ses as $s) {
    echo "<tr><td><code>{$s['id']}</code></td><td>{$s['name']}</td><td>{$s['class_level']}</td></tr>";
}
echo "</table>";

/* 2. All subjects */
echo "<h2>2. All subjects in your database</h2>";
$all = $pdo->query("SELECT id, name, class_level FROM subjects ORDER BY class_level, name")->fetchAll();
echo "<table><tr><th>ID</th><th>Name</th><th>Grade</th></tr>";
foreach ($all as $s) {
    echo "<tr><td><code>{$s['id']}</code></td><td>{$s['name']}</td><td>{$s['class_level']}</td></tr>";
}
echo "</table>";

/* 3. Latest 15 questions posted */
echo "<h2>3. Latest 15 questions in the database</h2>";
$qs = $pdo->query("
    SELECT q.id, q.subject_id, q.class_level, q.exam_type, LEFT(q.question, 55) AS q, s.name AS subj
    FROM questions q
    LEFT JOIN subjects s ON s.id = q.subject_id
    ORDER BY q.id DESC
    LIMIT 15
")->fetchAll();
echo "<table><tr><th>Q ID</th><th>Subject ID</th><th>Subject Name</th><th>Class Level</th><th>Exam Type</th><th>Question</th></tr>";
foreach ($qs as $q) {
    $bad = !$q['subj'] ? 'err' : 'ok';
    echo "<tr>";
    echo "<td>{$q['id']}</td>";
    echo "<td><code>{$q['subject_id']}</code></td>";
    echo "<td class='{$bad}'>" . htmlspecialchars($q['subj'] ?? '⚠️ ORPHAN') . "</td>";
    echo "<td>" . htmlspecialchars($q['class_level']) . "</td>";
    echo "<td>" . htmlspecialchars($q['exam_type']) . "</td>";
    echo "<td>" . htmlspecialchars($q['q']) . "</td>";
    echo "</tr>";
}
echo "</table>";

/* 4. Orphan count */
$orphans = (int)$pdo->query("
    SELECT COUNT(*) FROM questions q
    LEFT JOIN subjects s ON s.id = q.subject_id
    WHERE s.id IS NULL
")->fetchColumn();
echo "<h2>4. Orphan questions (broken subject_id)</h2>";
if ($orphans === 0) {
    echo "<p class='ok'>✅ Zero orphans. Everything is fine.</p>";
} else {
    echo "<p class='err'>❌ <b>{$orphans}</b> orphan questions found!</p>";
    echo "<p>Run this SQL to fix them:</p>";
    echo "<div class='box-item'><code>UPDATE questions q INNER JOIN subjects s ON LOWER(s.name)='ses' AND s.class_level=q.class_level SET q.subject_id=s.id WHERE q.subject_id NOT IN (SELECT id FROM subjects);</code></div>";
}

/* 5. Simulate a student test */
echo "<h2>5. Simulate what a student would see</h2>";
echo "<p class='warn'>Pick your grade to see how many questions would show:</p>";

foreach (['NAT Grade 3','NAT Grade 6','NAT Grade 8','GABECE'] as $g) {
    $rows = $pdo->prepare("
        SELECT s.name, COUNT(q.id) AS cnt
        FROM subjects s
        LEFT JOIN questions q ON q.subject_id = s.id AND q.class_level = ?
        WHERE s.class_level = ?
        GROUP BY s.id, s.name
        ORDER BY s.name
    ");
    $rows->execute([$g, $g]);
    $counts = $rows->fetchAll();
    echo "<div class='box-item'><b>🎓 {$g}</b><br>";
    if (!$counts) {
        echo "<span class='err'>No subjects for this grade.</span>";
    } else {
        foreach ($counts as $c) {
            $color = (int)$c['cnt'] > 0 ? 'ok' : 'warn';
            echo "{$c['name']}: <span class='{$color}'>{$c['cnt']} question(s)</span><br>";
        }
    }
    echo "</div>";
}

echo "<p style='background:#fef3c7;padding:12px;border-radius:8px;margin-top:20px'>";
echo "<b>⚠️ Delete this file when done:</b> <code>check_question.php</code>";
echo "</p>";

echo "</div></body></html>";