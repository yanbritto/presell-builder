<?php

declare(strict_types=1);

require __DIR__ . '/app/config.php';


$campaign = getCampaignBySlug(is_string($_GET['campaign'] ?? null) ? $_GET['campaign'] : 'demo') ?? createDefaultCampaign();
if (($campaign['template'] ?? '') === 'modal') {
    require __DIR__ . '/app/modal.php';
    exit;
}
$tracking = consentTracking($_GET);
$trackQuery = http_build_query($tracking);
$reviewHref = '/review.php?campaign=' . rawurlencode($campaign['slug'] ?? 'demo') . ($trackQuery !== '' ? '&' . $trackQuery : '');
$buttonHref = '/bridge.php?campaign=' . rawurlencode($campaign['slug'] ?? 'demo');
if ($trackQuery !== '') {
    $buttonHref .= '&' . $trackQuery;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars((string) ($campaign['name'] ?? 'Presell Builder'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="site-shell">
        <header class="topbar">
            <div class="brand">Presell Builder</div>
            <nav>
                <a href="/admin/login.php">Admin</a>
            </nav>
        </header>

        <main class="hero">
            <section class="hero-copy">
                <span class="eyebrow">Presell • <?php echo htmlspecialchars((string) ($campaign['network'] ?? 'hotmart'), ENT_QUOTES, 'UTF-8'); ?></span>
                <h1><?php echo htmlspecialchars((string) ($campaign['headline'] ?? 'Gerador de presell para afiliados'), ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo htmlspecialchars((string) ($campaign['subtitle'] ?? 'Estratégia clara e segura para pages de review e bridge.'), ENT_QUOTES, 'UTF-8'); ?></p>

                <div class="cta-row">
                    <a class="btn-primary" href="<?php echo htmlspecialchars($buttonHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($campaign['cta_text'] ?? 'Quero ver a oferta'), ENT_QUOTES, 'UTF-8'); ?></a>
                    <a class="btn-secondary" href="<?php echo htmlspecialchars($reviewHref, ENT_QUOTES, 'UTF-8'); ?>">Ver review</a>
                </div>

                <ul class="proof-list">
                    <?php foreach (($campaign['proof'] ?? []) as $item): ?>
                        <li><?php echo htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <aside class="offer-card">
                <p class="label">Oferta atual</p>
                <h2><?php echo htmlspecialchars((string) ($campaign['name'] ?? 'Oferta premium'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="price-box">
                    <span class="currency">R$</span>
                    <strong><?php echo htmlspecialchars((string) ($campaign['price'] ?? '97,00'), ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <ul>
                    <?php foreach (($campaign['features'] ?? []) as $feature): ?>
                        <li><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        </main>
    </div>

    <?php require __DIR__ . '/app/consent.php'; ?>

    <script src="/assets/js/app.js"></script>
</body>
</html>
