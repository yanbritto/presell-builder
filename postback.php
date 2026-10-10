<?php
declare(strict_types=1);
require __DIR__ . '/app/config.php';
header('Content-Type: application/json; charset=utf-8');
function respond(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(405, ['ok' => false]); }
$secret = (string) env('POSTBACK_SECRET', '');
if ($secret === '') { respond(503, ['ok' => false, 'message' => 'Postback is not configured.']); }
$data = $_POST;
if (!verifyPostbackSignature($data, $secret)) { respond(401, ['ok' => false, 'message' => 'Invalid signature.']); }
$campaign = $data['campaign'];
$status = $data['status'];
$orderId = $data['order_id'];
if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $campaign)
    || !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/', $orderId)
    || !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $data['amount'])
    || getCampaignBySlug($campaign) === null) {
    respond(422, ['ok' => false, 'message' => 'Invalid campaign, order or amount.']);
}
if (!in_array($status, ['approved', 'paid', 'completed', 'refunded', 'chargeback', 'cancelled'], true)) {
    respond(200, ['ok' => true, 'accepted' => false, 'message' => 'This status is not a confirmed sale.']);
}
$duplicate = false;
try {
    updateJsonFile(appRoot() . '/storage/postbacks.json', static function (array $events) use ($data, &$duplicate): array {
        foreach ($events as $event) {
            if (($event['accepted'] ?? false) === true && ($event['verified'] ?? false) === true && ($event['source'] ?? '') === 'postback'
                && ($event['campaign'] ?? '') === $data['campaign'] && ($event['order_id'] ?? '') === $data['order_id']
                && ($event['status'] ?? '') === $data['status']) {
                $duplicate = true;
                return $events;
            }
        }
        $events[] = [
            'timestamp' => gmdate(DATE_ATOM), 'campaign' => $data['campaign'],
            'status' => $data['status'], 'amount' => $data['amount'], 'order_id' => $data['order_id'],
            'accepted' => true, 'verified' => true, 'source' => 'postback',
        ];
        return $events;
    });
} catch (Throwable $error) { respond(503, ['ok' => false, 'message' => 'Could not save postback.']); }
respond(200, ['ok' => true, 'accepted' => true, 'duplicate' => $duplicate,
    'sale' => in_array($status, ['approved', 'paid', 'completed'], true)]);
