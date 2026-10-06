<?php
require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Fix Columns</title>";
echo "<style>
body{font-family:system-ui;background:#f5f7ff;padding:20px;line-height:1.7}
.box{max-width:700px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
h1{color:#1e40af}h2{color:#0C1E7F;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-top:24px}
code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}
.ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}.warn{color:#d97706;font-weight:600}
</style></head><body><div class='box'>";
echo "<h1>🔧 Fix Missing Database Columns</h1>";

/* ============================================================
   Columns to add
   ============================================================ */
$fixes = [
    ['table' => 'users',    'column' => 'subscription_plan', 'sql' => "ALTER TABLE users ADD COLUMN subscription_plan VARCHAR(20) DEFAULT NULL"],
    ['table' => 'users',    'column' => 'subscribed_grades', 'sql' => "ALTER TABLE users ADD COLUMN subscribed_grades VARCHAR(255) DEFAULT NULL"],
    ['table' => 'payments', 'column' => 'subscribed_grades', 'sql' => "ALTER TABLE payments ADD COLUMN subscribed_grades VARCHAR(255) DEFAULT NULL"],
];

foreach ($fixes as $f) {
    echo "<h2>Checking <code>{$f['table']}.{$f['column']}</code></h2>";

    try {
        /* Check if column exists */
        $st = $pdo->prepare("SHOW COLUMNS FROM `{$f['table']}` LIKE ?");
        $st->execute([$f['column']]);
        $exists = $st->fetch();

        if ($exists) {
            echo "<p class='ok'>✅ Column already exists — skipping.</p>";
        } else {
            $pdo->exec($f['sql']);
            echo "<p class='ok'>✅ Column <code>{$f['column']}</code> added successfully.</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

/* ============================================================
   Verify final structure
   ============================================================ */
echo "<h2>Final check — <code>users</code> table columns</h2>";
$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
echo "<ul>";
foreach ($cols as $c) {
    $highlight = in_array($c['Field'], ['subscription_plan', 'subscribed_grades']);
    $style = $highlight ? "style='background:#d1fae5;padding:3px 8px;border-radius:6px;display:inline-block'" : "";
    echo "<li><span $style><code>{$c['Field']}</code> — {$c['Type']}</span></li>";
}
echo "</ul>";

echo "<h2>Final check — <code>payments</code> table columns</h2>";
$cols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll();
echo "<ul>";
foreach ($cols as $c) {
    $highlight = in_array($c['Field'], ['subscribed_grades']);
    $style = $highlight ? "style='background:#d1fae5;padding:3px 8px;border-radius:6px;display:inline-block'" : "";
    echo "<li><span $style><code>{$c['Field']}</code> — {$c['Type']}</span></li>";
}
echo "</ul>";

echo "<p style='background:#d1fae5;padding:16px;border-radius:8px;font-weight:700;color:#065f46;margin-top:24px'>";
echo "🎉 Done! Now <a href='" . SITE_URL . "/student/subscription.php'>go to Subscription</a> and try again.";
echo "</p>";

echo "<p style='background:#fef3c7;padding:12px;border-radius:8px;margin-top:16px'>";
echo "<b>⚠️ Delete this file when done:</b> <code>fix_columns.php</code>";
echo "</p>";

echo "</div></body></html>";