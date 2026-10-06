<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Student Debug</title>";
echo "<style>body{font-family:system-ui;background:#f5f7ff;padding:20px;line-height:1.7}";
echo ".box{max-width:800px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}";
echo "h1{color:#1e40af}h2{color:#0C1E7F;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-top:24px}";
echo "code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}";
echo ".ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}.warn{color:#d97706;font-weight:600}";
echo "</style></head><body><div class='box'>";
echo "<h1>🔍 Student Debug</h1>";

/* 1. Try to connect */
echo "<h2>1. Database</h2>";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "<p class='ok'>✅ Connected</p>";
} catch (Throwable $e) {
    echo "<p class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></body></html>";
    exit;
}

/* 2. Check session */
echo "<h2>2. Session</h2>";
echo "<p>user_id: <code>" . ($_SESSION['user_id'] ?? 'NOT SET') . "</code></p>";
echo "<p>admin_id: <code>" . ($_SESSION['admin_id'] ?? 'NOT SET') . "</code></p>";
echo "<p>exam_type: <code>" . ($_SESSION['exam_type'] ?? 'NOT SET') . "</code></p>";

/* 3. Check users table columns */
echo "<h2>3. Users table columns</h2>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
    echo "<ul>";
    $needed = ['subscription_end','subscription_plan','subscribed_grades'];
    $found = [];
    foreach ($cols as $c) {
        $found[] = $c['Field'];
        echo "<li><code>{$c['Field']}</code> — {$c['Type']}</li>";
    }
    echo "</ul>";
    foreach ($needed as $n) {
        if (!in_array($n, $found, true)) {
            echo "<p class='err'>❌ Missing column: <code>{$n}</code></p>";
        }
    }
} catch (Throwable $e) {
    echo "<p class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
}

/* 4. Check payments table columns */
echo "<h2>4. Payments table columns</h2>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll();
    echo "<ul>";
    $found_p = [];
    foreach ($cols as $c) {
        $found_p[] = $c['Field'];
        echo "<li><code>{$c['Field']}</code> — {$c['Type']}</li>";
    }
    echo "</ul>";
    if (!in_array('subscribed_grades', $found_p, true)) {
        echo "<p class='err'>❌ Missing column: <code>subscribed_grades</code></p>";
    }
} catch (Throwable $e) {
    echo "<p class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
}

/* 5. Check subjects */
echo "<h2>5. Subjects</h2>";
$subs = $pdo->query("SELECT class_level, COUNT(*) AS cnt FROM subjects GROUP BY class_level")->fetchAll();
echo "<ul>";
foreach ($subs as $s) {
    echo "<li><code>{$s['class_level']}</code> — {$s['cnt']} subjects</li>";
}
echo "</ul>";

/* 6. List students */
echo "<h2>6. Students in database</h2>";
$students = $pdo->query("SELECT id, name, phone, class_level, subscription_plan, subscribed_grades FROM users ORDER BY id DESC LIMIT 10")->fetchAll();
if (!$students) {
    echo "<p class='warn'>No students registered yet.</p>";
} else {
    echo "<table style='width:100%;border-collapse:collapse;margin:8px 0'>";
    echo "<tr style='background:#f9fafb'><th style='padding:8px;text-align:left'>ID</th><th style='padding:8px;text-align:left'>Name</th><th style='padding:8px;text-align:left'>Grade</th><th style='padding:8px;text-align:left'>Plan</th><th style='padding:8px;text-align:left'>Grades</th></tr>";
    foreach ($students as $s) {
        echo "<tr><td style='padding:8px;border-bottom:1px solid #e5e7eb'>{$s['id']}</td>";
        echo "<td style='padding:8px;border-bottom:1px solid #e5e7eb'>" . htmlspecialchars($s['name']) . "</td>";
        echo "<td style='padding:8px;border-bottom:1px solid #e5e7eb'>" . htmlspecialchars($s['class_level']) . "</td>";
        echo "<td style='padding:8px;border-bottom:1px solid #e5e7eb'>" . htmlspecialchars($s['subscription_plan'] ?? '—') . "</td>";
        echo "<td style='padding:8px;border-bottom:1px solid #e5e7eb'>" . htmlspecialchars($s['subscribed_grades'] ?? '—') . "</td></tr>";
    }
    echo "</table>";
}

/* 7. Test each student page */
echo "<h2>7. Test files exist</h2>";
$files = [
    'student/dashboard.php',
    'student/subjects.php',
    'student/take_test.php',
    'student/choose_exam.php',
    'student/subscription.php',
    'student/profile.php',
    'student/result.php',
];
echo "<ul>";
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo "<li><code>{$f}</code> — <span class='ok'>✅ " . number_format(filesize($path)) . " bytes</span></li>";
    } else {
        echo "<li><code>{$f}</code> — <span class='err'>❌ MISSING</span></li>";
    }
}
echo "</ul>";

echo "<p style='background:#fef3c7;padding:12px;border-radius:8px;margin-top:24px'>";
echo "<b>⚠️ Delete this file when done:</b> <code>student_debug.php</code>";
echo "</p>";

echo "</div></body></html>";