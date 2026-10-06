<?php
require_once __DIR__ . '/includes/db.php';

/* Already logged in? Send forward */
if (!empty($_SESSION['user_id'])) {
    $st = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$_SESSION['user_id']]);
    $u = $st->fetch();

    if ($u) {
        $dest = empty($u['grade'])
            ? SITE_URL . '/student/choose_grade.php'
            : SITE_URL . '/student/dashboard.php';
        redirect($dest);
    }
    unset($_SESSION['user_id'], $_SESSION['user_name']);
}

$error = '';
$old   = ['name' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $old   = ['name' => $name, 'phone' => $phone];

    if ($name === '')  $error = 'Enter your full name.';
    elseif (strlen($name) < 3) $error = 'Name is too short.';
    elseif ($phone === '') $error = 'Enter your phone number.';
    elseif (!preg_match('/^[0-9]{7,10}$/', $phone)) $error = 'Enter a valid phone number (7–10 digits, no +220).';

    if ($error === '') {
        /* Check for duplicate name + phone */
        $chk = $pdo->prepare('SELECT id FROM users WHERE name = ? AND phone = ? LIMIT 1');
        $chk->execute([$name, $phone]);

        if ($chk->fetch()) {
            $error = 'That name + phone is already registered. Please log in.';
        } else {
            try {
                $ins = $pdo->prepare('INSERT INTO users
                    (name, phone, class_level, grade, created_at)
                    VALUES (?, ?, ?, NULL, NOW())');
                $ins->execute([$name, $phone, '']);

                $_SESSION['user_id']   = (int)$pdo->lastInsertId();
                $_SESSION['user_name'] = $name;

                flash('✅ Account created. Now choose your grade.');
                redirect(SITE_URL . '/student/choose_grade.php');

            } catch (Exception $ex) {
                $error = 'Database error: ' . $ex->getMessage();
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container py-5" style="max-width:520px">
  <div class="text-center mb-4">
    <h2 class="fw-bold" style="color:#0C1E7F">Test Me Mr 🇬🇲</h2>
    <p class="text-muted">Create your student account 🇬🇲</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card p-4 shadow-sm" style="border-radius:18px">
    <div class="mb-3">
      <label class="form-label fw-bold">Full Name</label>
      <input type="text" name="name" class="form-control form-control-lg"
             value="<?= e($old['name']) ?>"
             placeholder="e.g. Moses Ibuchim Chuku" required autofocus>
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Phone Number</label>
      <input type="text" name="phone" class="form-control form-control-lg"
             value="<?= e($old['phone']) ?>"
             placeholder="e.g. 7705902" required>
      <div class="form-text">Many students can share one phone — just use your own name.</div>
    </div>

    <button type="submit" class="btn btn-success w-100 py-3 fw-bold"
            style="border-radius:12px;background:linear-gradient(135deg,#3A7728,#16a34a);border:none">
      Create Student Account 🇬🇲
    </button>

    <p class="text-center text-muted mt-3 mb-0 small">
      Already have an account?
      <a href="<?= SITE_URL ?>/login.php" class="fw-bold">Login</a>
    </p>

    <hr class="my-3">

    <p class="text-center mb-0 small">
      🛡️ Site administrator?
      <a href="<?= SITE_URL ?>/admin/register.php" class="fw-bold" style="color:#CE1126">
        Create Admin Account
      </a>
    </p>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>