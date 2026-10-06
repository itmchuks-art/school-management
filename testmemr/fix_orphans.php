<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Fix Orphans</title>";
echo "<style>body{font-family:system-ui;background:#f5f7ff;padding:20px;line-height:1.6}";
echo ".box{max-width:900px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}";
echo "h1{color:#1e40af}h2{color:#0C1E7F;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-top:24px}";
echo "code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}";
echo ".ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}.warn{color:#d97706;font-weight:600}";
echo "table{width:100%;border-collapse:collapse;margin:12px 0}";
echo "th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:13px}";
echo "th{background:#f9fafb}";
echo ".btn{background:#16a34a;color:#fff;padding:14px 28px;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;margin-top:12px}";
echo ".btn:hover{background:#15803d}";
echo "</style></head><body><div class='box'>";
echo "<h1>🔧 Fix Orphan Questions</h1>";

$fix_mode = isset($_GET['fix']) && $_GET['fix'] === '1';

/* ---------- Step 1: Find orphans ---------- */
$orphans = $pdo->query("
    SELECT q.id, q.subject_id, q.class_level, LEFT(q.question, 60) AS q_preview
    FROM questions q
    LEFT JOIN subjects s ON s.id = q.subject_id
    WHERE s.id IS NULL
    ORDER BY q.class_level, q.id DESC
")->fetchAll();

/* ---------- Step 2: Show all SES subjects ---------- */
$ses_subjects = $pdo->query("SELECT id, name, class_level FROM subjects WHERE name='SES' ORDER BY class_level")->fetchAll();
$all_subjects = $pdo->query("SELECT id, name, class_level FROM subjects ORDER BY class_level, name")->fetchAll();

echo "<h2>1. SES Subjects in your database</h2>";
echo "<table><tr><th>ID</th><th>Name</th><th>Grade</th></tr>";
foreach ($ses_subjects as $s) {
    echo "<tr><td><code>{$s['id']}</code></td><td>{$s['name']}</td><td>{$s['class_level']}</td></tr>";
}
echo "</table>";

echo "<h2>2. Orphan questions found: " . count($orphans) . "</h2>";

if (!$orphans) {
    echo "<p class='ok'>✅ No orphan questions. Everything is fine.</p>";
} else {
    echo "<p class='warn'>⚠️ These questions point to a subject_id that doesn't exist:</p>";
    echo "<table><tr><th>Question ID</th><th>Broken subject_id</th><th>Grade</th><th>Question</th></tr>";
    foreach (array_slice($orphans, 0, 30) as $o) {
        echo "<tr><td>{$o['id']}</td><td><code>{$o['subject_id']}</code></td>";
        echo "<td>" . htmlspecialchars($o['class_level']) . "</td>";
        echo "<td>" . htmlspecialchars($o['q_preview']) . "</td></tr>";
    }
    echo "</table>";

    /* ---------- Fix mode ---------- */
    if ($fix_mode) {
        echo "<h2>3. Fixing orphans…</h2>";

        /* Build a map: class_level → correct subject_id */
        $grade_map = [];
        foreach ($ses_subjects as $s) {
            $grade_map[$s['class_level']] = (int)$s['id'];
        }

        $fixed = 0;
        $still_broken = 0;

        foreach ($orphans as $o) {
            $grade = $o['class_level'];

            if (isset($grade_map[$grade])) {
                $new_id = $grade_map[$grade];
                $upd = $pdo->prepare('UPDATE questions SET subject_id=? WHERE id=?');
                $upd->execute([$new_id, (int)$o['id']]);
                $fixed++;
            } else {
                $still_broken++;
            }
        }

        echo "<p class='ok'>✅ Fixed <b>$fixed</b> orphan question(s).</p>";
        if ($still_broken > 0) {
            echo "<p class='warn'>⚠️ Still broken (unknown grade): <b>$still_broken</b></p>";
        }

        /* Recheck */
        $remaining = (int)$pdo->query("
            SELECT COUNT(*) FROM questions q
            LEFT JOIN subjects s ON s.id = q.subject_id
            WHERE s.id IS NULL
        ")->fetchColumn();

        if ($remaining === 0) {
            echo "<p style='background:#d1fae5;padding:16px;border-radius:8px;font-weight:700;color:#065f46'>";
            echo "🎉 All orphans fixed! <a href='admin/questions.php'>Go to Questions →</a>";
            echo "</p>";
        } else {
            echo "<p class='warn'>⚠️ $remaining orphan(s) still remain (probably not SES).</p>";
        }

    } else {
        echo "<p><b>Ready to auto-repair?</b></p>";
        echo "<p>This will match each orphan's grade to the correct SES subject and update it.</p>";
        echo "<a href='?fix=1'><button class='btn'>🔧 Fix All Orphans Now</button></a>";
    }
}

echo "<p style='background:#fef3c7;padding:12px;border-radius:8px;margin-top:24px'>";
echo "<b>⚠️ Delete this file when done:</b> <code>fix_orphans.php</code>";
echo "</p>";

echo "</div></body></html>";