<?php
require_once __DIR__ . '/includes/db.php';
echo "<h2>SITE_URL is: <code>" . SITE_URL . "</code></h2>";
echo "<p>Expected: <code>/testmemr</code></p>";
echo "<p>Test link: <a href='" . SITE_URL . "/admin/index.php'>Go to Admin</a></p>";