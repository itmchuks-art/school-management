<?php
require_once __DIR__ . '/../includes/db.php';

if (!empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/dashboard.php');
}

$error = '';
$old   = ['name' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $old   = ['name' => $name, 'phone' => $phone];

    if ($name === '')  $error = 'Enter your name.';
    elseif (strlen($name) < 3) $error = 'Name is too short.';
    elseif ($phone === '') $error = 'Enter your phone number.';
    elseif (!preg_match('/^[0-9]{7,10}$/', $phone)) $error = 'Enter a valid phone (7–10 digits).';

    if ($error === '') {
        /* Block only same name + phone */
        $chk = $pdo->prepare('SELECT id FROM admins WHERE name = ? AND phone = ? LIMIT 1');
        $chk->execute([$name, $phone]);

        if ($chk->fetch()) {
            $error = 'That admin already exists.';
        } else {
            try {
                $ins = $pdo->prepare('INSERT INTO admins (name, phone, created_at)
                                      VALUES (?, ?, NOW())');
                $ins->execute([$name, $phone]);

                flash('✅ Admin account created. Please log in.');
                redirect(SITE_URL . '/admin/login.php');
            } catch (Exception $ex) {
                $error = 'Database error: ' . $ex->getMessage();
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="max-width:480px">

  <div class="text-center mb-4">
    <h2 class="fw-bold" style="color:#CE1126">🛡️ Admin Registration</h2>
    <p class="text-muted">Create your admin account</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card p-4 shadow-sm" style="border-radius:18px">

    <div class="mb-3">
      <label class="form-label fw-bold">Full Name</label>
      <input name="name" class="form-control form-control-lg"
             value="<?= e($old['name']) ?>" required autofocus>
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Phone Number</label>
      <input name="phone" class="form-control form-control-lg"
             value="<?= e($old['phone']) ?>" placeholder="e.g. 7705902" required>
    </div>

    <button class="btn w-100 py-3 fw-bold"
            style="border-radius:12px;background:linear-gradient(135deg,#CE1126,#a30d1e);border:none;color:#fff">
      Create Admin Account
    </button>

    <p class="text-center text-muted mt-3 mb-0 small">
      Already admin?
      <a href="<?= SITE_URL ?>/admin/login.php" class="fw-bold">Login</a>
      &nbsp;·&nbsp;
      <a href="<?= SITE_URL ?>/login.php">Student login</a>
    </p>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>