<?php
declare(strict_types=1);
require __DIR__ . '/../app/config.php';

// Bootstrap is available only through a loopback connection and a local Host.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || !preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?$/i', $_SERVER['HTTP_HOST'] ?? '')) {
    http_response_code(403);
    exit('Local setup only.');
}
if (securePasswordHash() !== '') { redirect('/admin/login.php'); }
startSession();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';
    if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $username)
        || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")
        || $password !== $confirmation) {
        $error = 'Informe um usuário válido e confirme uma senha de 12 a 72 bytes.';
    } else {
        $path = appRoot() . (is_file(appRoot() . '/.env.local') ? '/.env.local' : '/.env');
        $handle = fopen($path, 'c+b');
        if ($handle === false) {
            $error = 'Não foi possível abrir a configuração local.';
        } else {
            try {
                if (!flock($handle, LOCK_EX)) { throw new RuntimeException('Lock failed.'); }
                $raw = stream_get_contents($handle);
                $current = trim($raw) === '' ? [] : parse_ini_string($raw, false, INI_SCANNER_RAW);
                if (!is_array($current)) { throw new RuntimeException('Invalid configuration.'); }
                if (!empty($current['APP_ADMIN_PASSWORD_HASH'])) { throw new RuntimeException('Already configured.'); }
                $lines = preg_split('/\r\n|\n|\r/', $raw);
                $lines = array_values(array_filter($lines, static fn (string $line): bool => !preg_match('/^\s*APP_ADMIN_(USER|PASSWORD_HASH)\s*=/', $line)));
                $lines[] = 'APP_ADMIN_USER="' . $username . '"';
                $lines[] = 'APP_ADMIN_PASSWORD_HASH="' . password_hash($password, PASSWORD_DEFAULT) . '"';
                $content = implode(PHP_EOL, $lines) . PHP_EOL;
                rewind($handle);
                if (!ftruncate($handle, 0) || fwrite($handle, $content) !== strlen($content) || !fflush($handle)) {
                    throw new RuntimeException('Save failed.');
                }
                $_SESSION['setup_complete'] = true;
            } catch (Throwable $exception) {
                $error = 'Não foi possível salvar. Confira as permissões ou se o administrador já foi configurado.';
            } finally { flock($handle, LOCK_UN); fclose($handle); }
            if ($error === '') {
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                redirect('/admin/login.php');
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar administrador | Presell Builder</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-body">
    <main class="auth-card">
        <h1>Crie seu acesso</h1>
        <p>Defina o usuário e a senha para criar suas páginas. Esta configuração inicial está disponível apenas neste computador.</p>
        <?php if ($error !== ''): ?><div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" action="/admin/setup.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <label>Usuário<input type="text" name="username" value="admin" pattern="[A-Za-z0-9_.-]{1,64}" autocomplete="username" required></label>
            <label>Nova senha<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <label>Confirmar senha<input type="password" name="confirmation" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <p>Use uma senha nova, com pelo menos 12 caracteres.</p>
            <button type="submit" class="btn-primary">Salvar e ir para o login</button>
        </form>
    </main>
</body>
</html>
