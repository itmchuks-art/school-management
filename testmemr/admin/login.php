<?php
require_once __DIR__ . '/../includes/db.php';

if (!empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/dashboard.php');
}

$error = '';
$name  = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || $phone === '') {
        $error = 'Enter your name and phone number.';
    } else {
        $st = $pdo->prepare('SELECT * FROM admins WHERE name = ? AND phone = ? LIMIT 1');
        $st->execute([$name, $phone]);
        $admin = $st->fetch();

        if (!$admin) {
            $error = 'Invalid admin name or phone.';
        } else {
            $_SESSION['admin_id']   = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'];

            flash('👋 Welcome, ' . $admin['name']);
            redirect(SITE_URL . '/admin/dashboard.php');
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="max-width:420px">
  <div class="text-center mb-4">
    <h2 class="fw-bold" style="color:#0C1E7F">🛡️ Admin Login</h2>
    <p class="text-muted">Login with your admin name and phone</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card p-4 shadow-sm" style="border-radius:18px">
    <div class="mb-3">
      <label class="form-label fw-bold">Full Name</label>
      <input name="name" class="form-control form-control-lg"
             value="<?= e($name) ?>" required autofocus>
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Phone Number</label>
      <input name="phone" class="form-control form-control-lg"
             value="<?= e($phone) ?>" required>
    </div>

    <button class="btn btn-primary w-100 py-3 fw-bold"
            style="border-radius:12px;background:linear-gradient(135deg,#0C1E7F,#1e40af);border:none">
      Login
    </button>

    <p class="text-center text-muted mt-3 mb-0 small">
      <a href="<?= SITE_URL ?>/admin/register.php">Create first admin</a>
      &nbsp;·&nbsp;
      <a href="<?= SITE_URL ?>/login.php">Student login</a>
    </p>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>