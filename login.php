<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['mot_de_passe'] ?? '';

    $user = fetch_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !password_verify($pass, $user['mot_de_passe'])) {
        $error = t('invalid_credentials');
    } elseif ((int)$user['actif'] !== 1) {
        $error = t('inactive_account');
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['lang']    = $user['langue'];
        flash('success', t('welcome_back') . ', ' . $user['nom'] . ' !');
        redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t('login_title')) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1>🌱 <?= e(APP_NAME) ?></h1>
    <p class="muted"><?= e(t('login_title')) ?></p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email"><?= e(t('email')) ?></label>
            <input type="email" id="email" name="email" required autofocus
                   value="<?= e($_POST['email'] ?? '') ?>" placeholder="vous@exemple.com">
        </div>
        <div class="field">
            <label for="mot_de_passe"><?= e(t('password')) ?></label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?= e(t('sign_in')) ?></button>
    </form>

    <p class="muted center" style="margin-top:1rem;font-size:.8rem;">
        Démo : proprietaire@laferme.tg / admin123
    </p>
</div>
</body>
</html>
