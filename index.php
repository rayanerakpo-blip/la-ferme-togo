<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'index';
$page_title = t('dashboard_title');
$u          = current_user();
$owner      = is_proprietaire();

/* --- Indicateurs --- */
$nbParcelles   = (int)fetch_value('SELECT COUNT(*) FROM parcelles');
$superficie    = (float)fetch_value('SELECT COALESCE(SUM(superficie),0) FROM parcelles');
$nbCultures    = (int)fetch_value('SELECT COUNT(*) FROM cultures WHERE stade <> "recolte"');
$nbAlertes     = (int)fetch_value('SELECT COUNT(*) FROM stocks WHERE quantite <= seuil_alerte');

if ($owner) {
    $nbTaches = (int)fetch_value('SELECT COUNT(*) FROM taches WHERE statut <> "termine"');
} else {
    $nbTaches = (int)fetch_value('SELECT COUNT(*) FROM taches WHERE statut <> "termine" AND (user_id = ? OR user_id IS NULL)', [$u['id']]);
}

$recettes = $depenses = 0.0;
if ($owner) {
    $recettes = (float)fetch_value('SELECT COALESCE(SUM(montant),0) FROM transactions_financieres WHERE type = "vente"');
    $depenses = (float)fetch_value('SELECT COALESCE(SUM(montant),0) FROM transactions_financieres WHERE type = "achat"');
}

/* --- Listes --- */
$myTasks = fetch_all(
    'SELECT t.*, p.nom AS parcelle FROM taches t
     LEFT JOIN parcelles p ON p.id = t.parcelle_id
     WHERE t.statut <> "termine" ' . ($owner ? '' : 'AND (t.user_id = ? OR t.user_id IS NULL) ') . '
     ORDER BY (t.date_echeance IS NULL), t.date_echeance ASC LIMIT 6',
    $owner ? [] : [$u['id']]
);

$lowStocks = fetch_all('SELECT * FROM stocks WHERE quantite <= seuil_alerte ORDER BY (quantite - seuil_alerte) ASC LIMIT 6');
$recos     = fetch_all('SELECT r.*, p.nom AS parcelle FROM recommandations r JOIN parcelles p ON p.id = r.parcelle_id ORDER BY r.created_at DESC LIMIT 5');

$actSummary = [];
if ($owner) {
    $actSummary = fetch_all(
        'SELECT activite,
                COALESCE(SUM(CASE WHEN type = "vente" THEN montant ELSE 0 END),0) AS recettes,
                COALESCE(SUM(CASE WHEN type = "achat"  THEN montant ELSE 0 END),0) AS depenses
         FROM transactions_financieres GROUP BY activite ORDER BY activite'
    );
}

require __DIR__ . '/includes/header.php';
?>

<div class="kpi-grid">
    <div class="kpi">
        <div class="label"><?= e(t('kpi_parcelles')) ?></div>
        <div class="value"><?= $nbParcelles ?></div>
        <div class="sub"><?= number_format($superficie, 2, ',', ' ') ?> <?= e(t('hectares')) ?> <?= e(lc(t('kpi_superficie'))) ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= e(t('kpi_cultures')) ?></div>
        <div class="value"><?= $nbCultures ?></div>
        <div class="sub"><?= e(season_label()) ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= e(t('kpi_taches')) ?></div>
        <div class="value"><?= $nbTaches ?></div>
        <div class="sub"><?= $owner ? e(t('all')) : e(t('my_tasks_today')) ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= e(t('kpi_alertes_stock')) ?></div>
        <div class="value <?= $nbAlertes ? 'warn' : '' ?>"><?= $nbAlertes ?></div>
        <div class="sub"><?= e(t('low_stocks')) ?></div>
    </div>
    <?php if ($owner): ?>
        <div class="kpi">
            <div class="label"><?= e(t('kpi_recettes')) ?></div>
            <div class="value"><?= e(money($recettes)) ?></div>
        </div>
        <div class="kpi">
            <div class="label"><?= e(t('kpi_depenses')) ?></div>
            <div class="value danger"><?= e(money($depenses)) ?></div>
        </div>
        <div class="kpi">
            <div class="label"><?= e(t('kpi_solde')) ?></div>
            <div class="value" style="color:<?= ($recettes - $depenses) >= 0 ? 'var(--green-700)' : 'var(--danger)' ?>">
                <?= e(money($recettes - $depenses)) ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><h2><?= e(t('quick_actions')) ?></h2></div>
    <div class="row-between" style="justify-content:flex-start;gap:.6rem">
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/cultures.php?action=new"><?= e(t('new_culture')) ?></a>
        <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/recoltes.php?action=new"><?= e(t('new_recolte')) ?></a>
        <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/irrigations.php?action=new"><?= e(t('new_irrigation')) ?></a>
        <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/taches.php?action=new"><?= e(t('new_tache')) ?></a>
        <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/recommandations.php"><?= e(t('generate_reco')) ?></a>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head">
            <h2><?= e(t('my_tasks_today')) ?></h2>
            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/taches.php"><?= e(t('details')) ?></a>
        </div>
        <?php if (!$myTasks): ?>
            <p class="empty"><?= e(t('no_records')) ?></p>
        <?php else: ?>
            <ul class="list-plain">
                <?php foreach ($myTasks as $task): ?>
                    <?php
                        $late = $task['date_echeance'] && $task['date_echeance'] < date('Y-m-d');
                    ?>
                    <li>
                        <span>
                            <?= e($task['titre']) ?>
                            <?php if ($task['parcelle']): ?><br><small class="muted"><?= e($task['parcelle']) ?></small><?php endif; ?>
                        </span>
                        <span class="right">
                            <?php if ($late): ?><span class="badge badge-red"><?= e(t('overdue')) ?></span><?php endif; ?>
                            <span class="badge badge-<?= $task['priorite'] === 'haute' ? 'red' : ($task['priorite'] === 'moyenne' ? 'amber' : 'gray') ?>"><?= e(te('priorite', $task['priorite'])) ?></span>
                            <br><small class="muted"><?= e(fr_date($task['date_echeance'])) ?></small>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <h2><?= e(t('recent_recommandations')) ?></h2>
            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/recommandations.php"><?= e(t('details')) ?></a>
        </div>
        <?php if (!$recos): ?>
            <p class="empty"><?= e(t('no_records')) ?></p>
        <?php else: ?>
            <?php foreach ($recos as $r): ?>
                <div class="reco <?= e($r['type']) ?>">
                    <div class="reco-head">
                        <strong><?= e(te('reco', $r['type'])) ?> — <?= e($r['parcelle']) ?></strong>
                        <span class="badge badge-blue"><?= e(te('reco_statut', $r['statut'])) ?></span>
                    </div>
                    <div><?= e($r['contenu']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('low_stocks')) ?></h2></div>
        <?php if (!$lowStocks): ?>
            <p class="empty"><?= e(t('stock_ok')) ?> ✅</p>
        <?php else: ?>
            <ul class="list-plain">
                <?php foreach ($lowStocks as $s): ?>
                    <li>
                        <span><?= e($s['produit']) ?> <small class="muted">(<?= e(te('cat', $s['categorie'])) ?>)</small></span>
                        <span class="right">
                            <span class="badge badge-red"><?= number_format((float)$s['quantite'], 0, ',', ' ') ?> / <?= number_format((float)$s['seuil_alerte'], 0, ',', ' ') ?> <?= e($s['unite']) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if ($owner): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= e(t('activities_summary')) ?></h2>
            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/finances.php"><?= e(t('nav_finances')) ?></a>
        </div>
        <?php if (!$actSummary): ?>
            <p class="empty"><?= e(t('no_records')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th><?= e(t('activite')) ?></th><th class="right"><?= e(t('recettes')) ?></th><th class="right"><?= e(t('depenses')) ?></th><th class="right"><?= e(t('solde')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($actSummary as $a): ?>
                        <?php $solde = (float)$a['recettes'] - (float)$a['depenses']; ?>
                        <tr>
                            <td><?= e(te('act', $a['activite'])) ?></td>
                            <td class="right"><?= e(money($a['recettes'])) ?></td>
                            <td class="right"><?= e(money($a['depenses'])) ?></td>
                            <td class="right" style="color:<?= $solde >= 0 ? 'var(--green-700)' : 'var(--danger)' ?>"><?= e(money($solde)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
