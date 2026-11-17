<?php
/**
 * Installateur : crée la base, les tables et les comptes par défaut.
 * À exécuter une seule fois via http://localhost/.../install.php
 */
require_once __DIR__ . '/includes/functions.php';

$messages = [];
$errors   = [];

/* Sécurité : si l'application est déjà installée, on verrouille l'installateur. */
$installed = false;
try {
    $dsnCheck = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $check    = new PDO($dsnCheck, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $installed = (int)$check->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (PDOException $e) {
    $installed = false; // Base ou table absente : l'installation reste possible.
}

function run(string $dsn, string $user, string $pass, array $sqls, array &$errors): void
{
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        $errors[] = 'Connexion impossible : ' . $e->getMessage();
        return;
    }
    foreach ($sqls as $i => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $errors[] = 'Erreur sur la requête n°' . ($i + 1) . ' : ' . $e->getMessage();
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$installed) {
    // 1) Créer la base
    try {
        $root = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $messages[] = 'Base de données « ' . DB_NAME . ' » prête.';
    } catch (PDOException $e) {
        $errors[] = 'Impossible de créer la base : ' . $e->getMessage();
    }

    if (!$errors) {
        // 2) Créer les tables + données de démonstration
        $file = __DIR__ . '/sql/schema.sql';
        if (!is_file($file)) {
            $errors[] = 'Fichier sql/schema.sql introuvable.';
        } else {
            $sql = file_get_contents($file);
            $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            run($dsn, DB_USER, DB_PASS, $statements, $errors);
            if (!$errors) {
                $messages[] = 'Tables et données de démonstration créées.';
            }
        }
    }

    if (!$errors) {
        // 3) Comptes par défaut
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($count === 0) {
                $stmt = $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe, role, langue, actif) VALUES (?,?,?,?,?,1)');
                $stmt->execute(['Propriétaire', 'proprietaire@laferme.tg', password_hash('admin123', PASSWORD_DEFAULT), 'proprietaire', 'fr']);
                $stmt->execute(['Collaborateur', 'collaborateur@laferme.tg', password_hash('collab123', PASSWORD_DEFAULT), 'collaborateur', 'fr']);
                $messages[] = 'Comptes par défaut créés.';
            } else {
                $messages[] = 'Des utilisateurs existent déjà, aucun compte ajouté.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Création des comptes impossible : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:640px">
    <h1>🌱 Installation de <?= e(APP_NAME) ?></h1>
    <p class="muted">Crée la base <strong><?= e(DB_NAME) ?></strong> sur <strong><?= e(DB_HOST) ?></strong>
       (utilisateur <strong><?= e(DB_USER) ?></strong>).</p>

    <?php if ($messages): ?>
        <div class="alert alert-success">
            <ul><?php foreach ($messages as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
            <p>Vérifiez les identifiants dans <code>config/config.php</code>.</p>
        </div>
    <?php endif; ?>

    <?php if ($installed): ?>
        <div class="alert alert-warn">
            🔒 L'application est déjà installée. L'installateur est verrouillé pour protéger vos données.
        </div>
        <p class="muted">Pour réinstaller, supprimez d'abord la base <strong><?= e(DB_NAME) ?></strong>
           (ou videz la table <code>users</code>).</p>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/login.php">Aller à la connexion</a>
    <?php elseif (!$messages && !$errors): ?>
        <form method="post">
            <p>Ce script va (ré)initialiser les tables et insérer des données de démonstration.</p>
            <button class="btn btn-primary" type="submit">Lancer l'installation</button>
        </form>
    <?php else: ?>
        <?php if (!$errors): ?>
            <div class="install-creds">
                <p><strong>Comptes de démonstration</strong></p>
                <p>Propriétaire : <code>proprietaire@laferme.tg</code> / <code>admin123</code></p>
                <p>Collaborateur : <code>collaborateur@laferme.tg</code> / <code>collab123</code></p>
            </div>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/login.php">Aller à la connexion</a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/install.php">Réessayer</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
