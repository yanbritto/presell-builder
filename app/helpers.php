<?php

declare(strict_types=1);

function appRoot(): string
{
    return dirname(__DIR__);
}

function env(string $key, mixed $default = null): mixed
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];

        foreach ([appRoot() . '/.env', appRoot() . '/.env.local'] as $file) {
            if (is_file($file)) {
                $parsed = parse_ini_file($file, true, INI_SCANNER_TYPED);
                if (is_array($parsed)) {
                    $cache = array_replace($cache, $parsed);
                }
            }
        }

        foreach ($_ENV as $name => $value) {
            $cache[$name] = $value;
        }

        foreach ($_SERVER as $name => $value) {
            if (str_starts_with((string) $name, 'APP_')) {
                $cache[$name] = $value;
            }
        }
    }

    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        session_cache_limiter('');
        session_set_cookie_params(['httponly' => true, 'secure' => isHttps(), 'samesite' => 'Lax', 'path' => '/']);
        session_start();
    }
}

function appBaseUrl(): string
{
    return (string) env('APP_BASE_URL', 'http://localhost:8000');
}

function redirect(string $path): never
{
    $location = str_starts_with($path, 'http') ? $path : appBaseUrl() . $path;
    header('Location: ' . $location);
    exit;
}

function readJsonFile(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) { throw new RuntimeException('Cannot read storage.'); }
    if (!flock($handle, LOCK_SH)) { fclose($handle); throw new RuntimeException('Cannot lock storage.'); }
    $raw = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) { throw new RuntimeException('Invalid storage.'); }
    return $decoded;
}

function writeJsonFile(string $path, array $data): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    updateJsonFile($path, static fn (array $existing): array => $data);
    return true;
}

function ensureStorageFiles(): void
{
    $base = appRoot() . '/storage';
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }

    $paths = [
        $base . '/campaigns.json',
        $base . '/traffic.json',
        $base . '/postbacks.json',
    ];

    foreach ($paths as $path) {
        if (!is_file($path)) {
            updateJsonFile($path, static fn (array $existing): array => $existing);
        }
    }
}

function createDefaultCampaign(): array
{
    return [
        'id' => 'demo-presell',
        'slug' => 'demo',
        'name' => 'Presell de Demo',
        'network' => 'braip',
        'headline' => 'Transforme cliques em decisões de compra com uma página quente e persuasiva.',
        'subtitle' => 'Estratégia de presell pronta para marketing de afiliados.',
        'price' => '97,00',
        'offer_url' => 'https://example.com/offer',
        'cta_text' => 'Quero ver a oferta',
        'proof' => [
            'Resultados reais sem truques e sem consentimento enganoso.',
            'Estrutura otimizada para página de review, bridge e checkout.',
            'Rastreio limpo de GCLID, subid e ValueTrack sem cliques como venda.',
        ],
        'features' => [
            'Modal de aviso claro',
            'Bridge page com tracking',
            'Review page com prova social',
            'Postback de conversão validado',
        ],
        'tracking' => [
            'allowed_parameters' => ['gclid', 'wbraid', 'gbraid', 'utm_source', 'utm_campaign', 'subid', 'sub_id'],
        ],
    ];
}

function loadCampaigns(): array
{
    ensureStorageFiles();
    $path = appRoot() . '/storage/campaigns.json';

    $campaigns = readJsonFile($path, []);
    if ($campaigns === []) {
        $campaigns = updateJsonFile($path, static fn (array $current): array => $current === [] ? [createDefaultCampaign()] : $current);
    }

    return $campaigns;
}

function getCampaignBySlug(string $slug): ?array
{
    foreach (loadCampaigns() as $campaign) {
        if (($campaign['slug'] ?? '') === $slug) {
            return $campaign;
        }
    }

    return null;
}

function sanitizeTrackingData(array $input): array
{
    $allowed = trackingKeys();
    $data = [];

    foreach ($allowed as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }

        if (!is_scalar($input[$key])) { continue; }
        $value = trim((string) $input[$key]);
        if (strlen($value) > 512 || preg_match('/[\x00-\x1F\x7F]/', $value)) { continue; }
        if ($value === '' || $value === 'null') {
            continue;
        }

        $data[$key] = $value;
    }

    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
        if (isset($data[$key]) && preg_match('/^\s*$/', $data[$key])) {
            unset($data[$key]);
        }
    }

    return $data;
}

function countApprovedSales(array $events): int
{
    $orders = [];
    foreach ($events as $event) {
        if (($event['accepted'] ?? false) !== true || ($event['verified'] ?? false) !== true || ($event['source'] ?? '') !== 'postback'
            || empty($event['order_id']) || empty($event['campaign'])) { continue; }
        $key = json_encode([$event['campaign'], $event['order_id']]);
        $status = strtolower((string) ($event['status'] ?? ''));
        if (in_array($status, ['refunded', 'chargeback', 'cancelled'], true)) { $orders[$key] = false; }
        elseif (in_array($status, ['approved', 'paid', 'completed'], true) && ($orders[$key] ?? null) !== false) { $orders[$key] = true; }
    }
    return count(array_filter($orders));
}

function appendJsonLog(string $fileName, array $entry): void
{
    $path = appRoot() . '/storage/' . $fileName;
    updateJsonFile($path, static function (array $events) use ($entry): array {
        $events[] = $entry;
        return $events;
    });
}

function verifyPostbackSignature(array $payload, string $secret): bool
{
    if ($secret === '' || empty($payload['signature'])) {
        return false;
    }
    foreach (['campaign', 'status', 'amount', 'order_id', 'signature'] as $key) {
        if (!isset($payload[$key]) || !is_string($payload[$key]) || str_contains($payload[$key], '|')) { return false; }
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $payload['signature'])) { return false; }

    $expected = hash_hmac('sha256', implode('|', [
        (string) ($payload['campaign'] ?? ''),
        (string) ($payload['status'] ?? ''),
        (string) ($payload['amount'] ?? ''),
        (string) ($payload['order_id'] ?? ''),
    ]), $secret);

    return hash_equals($expected, (string) $payload['signature']);
}

function add_query_arg(string $url, string $key, string $value): string
{
    $parsed = parse_url($url);
    $query = $parsed['query'] ?? '';
    $pairs = [];

    if ($query !== '') {
        parse_str($query, $pairs);
    }

    $pairs[$key] = $value;
    $parsed['query'] = http_build_query($pairs);

    $built = '';
    if (isset($parsed['scheme'])) {
        $built .= $parsed['scheme'] . '://';
    }
    if (isset($parsed['host'])) {
        $built .= $parsed['host'];
    }
    if (isset($parsed['port'])) {
        $built .= ':' . $parsed['port'];
    }
    if (isset($parsed['path'])) {
        $built .= $parsed['path'];
    }
    if (isset($parsed['query']) && $parsed['query'] !== '') {
        $built .= '?' . $parsed['query'];
    }
    if (isset($parsed['fragment'])) {
        $built .= '#' . $parsed['fragment'];
    }

    return $built;
}

function csrfToken(): string
{
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    startSession();
    $expected = $_SESSION['csrf_token'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($expected) || $expected === '' || !is_string($token) || !hash_equals($expected, $token)) {
        http_response_code(419);
        echo 'Invalid CSRF token.';
        exit;
    }
}

function adminRequired(): void
{
    startSession();
    $hash = securePasswordHash();
    if (empty($_SESSION['admin_authenticated']) || $hash === ''
        || !hash_equals(hash('sha256', $hash), (string) ($_SESSION['admin_config_fingerprint'] ?? ''))) {
        unset($_SESSION['admin_authenticated']);
        redirect('/admin/login.php');
    }
}

function securePasswordHash(): string
{
    return (string) env('APP_ADMIN_PASSWORD_HASH', '');
}

function isHttps(): bool
{
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function updateJsonFile(string $path, callable $update): array
{
    if (!is_dir(dirname($path))) { mkdir(dirname($path), 0775, true); }
    $handle = fopen($path, 'c+b');
    if ($handle === false) { throw new RuntimeException('Cannot open storage.'); }
    try {
        if (!flock($handle, LOCK_EX)) { throw new RuntimeException('Cannot lock storage.'); }
        $raw = stream_get_contents($handle);
        $data = trim($raw) === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) { throw new RuntimeException('Invalid storage.'); }
        $data = $update($data);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Cannot save storage.');
        }
        return $data;
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function trackingKeys(): array
{
    return ['gclid', 'gbraid', 'wbraid', 'fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'subid', 'sub_id', 'source', 'network', 'value', 'campaignid', 'adgroupid', 'creative', 'keyword', 'matchtype', 'device', 'placement', 'network', 'targetid', 'loc_physical_ms'];
}

function consentAccepted(): bool
{
    return ($_COOKIE['presell_consent'] ?? '') === 'accepted';
}

function consentTracking(array $input): array
{
    return consentAccepted() ? sanitizeTrackingData($input) : [];
}

function validOfferUrl(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
        && !preg_match('/[\x00-\x20\x7F]/', $url)
        && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null;
}

function offerUrl(array $campaign, array $input): string
{
    $url = (string) ($campaign['offer_url'] ?? '');
    if (!validOfferUrl($url)) { return '#'; }
    foreach (consentTracking($input) as $key => $value) { $url = add_query_arg($url, $key, $value); }
    return $url;
}

function authenticateAdmin(string $username, string $password): bool
{
    $authenticated = false;
    updateJsonFile(appRoot() . '/storage/login_attempts.json', static function (array $attempts) use ($username, $password, &$authenticated): array {
        $now = time();
        $attempts = array_values(array_filter($attempts, static fn (array $entry): bool => ($entry['time'] ?? 0) > $now - 900));
        if (count($attempts) >= 5) { return $attempts; }
        $hash = securePasswordHash();
        $authenticated = $hash !== '' && $username === (string) env('APP_ADMIN_USER', '') && password_verify($password, $hash);
        if ($authenticated) { return []; }
        $attempts[] = ['time' => $now];
        return $attempts;
    });
    return $authenticated;
}
