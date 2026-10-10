<?php

declare(strict_types=1);

require __DIR__ . '/app/config.php';
$campaignSlug = is_string($_GET['campaign'] ?? null) ? $_GET['campaign'] : 'demo';
$campaign = getCampaignBySlug($campaignSlug) ?? createDefaultCampaign();
$tracking = consentTracking($_GET);
if (consentAccepted()) {
    appendJsonLog('traffic.json', [
        'timestamp' => gmdate(DATE_ATOM), 'campaign' => $campaign['slug'],
        'tracking' => $tracking, 'source' => 'bridge', 'consent' => 'accepted',
    ]);
}
$offerUrl = offerUrl($campaign, $_GET);
if (consentAccepted() && $offerUrl !== '#') {
    header('Location: ' . $offerUrl);
    exit;
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bridge | <?php echo htmlspecialchars((string) ($campaign['name'] ?? 'Presell'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="bridge-shell">
        <div class="bridge-card">
            <span class="eyebrow">Bridge page</span>
            <h1><?php echo htmlspecialchars((string) ($campaign['headline'] ?? 'Aguarde...'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Você pode continuar para a oferta sem aceitar o rastreamento. Nenhum clique é contado como venda.</p>
            <div class="progress-bar"><span></span></div>
            <a class="btn-primary" href="<?php echo htmlspecialchars($offerUrl, ENT_QUOTES, 'UTF-8'); ?>">Continuar para a oferta</a>
        </div>
    </main>
    <?php require __DIR__ . '/app/consent.php'; ?>
    <script src="/assets/js/app.js"></script>
</body>
</html>
