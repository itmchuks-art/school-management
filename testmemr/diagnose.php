<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Diagnose</title>";
echo "<style>body{font-family:system-ui;background:#f5f7ff;padding:20px;line-height:1.6}";
echo ".box{max-width:700px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}";
echo "h1{color:#1e40af}code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}";
echo ".ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}.warn{color:#d97706;font-weight:600}";
echo "ul{margin:8px 0}li{margin:4px 0}";
echo "</style></head><body><div class='box'>";
echo "<h1>🔧 Test Me Mr — Diagnostics</h1>";

/* 1. PHP + session */
echo "<h3>1. PHP & Session</h3>";
echo "<ul>";
echo "<li>PHP version: <code>" . PHP_VERSION . "</code> " . (PHP_VERSION_ID >= 80000 ? "<span class='ok'>✅</span>" : "<span class='err'>❌ Need 8.0+</span>") . "</li>";
session_start();
echo "<li>Session started: <span class='ok'>✅</span></li>";
echo "</ul>";

/* 2. File check */
echo "<h3>2. Required files exist</h3>";
$files = [
    'includes/db.php',
    'includes/header.php',
    'includes/footer.php',
    'index.php',
    'login.php',
    'register.php',
    'logout.php',
    'save_attempt.php',
    'student/dashboard.php',
    'student/subjects.php',
    'student/take_test.php',
    'student/choose_exam.php',
    'student/choose_grade.php',
    'admin/index.php',
    'admin/questions.php',
    'admin/subjects.php',
];
echo "<ul>";
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo "<li><code>$f</code> <span class='ok'>✅</span></li>";
    } else {
        echo "<li><code>$f</code> <span class='err'>❌ MISSING</span></li>";
    }
}
echo "</ul>";

/* 3. DB connection */
echo "<h3>3. Database</h3>";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "<p class='ok'>✅ Connected to MySQL</p>";

    // Check tables
    $tables = ['users','subjects','questions','attempts','payments','admins'];
    echo "<ul>";
    foreach ($tables as $t) {
        $has = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
        if ($has) {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            echo "<li><code>$t</code> <span class='ok'>✅ ($cnt rows)</span></li>";
        } else {
            echo "<li><code>$t</code> <span class='err'>❌ MISSING TABLE</span></li>";
        }
    }
    echo "</ul>";

    // Check users columns
    echo "<h4>users table columns:</h4>";
    $cols = $pdo->query("DESCRIBE users")->fetchAll();
    echo "<ul>";
    foreach ($cols as $c) {
        echo "<li><code>{$c['Field']}</code> ({$c['Type']})</li>";
    }
    echo "</ul>";

} catch (Throwable $e) {
    echo "<p class='err'>❌ DB ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

/* 4. Config */
echo "<h3>4. Configuration</h3>";
if (defined('CLASS_LEVELS')) {
    echo "<p>CLASS_LEVELS: <code>" . implode(', ', CLASS_LEVELS) . "</code></p>";
} else {
    echo "<p class='err'>❌ CLASS_LEVELS not defined</p>";
}
if (defined('MAIN_EXAMS')) {
    echo "<p>MAIN_EXAMS: <code>" . implode(', ', MAIN_EXAMS) . "</code></p>";
} else {
    echo "<p class='err'>❌ MAIN_EXAMS not defined</p>";
}

/* 5. Test insert */
echo "<h3>5. Test insert into users</h3>";
try {
    $pdo->prepare("INSERT INTO users (name, phone, class_level) VALUES ('__diag__', '0000', '')")->execute();
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    echo "<p class='ok'>✅ Insert + delete works</p>";
} catch (Throwable $e) {
    echo "<p class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p style='background:#fef3c7;padding:12px;border-radius:8px'><b>⚠️ DELETE THIS FILE WHEN DONE</b>: <code>diagnose.php</code></p>";
echo "</div></body></html>";