<?php
/* ============================================================
   Modem Pay Webhook — receives payment confirmations
   URL to give Modem Pay: https://yourdomain.com/testmemr/student/modempay_webhook.php
   ============================================================ */

require_once __DIR__ . '/../includes/db.php';

/* Log every webhook hit for debugging */
file_put_contents(__DIR__ . '/../webhook.log',
    date('Y-m-d H:i:s') . " — " . file_get_contents('php://input') . "\n\n",
    FILE_APPEND);

/* Read body */
$raw   = file_get_contents('php://input');
$event = json_decode($raw, true);

if (!$event) {
    http_response_code(400);
    echo 'Bad payload';
    exit;
}

/* Modem Pay sends various event types; we care about successful payment */
$eventType = $event['type'] ?? $event['event'] ?? '';
$data      = $event['data'] ?? $event;

/* Only proceed on success */
$isSuccess = in_array($eventType, ['payment.success', 'payment_successful', 'charge.succeeded'], true)
          || (($data['status'] ?? '') === 'successful');

if (!$isSuccess) {
    http_response_code(200);
    echo 'Ignored';
    exit;
}

/* Extract metadata */
$meta       = $data['metadata']   ?? [];
$user_id    = (int)($meta['user_id']    ?? 0);
$plan_key   = $meta['plan']              ?? '';
$payment_id = (int)($meta['payment_id'] ?? 0);

if (!$user_id || !isset(PLANS[$plan_key])) {
    http_response_code(400);
    echo 'Missing user or plan';
    exit;
}

/* Extend subscription */
$plan  = PLANS[$plan_key];
$days  = $plan['days'];

$st = $pdo->prepare('SELECT subscription_end FROM users WHERE id=?');
$st->execute([$user_id]);
$cur = $st->fetchColumn();

$base = max(time(), $cur ? strtotime($cur) : time());
$new  = date('Y-m-d H:i:s', $base + $days * 86400);

/* Determine grades unlocked */
$grades_csv = $plan['type'] === 'all'
    ? implode(',', CLASS_LEVELS)
    : ($meta['grades'] ?? '');

$pdo->prepare('UPDATE users SET subscription_end=?, subscription_plan=?, subscribed_grades=? WHERE id=?')
    ->execute([$new, $plan_key, $grades_csv, $user_id]);

/* Update the payment record */
if ($payment_id) {
    $pdo->prepare('UPDATE payments SET status=?, reference=? WHERE id=?')
        ->execute(['approved', $data['reference'] ?? $data['id'] ?? '', $payment_id]);
} else {
    $pdo->prepare('INSERT INTO payments (user_id, plan, amount, method, reference, status, subscribed_grades)
                   VALUES (?,?,?,?,?,?,?)')
        ->execute([
            $user_id, $plan_key, $plan['amount'],
            'ModemPay', $data['reference'] ?? '', 'approved', $grades_csv,
        ]);
}

http_response_code(200);
echo 'OK';