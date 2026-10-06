<?php
echo "<h2>Hostinger DB Info Test</h2>";
echo "<pre style='font-size:16px'>";

$hosts = ['localhost', '127.0.0.1'];
$users = [
    'testmemr',
    'u123456789_testmemr',
    'root',
];

foreach ($hosts as $h) {
    foreach ($users as $u) {
        try {
            $pdo = new PDO("mysql:host=$h;dbname=testmemr", $u, 'novirus123');
            echo "✅ WORKS → host=$h  user=$u  db=testmemr\n";
        } catch (Throwable $e) {
            echo "❌ fails  → host=$h  user=$u\n";
        }
    }
}
echo "</pre>";
echo "<p>Now go back to hPanel and copy the exact User column, then update includes/db.php.</p>";