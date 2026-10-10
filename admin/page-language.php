<?php
declare(strict_types=1);
require __DIR__ . '/../app/config.php';
adminRequired();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verifyCsrf();
$slug = is_string($_POST['campaign'] ?? null) ? $_POST['campaign'] : '';
$language = $_POST['language'] ?? '';
if (!in_array($language, ['pt', 'en'], true) || getCampaignBySlug($slug) === null) { http_response_code(422); exit; }
updateJsonFile(appRoot() . '/storage/campaigns.json', static function (array $campaigns) use ($slug, $language): array {
    foreach ($campaigns as &$campaign) {
        if ($campaign['slug'] === $slug) {
            $campaign['language'] = $language;
            if (($campaign['import']['status'] ?? '') === 'basic') {
                $campaign['name'] = $campaign['headline'] = $language === 'pt' ? 'Conheça a oferta' : 'Discover the offer';
            }
        }
    }
    return $campaigns;
});
redirect('/builder.php?created=' . rawurlencode($slug));
