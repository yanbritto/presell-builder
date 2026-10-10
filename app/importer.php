<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function publicImportUrl(string $url): bool
{
    if (!validOfferUrl($url) || strlen($url) > 4096) { return false; }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $port = parse_url($url, PHP_URL_PORT);
    return preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,63}$/', $host) === 1
        && !str_ends_with($host, '.local') && !str_ends_with($host, '.localhost')
        && ($port === null || ($port === 443 && parse_url($url, PHP_URL_SCHEME) === 'https') || ($port === 80 && parse_url($url, PHP_URL_SCHEME) === 'http'));
}

function publicImportIp(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return false; }
    $number = (int) sprintf('%u', ip2long($ip));
    foreach (['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $range) {
        [$base, $bits] = explode('/', $range);
        $mask = (0xFFFFFFFF << (32 - (int) $bits)) & 0xFFFFFFFF;
        if (($number & $mask) === ((int) sprintf('%u', ip2long($base)) & $mask)) { return false; }
    }
    return true;
}

function importResolve(string $host): array
{
    $records = @dns_get_record($host, DNS_A);
    return array_values(array_unique(array_column(is_array($records) ? $records : [], 'ip')));
}

function importRequest(string $url, string $ip, int $limit): array
{
    if (!function_exists('curl_init')) { throw new RuntimeException('curl_unavailable'); }
    $host = (string) parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT) ?? (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);
    $body = ''; $headers = []; $tooLarge = false;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_PROXY => '', CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'PresellBuilder/0.2 (+public offer preview)',
        CURLOPT_HTTPHEADER => ['Accept: text/html,image/*;q=0.8', 'Accept-Language: en,pt;q=0.9'],
        CURLOPT_ENCODING => '',
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge, $limit): int {
            if (strlen($body) + strlen($chunk) > $limit) { $tooLarge = true; return 0; }
            $body .= $chunk; return strlen($chunk);
        },
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
            if (str_starts_with($line, 'HTTP/')) { $headers = []; }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) { $headers[strtolower(trim($parts[0]))] = trim($parts[1]); }
            return strlen($line);
        },
    ]);
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($tooLarge) { throw new RuntimeException('response_too_large'); }
    if ($ok === false) { throw new RuntimeException('connection_failed'); }
    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

function absoluteImportUrl(string $base, string $target): string
{
    $target = trim(html_entity_decode($target, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $target)) { return $target; }
    $scheme = (string) parse_url($base, PHP_URL_SCHEME);
    $origin = $scheme . '://' . parse_url($base, PHP_URL_HOST);
    if (str_starts_with($target, '//')) { return $scheme . ':' . $target; }
    if (str_starts_with($target, '?')) { return $origin . (parse_url($base, PHP_URL_PATH) ?: '/') . $target; }
    if (str_starts_with($target, '/')) { return $origin . $target; }
    $path = (string) (parse_url($base, PHP_URL_PATH) ?: '/');
    return $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $target;
}

function fetchPublicResource(string $url, int $limit = 2097152, ?callable $resolver = null, ?callable $transport = null, ?float $deadline = null): array
{
    $resolver ??= 'importResolve'; $transport ??= 'importRequest';
    $deadline ??= microtime(true) + 20;
    $chain = [];
    for ($hop = 0; $hop < 5; $hop++) {
        if (microtime(true) >= $deadline) { throw new RuntimeException('connection_failed'); }
        if (!publicImportUrl($url)) { throw new RuntimeException('unsafe_destination'); }
        $ips = $resolver((string) parse_url($url, PHP_URL_HOST));
        if ($ips === []) { throw new RuntimeException('page_unavailable'); }
        if (count(array_filter($ips, 'publicImportIp')) !== count($ips)) { throw new RuntimeException('unsafe_destination'); }
        $response = $transport($url, $ips[0], $limit);
        if (strlen($response['body']) > $limit) { throw new RuntimeException('response_too_large'); }
        $chain[] = $url;
        if (in_array($response['status'], [301, 302, 303, 307, 308], true)) {
            $location = $response['headers']['location'] ?? '';
            if ($location === '') { throw new RuntimeException('invalid_redirect'); }
            $url = absoluteImportUrl($url, $location);
            continue;
        }
        if ($response['status'] < 200 || $response['status'] >= 300) { throw new RuntimeException('page_unavailable'); }
        return $response + ['url' => $url, 'chain' => $chain];
    }
    throw new RuntimeException('too_many_redirects');
}

function detectAffiliateNetwork(array $urls): string
{
    $networks = ['braip', 'hotmart', 'eduzz', 'smartadv', 'buygoods', 'clickbank', 'digistore24'];
    foreach ($urls as $url) {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($networks as $network) {
            foreach (['com', 'net', 'com.br'] as $suffix) {
                $domain = $network . '.' . $suffix;
                if ($host === $domain || str_ends_with($host, '.' . $domain)) { return $network; }
            }
        }
    }
    return 'unknown';
}

function extractOfferMetadata(string $html, string $url): array
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $xpath = new DOMXPath($dom);
    $meta = [];
    foreach ($xpath->query('//meta[@content]') as $node) {
        $key = strtolower($node->getAttribute('property') ?: $node->getAttribute('name'));
        if ($key !== '') { $meta[$key] = $node->getAttribute('content'); }
    }
    $clean = static function (string $value, int $length): string {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        preg_match('/^.{0,' . $length . '}/us', $value, $matches);
        return $matches[0] ?? '';
    };
    $title = $clean($meta['og:title'] ?? $xpath->evaluate('string(//title)'), 180);
    if ($title === '' || preg_match('/just a moment|access denied|attention required|verify you are human|captcha/i', $title)) { throw new RuntimeException('page_unavailable'); }
    $language = strtolower($xpath->evaluate('string(//html/@lang)') ?: ($meta['og:locale'] ?? ''));
    $image = isset($meta['og:image']) ? absoluteImportUrl($url, $meta['og:image']) : '';
    return ['title' => $title, 'description' => $clean($meta['og:description'] ?? $meta['description'] ?? '', 500),
        'language' => str_starts_with($language, 'pt') ? 'pt' : (str_starts_with($language, 'en') ? 'en' : ''),
        'image_url' => publicImportUrl($image) ? $image : ''];
}

function generateLinkCampaign(string $affiliateUrl, ?callable $resolver = null, ?callable $transport = null): array
{
    if (!publicImportUrl($affiliateUrl)) { throw new InvalidArgumentException('Informe um link HTTP/HTTPS público de afiliado, sem credenciais ou portas especiais.'); }
    $metadata = []; $network = detectAffiliateNetwork([$affiliateUrl]); $status = 'basic'; $reason = '';
    $deadline = microtime(true) + 24;
    try {
        $response = fetchPublicResource($affiliateUrl, 2097152, $resolver, $transport, $deadline);
        $contentType = strtolower($response['headers']['content-type'] ?? '');
        if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml+xml')) { throw new RuntimeException('not_html'); }
        $metadata = extractOfferMetadata($response['body'], $response['url']);
        $network = detectAffiliateNetwork($response['chain']); $status = 'imported';
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'unsafe_destination') { throw new InvalidArgumentException('Este link ou um de seus destinos não é um endereço público permitido.'); }
        $reason = $error->getMessage();
    }
    $language = $metadata['language'] ?? '';
    if ($language === '') { $language = in_array($network, ['braip', 'hotmart', 'eduzz'], true) ? 'pt' : 'en'; }
    $name = $metadata['title'] ?? ($language === 'pt' ? 'Conheça a oferta' : 'Discover the offer');
    $slug = 'presell-' . bin2hex(random_bytes(6));
    $campaign = ['id' => $slug, 'slug' => $slug, 'name' => $name, 'network' => $network,
        'language' => $language, 'template' => 'modal', 'headline' => $name,
        'subtitle' => $metadata['description'] ?? '', 'price' => '', 'proof' => [], 'features' => [],
        'offer_url' => $affiliateUrl, 'cta_text' => $language === 'pt' ? 'Ver a oferta' : 'View offer',
        'import' => ['status' => $status, 'reason' => $reason, 'source_host' => isset($response) ? parse_url($response['url'], PHP_URL_HOST) : '', 'created_at' => gmdate(DATE_ATOM)],
    ];
    if (!empty($metadata['image_url'])) {
        try {
            $image = fetchPublicResource($metadata['image_url'], 5242880, $resolver, $transport, $deadline);
            $info = @getimagesizefromstring($image['body']);
            $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (is_array($info) && isset($extensions[$info['mime']])) {
                $name = hash('sha256', $image['body']) . '.' . $extensions[$info['mime']];
                $directory = appRoot() . '/storage/media';
                if (!is_dir($directory)) { mkdir($directory, 0775, true); }
                if (file_put_contents($directory . '/' . $name, $image['body'], LOCK_EX) !== false) { $campaign['image_file'] = $name; }
            }
        } catch (RuntimeException $error) { /* Metadata remains usable without an image. */ }
    }
    return $campaign;
}
