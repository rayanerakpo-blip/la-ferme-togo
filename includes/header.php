<?php
/**
 * En-tête commun : barre latérale de navigation + barre supérieure.
 */
require_once __DIR__ . '/functions.php';

$__u        = current_user();
$active     = $active ?? '';
$page_title = $page_title ?? t('nav_dashboard');
$__lang     = current_lang();
$__self     = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

$__nav = [
    ['index.php',           '📊', t('nav_dashboard')],
    ['parcelles.php',       '🗺️', t('nav_parcelles')],
    ['cultures.php',        '🌱', t('nav_cultures')],
    ['recoltes.php',        '🌾', t('nav_recoltes')],
    ['irrigations.php',     '💧', t('nav_irrigations')],
    ['taches.php',          '✅', t('nav_taches')],
    ['stocks.php',          '📦', t('nav_stocks')],
    ['transformations.php', '🏭', t('nav_transformations')],
];
// Modules réservés au propriétaire
if (is_proprietaire()) {
    $__nav[] = ['finances.php', '💰', t('nav_finances')];
}
$__nav[] = ['recommandations.php', '🤖', t('nav_recommandations')];
if (is_proprietaire()) {
    $__nav[] = ['utilisateurs.php', '👥', t('nav_utilisateurs')];
}
?>
<!DOCTYPE html>
<html lang="<?= e($__lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">🌱 <?= e(APP_NAME) ?><small><?= e(t('tagline')) ?></small></div>
        <nav>
            <?php foreach ($__nav as $item): ?>
                <a href="<?= e(BASE_URL . '/' . $item[0]) ?>" class="<?= $active === basename($item[0], '.php') ? 'active' : '' ?>">
                    <span class="ico"><?= $item[1] ?></span><?= e($item[2]) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="side-foot"><?= e(season_label()) ?> · <?= date('d/m/Y') ?></div>
    </aside>
    <div class="sidebar-backdrop" data-close-nav></div>

    <div class="main">
        <header class="topbar">
            <button class="menu-toggle" type="button" data-toggle-nav aria-label="Menu">☰</button>
            <h1><?= e($page_title) ?></h1>
            <div class="lang-switch">
                <?php foreach (LANGS as $code => $label):
                    $__q = $_GET;
                    $__q['lang'] = $code; ?>
                    <a href="<?= e($__self . '?' . http_build_query($__q)) ?>" class="<?= $__lang === $code ? 'active' : '' ?>"><?= e(strtoupper($code)) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="who">
                <?php if ($__u): ?>
                    <strong><?= e($__u['nom']) ?></strong>
                    <?= e(te('role', $__u['role'])) ?>
                <?php endif; ?>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(BASE_URL) ?>/logout.php"><?= e(t('nav_logout')) ?></a>
        </header>

        <main class="content">
            <?php foreach (get_flashes() as $fl): ?>
                <div class="alert alert-<?= $fl['type'] === 'error' ? 'error' : ($fl['type'] === 'warn' ? 'warn' : 'success') ?>">
                    <?= e($fl['message']) ?>
                </div>
            <?php endforeach; ?>
