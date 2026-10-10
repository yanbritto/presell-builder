<?php
declare(strict_types=1);
require __DIR__ . '/../app/helpers.php';
$secret = bin2hex(random_bytes(32));
$payload = ['campaign'=>'demo', 'status'=>'paid', 'amount'=>'10.00', 'order_id'=>'order-1'];
$payload['signature'] = hash_hmac('sha256', implode('|', array_values($payload)), $secret);
$sale = ['campaign'=>'demo', 'order_id'=>'order-1', 'status'=>'paid', 'accepted'=>true, 'verified'=>true, 'source'=>'postback'];
$refund = array_replace($sale, ['status'=>'refunded']);
$_COOKIE = [];
$checks = [
    'unsigned events rejected' => !verifyPostbackSignature(array_diff_key($payload, ['signature'=>true]), $secret),
    'missing secret rejected' => !verifyPostbackSignature($payload, ''),
    'valid HMAC accepted' => verifyPostbackSignature($payload, $secret),
    'changed amount rejected' => !verifyPostbackSignature(array_replace($payload, ['amount'=>'20.00']), $secret),
    'array signature rejected' => !verifyPostbackSignature(array_replace($payload, ['signature'=>[]]), $secret),
    'SubID case preserved' => sanitizeTrackingData(['subid'=>' AbC-123 ']) === ['subid'=>'AbC-123'],
    'ValueTrack retained' => sanitizeTrackingData(['campaignid'=>'123']) === ['campaignid'=>'123'],
    'invalid tracking discarded' => sanitizeTrackingData(['gclid'=>[], 'subid'=>str_repeat('x',513), 'value'=>"bad\nvalue"]) === [],
    'no consent means no tracking' => consentTracking(['gclid'=>'test']) === [],
    'rejected event ignored' => countApprovedSales([array_replace($sale, ['accepted'=>false])]) === 0,
    'clicks ignored' => countApprovedSales([array_replace($sale, ['status'=>'click'])]) === 0,
    'unsigned legacy events ignored' => countApprovedSales([['status'=>'paid']]) === 0,
    'old accepted events require authentication provenance' => countApprovedSales([array_diff_key($sale, ['verified'=>true])]) === 0,
    'duplicate orders counted once' => countApprovedSales([$sale,$sale,array_replace($sale,['status'=>'completed'])]) === 1,
    'refund removes sale and survives late confirmation' => countApprovedSales([$sale,$refund,$sale]) === 0,
    'unsafe URL rejected' => !validOfferUrl('javascript:alert(1)') && !validOfferUrl('https://user:pass@example.com'),
];
$_COOKIE['presell_consent'] = 'accepted';
$checks['accepted tracking retained'] = consentTracking(['gclid'=>'test']) === ['gclid'=>'test'];
$_COOKIE['presell_consent'] = 'rejected';
$checks['rejection prevents offer tracking'] = offerUrl(['offer_url'=>'https://example.com/offer'], ['gclid'=>'test']) === 'https://example.com/offer';
$failures=0;
foreach ($checks as $name=>$ok) { echo ($ok?'PASS':'FAIL').' - '.$name.PHP_EOL; $failures += $ok?0:1; }
exit($failures ? 1 : 0);
