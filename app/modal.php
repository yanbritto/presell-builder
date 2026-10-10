<?php
declare(strict_types=1);
$exportMode = $exportMode ?? false;
$portuguese = ($campaign['language'] ?? 'en') === 'pt';
$language = $portuguese ? 'pt-BR' : 'en';
$text = $portuguese ? [
    'disclosure' => 'Página de afiliado independente', 'offer' => 'Conheça a oferta',
    'subtitle' => 'Confira os detalhes, condições e informações atualizadas na página do vendedor.',
    'title' => 'Configurações de privacidade',
    'consent' => 'Você pode permitir o rastreamento de campanha e seguir para a oferta pelo nosso link de afiliado. Podemos receber uma comissão. O vendedor tem suas próprias configurações de privacidade.',
    'accept' => 'Aceitar rastreamento e continuar', 'reject' => 'Continuar sem rastreamento',
    'footnote' => 'Cliques não são vendas. Sua escolha controla o rastreamento nesta página.',
] : [
    'disclosure' => 'Independent affiliate page', 'offer' => 'Discover the offer',
    'subtitle' => 'See current product details, terms and information on the seller’s page.',
    'title' => 'Privacy Settings',
    'consent' => 'You can allow campaign tracking and visit the offer through our affiliate link. We may earn a commission. The seller has its own privacy settings.',
    'accept' => 'Accept tracking & continue', 'reject' => 'Continue without tracking',
    'footnote' => 'Clicks are not sales. Your choice controls tracking on this page.',
];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$destination = validOfferUrl((string) ($campaign['offer_url'] ?? '')) ? $campaign['offer_url'] : '#';
$bridge = '/bridge.php?campaign=' . rawurlencode($campaign['slug']);
$image = '';
$file = $campaign['image_file'] ?? '';
if (is_string($file) && preg_match('/^[a-f0-9]{64}\.(png|jpg|webp|gif)$/', $file) && is_file(appRoot() . '/storage/media/' . $file)) {
    $image = '/asset.php?campaign=' . rawurlencode($campaign['slug']);
    if ($exportMode) {
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'][pathinfo($file, PATHINFO_EXTENSION)];
        $image = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents(appRoot() . '/storage/media/' . $file));
    }
}
?>
<!doctype html><html lang="<?php echo $language; ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="referrer" content="no-referrer">
<title><?php echo $escape($campaign['name']); ?></title>
<?php if ($exportMode): ?><style><?php echo file_get_contents(__DIR__ . '/../assets/css/presell.css'); ?></style><?php else: ?><link rel="stylesheet" href="/assets/css/presell.css"><?php endif; ?>
</head><body>
<?php if ($image !== '' && ($campaign['image_role'] ?? '') === 'screenshot'): ?>
<main class="screenshot-page" aria-hidden="true" inert><img class="checkout-reference" src="<?php echo $escape($image); ?>" alt=""><span class="preview-label"><?php echo $escape($text['disclosure']); ?> · <?php echo $portuguese ? 'Imagem ilustrativa do checkout' : 'Checkout image for illustration only'; ?></span></main>
<?php else: ?>
<main class="offer-background" aria-hidden="true" inert>
    <header><span class="offer-brand"><?php echo $escape($campaign['name']); ?></span><span><?php echo $escape($text['disclosure']); ?></span></header>
    <section class="offer-hero"><div><span class="offer-kicker"><?php echo $escape($text['offer']); ?></span><h1><?php echo $escape($campaign['headline']); ?></h1><p><?php echo $escape($campaign['subtitle'] ?: $text['subtitle']); ?></p><span class="offer-cta"><?php echo $escape($text['offer']); ?></span></div>
    <?php if ($image !== ''): ?><img src="<?php echo $escape($image); ?>" alt=""><?php else: ?><div class="offer-art"><span><?php echo $escape($campaign['name']); ?></span></div><?php endif; ?></section>
    <section class="offer-details"><h2><?php echo $escape($text['offer']); ?></h2><p><?php echo $escape($text['subtitle']); ?></p></section>
</main>
<?php endif; ?>
<div class="modal-shade"><section class="offer-dialog" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-description" data-offer-url="<?php echo $escape($destination); ?>" data-bridge="<?php echo $exportMode ? '' : $escape($bridge); ?>">
    <span class="dialog-label"><?php echo $escape($text['disclosure']); ?></span>
    <h2 id="modal-title"><?php echo $escape($text['title']); ?></h2><p id="modal-description"><?php echo $escape($text['consent']); ?></p>
    <button type="button" class="continue-primary" data-offer-choice="accepted"><?php echo $escape($text['accept']); ?></button>
    <a class="continue-secondary" href="<?php echo $escape($destination); ?>" rel="noreferrer" data-offer-choice="rejected"><?php echo $escape($text['reject']); ?></a>
    <p class="dialog-footnote"><?php echo $escape($text['footnote']); ?></p>
</section></div>
<?php if ($exportMode): ?><script><?php echo file_get_contents(__DIR__ . '/../assets/js/presell.js'); ?></script><?php else: ?><script src="/assets/js/presell.js"></script><?php endif; ?>
</body></html>
