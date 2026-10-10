<?php
declare(strict_types=1);
require __DIR__ . '/../app/importer.php';
$failures = 0; $checks = 0;
function checkImport(bool $ok, string $name): void {
    global $failures, $checks; $checks++; if (!$ok) { $failures++; }
    echo ($ok ? 'PASS' : 'FAIL') . ' - ' . $name . PHP_EOL;
}
foreach (['http://localhost/', 'http://127.0.0.1/', 'http://2130706433/', 'file:///etc/passwd', 'https://user:pass@example.com/', 'https://example.com:8443/'] as $url) { checkImport(!publicImportUrl($url), 'unsafe URL rejected: ' . $url); }
foreach (['127.0.0.1', '10.1.1.1', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.64.0.1', '198.18.0.1', '192.0.0.1', '203.0.113.1', '224.0.0.1', '::1'] as $ip) { checkImport(!publicImportIp($ip), 'private or reserved IP rejected: ' . $ip); }
checkImport(publicImportIp('93.184.216.34'), 'public IP allowed');
$resolver = static fn (string $host): array => ['93.184.216.34'];
$calls = [];
$transport = static function (string $url, string $ip, int $limit) use (&$calls): array {
    $calls[] = $url;
    if (str_contains($url, 'hop.clickbank.net')) { return ['status'=>302,'headers'=>['location'=>'https://offer.example/product'],'body'=>'']; }
    return ['status'=>200,'headers'=>['content-type'=>'text/html'],'body'=>'<html lang="en"><title>Source offer</title><meta property="og:title" content="Actual Product"><meta name="description" content="Source description &amp; details"><body>Offer</body></html>'];
};
$link = 'https://hop.clickbank.net/?affiliate=TestCase';
$campaign = generateLinkCampaign($link, $resolver, $transport);
checkImport($campaign['name'] === 'Actual Product' && $campaign['subtitle'] === 'Source description & details', 'actual metadata imported');
checkImport($campaign['offer_url'] === $link, 'original affiliate URL preserved exactly');
checkImport($campaign['network'] === 'clickbank' && $campaign['language'] === 'en', 'network and language detected');
checkImport($campaign['template'] === 'modal' && $campaign['import']['status'] === 'imported', 'modal template generated');
checkImport(count($calls) === 2, 'HTTP redirect followed');
checkImport($campaign['price'] === '' && $campaign['proof'] === [], 'no fabricated price or proof');
$unavailable = static fn (): array => ['status'=>403,'headers'=>[],'body'=>''];
$basic = generateLinkCampaign('https://go.hotmart.com/sample', $resolver, $unavailable);
checkImport($basic['import']['status'] === 'basic' && $basic['language'] === 'pt', 'blocked page creates clearly marked Portuguese fallback');
checkImport($basic['slug'] !== $campaign['slug'], 'unique slugs generated');
$private = static fn (): array => ['192.168.1.1'];
try { generateLinkCampaign('https://offer.example/', $private, $transport); checkImport(false, 'private DNS blocked'); }
catch (InvalidArgumentException $error) { checkImport(true, 'private DNS blocked'); }
$mixed = static fn (): array => ['93.184.216.34', '127.0.0.1'];
try { fetchPublicResource('https://offer.example/', 1000, $mixed, $transport); checkImport(false, 'mixed DNS blocked'); }
catch (RuntimeException $error) { checkImport($error->getMessage() === 'unsafe_destination', 'mixed DNS blocked'); }
$redirectPrivate = static fn (): array => ['status'=>302,'headers'=>['location'=>'http://127.0.0.1/admin/'],'body'=>''];
try { fetchPublicResource('https://offer.example/', 1000, $resolver, $redirectPrivate); checkImport(false, 'private redirect blocked'); }
catch (RuntimeException $error) { checkImport($error->getMessage() === 'unsafe_destination', 'private redirect blocked'); }
$loop = static fn (): array => ['status'=>302,'headers'=>['location'=>'/again'],'body'=>''];
try { fetchPublicResource('https://offer.example/', 1000, $resolver, $loop); checkImport(false, 'redirect loop bounded'); }
catch (RuntimeException $error) { checkImport($error->getMessage() === 'too_many_redirects', 'redirect loop bounded'); }
$huge = static fn (): array => ['status'=>200,'headers'=>[],'body'=>str_repeat('x',1001)];
try { fetchPublicResource('https://offer.example/', 1000, $resolver, $huge); checkImport(false, 'response size bounded'); }
catch (RuntimeException $error) { checkImport($error->getMessage() === 'response_too_large', 'response size bounded'); }
$metadata = extractOfferMetadata('<html lang="pt-BR"><title>Oferta segura</title><meta property="og:description" content="&lt;script&gt;alert(1)&lt;/script&gt;Texto"><meta property="og:image" content="file:///etc/passwd"></html>', 'https://offer.example/');
checkImport($metadata['language'] === 'pt' && !str_contains($metadata['description'], '<script>') && $metadata['image_url'] === '', 'metadata sanitized and unsafe image removed');
checkImport(detectAffiliateNetwork(['https://hotmart.com.attacker.example/']) === 'unknown', 'network suffix spoofing rejected');
checkImport(absoluteImportUrl('https://offer.example/path/index', '?a=2') === 'https://offer.example/path/index?a=2', 'query-only redirect resolved');
echo "$checks importer checks completed" . PHP_EOL;
exit($failures ? 1 : 0);
