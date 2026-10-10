<?php
declare(strict_types=1);
require __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/importer.php';
require_once __DIR__ . '/app/background.php';
adminRequired();
$error = ''; $link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $link = is_string($_POST['affiliate_url'] ?? null) ? trim($_POST['affiliate_url']) : '';
    try {
        $background = '';
        if (isset($_FILES['background']) && ($_FILES['background']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = $_FILES['background'];
            if (!is_int($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK || !is_string($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name']) || filesize($upload['tmp_name']) > 5242880) {
                throw new InvalidArgumentException('Não foi possível receber a captura. Use uma imagem de até 5 MB.');
            }
            $background = storePresellBackground((string) file_get_contents($upload['tmp_name']));
        }
        $campaign = generateLinkCampaign($link);
        if ($background !== '') { $campaign['image_file'] = $background; $campaign['image_role'] = 'screenshot'; }
        updateJsonFile(appRoot() . '/storage/campaigns.json', static function (array $campaigns) use ($campaign): array { $campaigns[] = $campaign; return $campaigns; });
        redirect('/builder.php?created=' . rawurlencode($campaign['slug']));
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { $error = 'Não foi possível salvar a página. Confira as permissões de armazenamento e tente novamente.'; }
}
$created = is_string($_GET['created'] ?? null) ? getCampaignBySlug($_GET['created']) : null;
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$preview = $created ? '/index.php?campaign=' . rawurlencode($created['slug']) : '';
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar presell pelo link | Presell Builder</title><link rel="stylesheet" href="/assets/css/app.css">
</head><body><div class="dashboard-shell">
<header class="topbar"><a class="brand" href="/builder.php">Presell Builder</a><nav><a href="/admin/dashboard.php">Minhas páginas</a><form method="post" action="/admin/logout.php"><input type="hidden" name="csrf_token" value="<?php echo $escape(csrfToken()); ?>"><button class="btn-secondary" type="submit">Sair</button></form></nav></header>
<main class="link-builder">
<section class="panel builder-intro">
<span class="eyebrow">Do link à sua presell</span><h1>Cole seu link de afiliado.<br>Sua página começa aqui.</h1>
<p>Identificamos os dados públicos da oferta e criamos uma presell com fundo desfocado, popup e continuidade para o seu link.</p>
<?php if ($error !== ''): ?><div class="alert-error" role="alert"><?php echo $escape($error); ?></div><?php endif; ?>
<form method="post" action="/builder.php" enctype="multipart/form-data" class="campaign-form" id="link-builder-form">
<input type="hidden" name="csrf_token" value="<?php echo $escape(csrfToken()); ?>">
<label>Seu link de afiliado<input type="url" name="affiliate_url" value="<?php echo $escape($link); ?>" placeholder="https://…" maxlength="4096" required></label>
<label>Captura da oferta (opcional)<input type="file" name="background" accept="image/png,image/jpeg,image/webp"></label>
<p class="builder-hint">Para o visual do exemplo: envie uma captura do checkout. Sem captura, usamos os dados públicos da oferta. PNG, JPG ou WebP, até 5 MB.</p>
<button class="btn-primary" type="submit" id="generate-button">Gerar minha presell</button>
<p class="builder-hint">Sem preencher nome, slug ou textos. Se a oferta não puder ser lida, você recebe um modelo básico identificado como tal. A consulta ao link pode registrar um clique na plataforma de afiliados.</p>
</form></section>
<?php if ($created): ?>
<section class="panel" aria-live="polite"><span class="eyebrow">Página gerada</span><h2><?php echo $escape($created['name']); ?></h2>
<p><?php echo ($created['language'] ?? '') === 'pt' ? 'Idioma: português' : 'Idioma: inglês'; ?> · <?php echo ($created['network'] ?? '') === 'unknown' ? 'Plataforma não identificada' : $escape(ucfirst($created['network'])); ?></p>
<?php if (($created['import']['status'] ?? '') !== 'imported'): ?><div class="alert-error">O destino não forneceu informações acessíveis. Criamos um modelo básico com seu link, sem inventar preço, benefícios ou depoimentos. O idioma foi estimado e você pode ajustá-lo abaixo.</div>
<?php else: ?><div class="alert-success">Título e descrição importados da oferta. O layout é um template próprio; não é uma cópia completa da página original.</div><?php endif; ?>
<div class="cta-row"><a class="btn-primary" href="<?php echo $escape($preview); ?>" target="_blank" rel="noopener">Abrir minha presell</a><a class="btn-secondary" href="/export.php?campaign=<?php echo rawurlencode($created['slug']); ?>">Baixar HTML</a></div>
<label>Endereço da página<input readonly value="<?php echo $escape(appBaseUrl() . $preview); ?>" onclick="this.select()"></label>
<p>O endereço funciona enquanto este projeto estiver rodando. Para uso público, hospede o HTML ou a aplicação.</p>
<form method="post" action="/admin/page-language.php" class="language-form"><input type="hidden" name="csrf_token" value="<?php echo $escape(csrfToken()); ?>"><input type="hidden" name="campaign" value="<?php echo $escape($created['slug']); ?>"><label>Idioma<select name="language"><option value="en" <?php echo ($created['language'] ?? '') === 'en' ? 'selected' : ''; ?>>Inglês</option><option value="pt" <?php echo ($created['language'] ?? '') === 'pt' ? 'selected' : ''; ?>>Português</option></select></label><button class="btn-secondary" type="submit">Ajustar idioma</button></form>
<iframe class="presell-preview" title="Prévia da presell" src="<?php echo $escape($preview); ?>" sandbox="allow-scripts allow-same-origin allow-top-navigation-by-user-activation"></iframe>
</section><?php endif; ?>
</main></div><script src="/assets/js/builder.js"></script></body></html>
