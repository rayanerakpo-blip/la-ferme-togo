<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'recommandations';
$page_title = t('reco_title');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        if (($_POST['parcelle_id'] ?? '') === 'all') {
            $ids = array_column(fetch_all('SELECT id FROM parcelles'), 'id');
            $total = 0;
            foreach ($ids as $pid) {
                $total += generer_recommandations_parcelle((int)$pid);
            }
            flash('success', $total . ' ' . lc(t('reco_generated')));
        } else {
            $n = generer_recommandations_parcelle((int)$_POST['parcelle_id']);
            flash('success', $n > 0 ? t('reco_generated') : t('no_records'));
        }
    } elseif ($action === 'statut') {
        q('UPDATE recommandations SET statut = ? WHERE id = ?', [$_POST['statut'] ?? 'vue', (int)$_POST['id']]);
        flash('success', t('updated_success'));
    } elseif ($action === 'delete') {
        q('DELETE FROM recommandations WHERE id = ?', [(int)$_POST['id']]);
        flash('success', t('deleted_success'));
    }
    redirect('recommandations.php');
}

$parcelles = fetch_all('SELECT id, nom FROM parcelles ORDER BY nom');
$preselect = $_GET['parcelle_id'] ?? '';

$filtreType = $_GET['type'] ?? '';
$where = in_array($filtreType, ['plantation', 'irrigation'], true) ? 'WHERE r.type = ?' : '';
$recos = fetch_all(
    "SELECT r.*, p.nom AS parcelle FROM recommandations r JOIN parcelles p ON p.id = r.parcelle_id
     $where ORDER BY FIELD(r.priorite,'haute','moyenne','basse'), r.created_at DESC",
    $where ? [$filtreType] : []
);

require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('generate_reco')) ?></h2>
    </div>
    <p class="muted">
        Le moteur croise la culture en cours, le stade, la superficie, la saison (<?= e(season_label()) ?>)
        et le dernier apport hydrique, puis propose un calendrier de plantation et un conseil d'irrigation.
    </p>
    <form method="post" class="row-between" style="justify-content:flex-start;gap:.6rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <select name="parcelle_id" style="max-width:320px">
            <option value="all"><?= e(t('all')) ?> — <?= e(t('nav_parcelles')) ?></option>
            <?php foreach ($parcelles as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (string)$preselect === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">🤖 <?= e(t('generate_reco')) ?></button>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('reco_title')) ?></h2>
        <div class="row-between" style="gap:.5rem">
            <a class="btn btn-sm <?= $filtreType === '' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(BASE_URL) ?>/recommandations.php"><?= e(t('all')) ?></a>
            <a class="btn btn-sm <?= $filtreType === 'plantation' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(BASE_URL) ?>/recommandations.php?type=plantation"><?= e(t('reco_plantation')) ?></a>
            <a class="btn btn-sm <?= $filtreType === 'irrigation' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(BASE_URL) ?>/recommandations.php?type=irrigation"><?= e(t('reco_irrigation')) ?></a>
        </div>
    </div>

    <?php if (!$recos): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <?php foreach ($recos as $r): ?>
            <div class="reco <?= e($r['type']) ?>">
                <div class="reco-head">
                    <strong><?= e(te('reco', $r['type'])) ?> — <?= e($r['parcelle']) ?></strong>
                    <span>
                        <span class="badge badge-<?= $r['priorite'] === 'haute' ? 'red' : ($r['priorite'] === 'moyenne' ? 'amber' : 'gray') ?>"><?= e(te('priorite', $r['priorite'])) ?></span>
                        <span class="badge badge-blue"><?= e(te('reco_statut', $r['statut'])) ?></span>
                    </span>
                </div>
                <div><?= e($r['contenu']) ?></div>
                <div class="row-between" style="margin-top:.4rem">
                    <small class="muted"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></small>
                    <span class="no-print">
                        <?php if ($r['statut'] !== 'appliquee'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="statut">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="statut" value="appliquee">
                                <button class="btn btn-sm btn-primary" type="submit">✓ <?= e(t('mark_applied')) ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-outline" type="submit"><?= e(t('delete')) ?></button>
                        </form>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
