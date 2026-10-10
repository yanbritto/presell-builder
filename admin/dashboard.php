<?php

declare(strict_types=1);

require __DIR__ . '/../app/config.php';
adminRequired();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    foreach ($_POST as $value) {
        if (!is_string($value)) { http_response_code(422); exit('Invalid input.'); }
    }
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
    $title = trim((string) ($_POST['name'] ?? ''));
    $network = trim((string) ($_POST['network'] ?? 'hotmart'));
    $offerUrl = trim((string) ($_POST['offer_url'] ?? 'https://example.com/offer'));

    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $slug) || $title === '' || !validOfferUrl($offerUrl) || !in_array($network, ['braip', 'hotmart', 'smartadv', 'buygoods', 'clickbank', 'eduzz', 'digistore24'], true)) {
        $_SESSION['dashboard_message'] = 'Informe um slug válido, nome, rede e URL HTTP/HTTPS da oferta.';
        redirect('/admin/dashboard.php');
    }

    $newCampaign = [
        'id' => $slug,
        'slug' => $slug,
        'name' => $title,
        'network' => $network,
        'headline' => trim((string) ($_POST['headline'] ?? 'Estratégia de presell com CTA clara e segura.')),
        'subtitle' => trim((string) ($_POST['subtitle'] ?? 'Página de review e bridge com tracking e foco em conversão.')),
        'price' => trim((string) ($_POST['price'] ?? '97,00')),
        'offer_url' => $offerUrl,
        'cta_text' => trim((string) ($_POST['cta_text'] ?? 'Quero ver a oferta')),
        'proof' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', (string) ($_POST['proof'] ?? ''))))),
        'features' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', (string) ($_POST['features'] ?? ''))))),
        'tracking' => [
            'allowed_parameters' => ['gclid', 'wbraid', 'gbraid', 'utm_source', 'utm_campaign', 'subid', 'sub_id'],
        ],
    ];

    $updated = false;
    updateJsonFile(appRoot() . '/storage/campaigns.json', static function (array $campaigns) use ($slug, $newCampaign, &$updated): array {
        foreach ($campaigns as $index => $campaign) {
            if (($campaign['slug'] ?? '') === $slug) {
                $campaigns[$index] = $newCampaign;
                $updated = true;
                return $campaigns;
            }
        }
        $campaigns[] = $newCampaign;
        return $campaigns;
    });
    $_SESSION['dashboard_message'] = $updated ? 'Campanha atualizada.' : 'Campanha criada.';
    redirect('/admin/dashboard.php');
}

$campaigns = loadCampaigns();
$message = $_SESSION['dashboard_message'] ?? '';
unset($_SESSION['dashboard_message']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel administrativo</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="dashboard-shell">
        <header class="topbar">
            <div class="brand">Presell Builder</div>
            <nav>
                <a href="/">Landing</a>
                <form method="post" action="/admin/logout.php"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" class="btn-secondary">Sair</button></form>
            </nav>
        </header>

        <main class="dashboard-layout">
            <section class="panel">
                <h2>Campanhas</h2>
                <?php if ($message !== ''): ?>
                    <div class="alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <ul class="campaign-list">
                    <?php foreach ($campaigns as $campaign): ?>
                        <li>
                            <strong><?php echo htmlspecialchars((string) ($campaign['name'] ?? 'Campanha sem nome'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars((string) ($campaign['network'] ?? 'hotmart'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <a href="/index.php?campaign=<?php echo rawurlencode((string) ($campaign['slug'] ?? 'demo')); ?>" target="_blank">Preview</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="panel">
                <h2>Nova campanha</h2>
                <form method="post" action="/admin/dashboard.php" class="campaign-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label>Slug<input type="text" name="slug" placeholder="demo" required></label>
                    <label>Nome<input type="text" name="name" placeholder="Presell de Demo" required></label>
                    <label>Rede<select name="network">
                        <option value="braip">Braip</option>
                        <option value="hotmart">Hotmart</option>
                        <option value="smartadv">SmartADV</option>
                        <option value="buygoods">BuyGoods</option>
                        <option value="clickbank">ClickBank</option>
                        <option value="eduzz">Eduzz</option>
                        <option value="digistore24">Digistore24</option>
                    </select></label>
                    <label>Headline<input type="text" name="headline"></label>
                    <label>Subtitle<input type="text" name="subtitle"></label>
                    <label>Preço<input type="text" name="price" value="97,00"></label>
                    <label>Offer URL<input type="url" name="offer_url" value="https://example.com/offer"></label>
                    <label>CTA<input type="text" name="cta_text" value="Quero ver a oferta"></label>
                    <label>Prova social<textarea name="proof" rows="4">Resultado real sem truques.
Estrutura clara para review e bridge.
Tracking sem clicks como venda.</textarea></label>
                    <label>Features<textarea name="features" rows="4">Modal de aviso claro
Bridge page
Review page
Postback de conversão validado</textarea></label>
                    <button type="submit" class="btn-primary">Salvar campanha</button>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
