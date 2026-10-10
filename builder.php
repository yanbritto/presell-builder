<?php

declare(strict_types=1);

require __DIR__ . '/app/config.php';
adminRequired();

$campaigns = loadCampaigns();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Builder | Presell Builder</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="dashboard-shell">
        <header class="topbar">
            <div class="brand">Presell Builder</div>
            <nav>
                <a href="/admin/dashboard.php">Painel</a>
                <form method="post" action="/admin/logout.php"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" class="btn-secondary">Sair</button></form>
            </nav>
        </header>
        <main class="panel">
            <h2>Builder de campanhas</h2>
            <p>Use o painel administrativo para criar ou atualizar campanhas de presell, bridge e review.</p>
            <ul>
                <?php foreach ($campaigns as $campaign): ?>
                    <li>
                        <strong><?php echo htmlspecialchars((string) ($campaign['name'] ?? 'Campanha sem nome'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars((string) ($campaign['slug'] ?? 'demo'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </main>
    </div>
</body>
</html>
