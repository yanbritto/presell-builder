<?php

declare(strict_types=1);

require __DIR__ . '/../app/config.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $submittedUser = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $submittedPass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (authenticateAdmin($submittedUser, $submittedPass)) {
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_config_fingerprint'] = hash('sha256', securePasswordHash());
        $_SESSION['admin_user'] = $submittedUser;
        redirect('/admin/dashboard.php');
    }

    $_SESSION['login_error'] = 'Credenciais inválidas.';
}

$errorMessage = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login administrativo</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h1>Presell Builder</h1>
        <p>Login administrativo</p>
        <?php if ($errorMessage !== ''): ?>
            <div class="alert-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post" action="/admin/login.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <label>
                Usuário
                <input type="text" name="username" required>
            </label>
            <label>
                Senha
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn-primary">Entrar</button>
        </form>
    </div>
</body>
</html>
