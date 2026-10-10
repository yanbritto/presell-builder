<?php

declare(strict_types=1);

require __DIR__ . '/app/config.php';

$campaign = getCampaignBySlug(is_string($_GET['campaign'] ?? null) ? $_GET['campaign'] : 'demo') ?? createDefaultCampaign();
$offerUrl = offerUrl($campaign, $_GET);

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review | <?php echo htmlspecialchars((string) ($campaign['name'] ?? 'Presell'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="review-shell">
        <section class="review-card">
            <span class="eyebrow">Review page</span>
            <h1><?php echo htmlspecialchars((string) ($campaign['headline'] ?? 'Estratégia comprovada'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="subtitle">Antes de continuar, veja os benefícios e a lógica do produto em uma página de review clara e objetiva.</p>
            <div class="review-grid">
                <div>
                    <h2>Por que funciona</h2>
                    <ul>
                        <?php foreach (($campaign['proof'] ?? []) as $item): ?>
                            <li><?php echo htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="mini-offer">
                    <p>Oferta</p>
                    <h3><?php echo htmlspecialchars((string) ($campaign['name'] ?? 'Oferta premium'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <strong>R$ <?php echo htmlspecialchars((string) ($campaign['price'] ?? '97,00'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <a class="btn-primary" href="<?php echo htmlspecialchars($offerUrl, ENT_QUOTES, 'UTF-8'); ?>">Ver oferta</a>
                </div>
            </div>
        </section>
    </main>
    <?php require __DIR__ . '/app/consent.php'; ?>
    <script src="/assets/js/app.js"></script>
</body>
</html>
