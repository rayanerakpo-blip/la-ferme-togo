<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'transformations';
$page_title = t('transformations_title');
$u          = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $tr = fetch_one('SELECT * FROM transformations WHERE id = ?', [(int)$_POST['id']]);
        if ($tr) {
            // Annule l'ajout du produit fini en stock.
            q(
                'UPDATE stocks SET quantite = GREATEST(0, quantite - ?), updated_at = NOW()
                 WHERE produit = ? AND categorie = "transforme"',
                [(float)$tr['quantite_produite'], te('prod', $tr['produit'])]
            );
            // Restitue le manioc brut consommé lors de la transformation.
            $maniocStock = fetch_one('SELECT * FROM stocks WHERE produit LIKE "%manioc%" AND categorie = "recolte" ORDER BY id LIMIT 1');
            if ($maniocStock) {
                q('UPDATE stocks SET quantite = quantite + ?, updated_at = NOW() WHERE id = ?',
                  [(float)$tr['quantite_manioc'], $maniocStock['id']]);
            }
            q('DELETE FROM transformations WHERE id = ?', [(int)$tr['id']]);
            flash('success', t('deleted_success'));
        }
    } else {
        $manioc   = (float)($_POST['quantite_manioc'] ?? 0);
        $produit  = $_POST['produit'] ?? 'gari';
        $produite = (float)($_POST['quantite_produite'] ?? 0);
        if ($manioc <= 0 || $produite <= 0) {
            flash('error', t('required_fields'));
        } else {
            $unite = $_POST['unite'] ?? 'kg';
            q(
                'INSERT INTO transformations (date_transformation, produit, quantite_manioc, quantite_produite, unite, user_id) VALUES (?,?,?,?,?,?)',
                [$_POST['date_transformation'] ?? date('Y-m-d'), $produit, $manioc, $produite, $unite, $u['id']]
            );
            // Intégration : le produit transformé entre en stock.
            $nom = te('prod', $produit);
            $existing = fetch_one('SELECT * FROM stocks WHERE produit = ? AND categorie = "transforme"', [$nom]);
            if ($existing) {
                q('UPDATE stocks SET quantite = quantite + ?, updated_at = NOW() WHERE id = ?', [$produite, $existing['id']]);
            } else {
                q('INSERT INTO stocks (produit, categorie, quantite, unite, emplacement, seuil_alerte) VALUES (?,?,?,?,?,0)',
                  [$nom, 'transforme', $produite, $unite, t('warehouse')]);
            }
            // Consommation du manioc brut si présent en stock "récolte".
            $maniocStock = fetch_one('SELECT * FROM stocks WHERE produit LIKE "%manioc%" AND categorie = "recolte" ORDER BY id LIMIT 1');
            if ($maniocStock) {
                $new = max(0, (float)$maniocStock['quantite'] - $manioc);
                q('UPDATE stocks SET quantite = ?, updated_at = NOW() WHERE id = ?', [$new, $maniocStock['id']]);
            }
            flash('success', t('created_success'));
        }
    }
    redirect('transformations.php');
}

$rows = fetch_all(
    'SELECT t.*, u.nom AS auteur FROM transformations t LEFT JOIN users u ON u.id = t.user_id
     ORDER BY t.date_transformation DESC, t.id DESC'
);
$totalManioc = (float)fetch_value('SELECT COALESCE(SUM(quantite_manioc),0) FROM transformations');
$totalProduit = (float)fetch_value('SELECT COALESCE(SUM(quantite_produite),0) FROM transformations');
$rendementGlobal = $totalManioc > 0 ? round($totalProduit / $totalManioc * 100, 1) : 0;

$showForm = ($_GET['action'] ?? '') === 'new';

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= e(t('new_transformation')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
            <div class="field">
                <label><?= e(t('date')) ?></label>
                <input type="date" name="date_transformation" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label><?= e(t('produit_fini')) ?></label>
                <select name="produit">
                    <?php foreach (['gari', 'tapioca', 'attieke'] as $p): ?>
                        <option value="<?= $p ?>"><?= e(te('prod', $p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('quantite_manioc')) ?></label>
                <input type="number" step="0.01" min="0" name="quantite_manioc" required>
            </div>
            <div class="field">
                <label><?= e(t('quantite_produite')) ?></label>
                <input type="number" step="0.01" min="0" name="quantite_produite" required>
            </div>
            <div class="field">
                <label><?= e(t('unite')) ?></label>
                <input type="text" name="unite" value="kg">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/transformations.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="kpi-grid">
    <div class="kpi">
        <div class="label"><?= e(t('quantite_manioc')) ?></div>
        <div class="value"><?= number_format($totalManioc, 0, ',', ' ') ?></div>
        <div class="sub">kg</div>
    </div>
    <div class="kpi">
        <div class="label"><?= e(t('quantite_produite')) ?></div>
        <div class="value"><?= number_format($totalProduit, 0, ',', ' ') ?></div>
        <div class="sub">kg</div>
    </div>
    <div class="kpi">
        <div class="label"><?= e(t('rendement_transfo')) ?></div>
        <div class="value"><?= $rendementGlobal ?>%</div>
    </div>
</div>
<div class="card">
    <div class="card-head">
        <h2><?= e(t('transformations_title')) ?></h2>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/transformations.php?action=new">+ <?= e(t('new_transformation')) ?></a>
    </div>
    <?php if (!$rows): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><?= e(t('date')) ?></th>
                        <th><?= e(t('produit_fini')) ?></th>
                        <th class="right"><?= e(t('quantite_manioc')) ?></th>
                        <th class="right"><?= e(t('quantite_produite')) ?></th>
                        <th class="right"><?= e(t('rendement_transfo')) ?></th>
                        <th><?= e(t('assigne_a')) ?></th>
                        <th class="right"><?= e(t('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $rdt = (float)$r['quantite_manioc'] > 0 ? round((float)$r['quantite_produite'] / (float)$r['quantite_manioc'] * 100, 1) : 0; ?>
                    <tr>
                        <td><?= e(fr_date($r['date_transformation'])) ?></td>
                        <td><span class="badge badge-amber"><?= e(te('prod', $r['produit'])) ?></span></td>
                        <td class="right"><?= number_format((float)$r['quantite_manioc'], 2, ',', ' ') ?> <?= e($r['unite']) ?></td>
                        <td class="right"><?= number_format((float)$r['quantite_produite'], 2, ',', ' ') ?> <?= e($r['unite']) ?></td>
                        <td class="right"><?= $rdt ?>%</td>
                        <td><?= e($r['auteur'] ?: '—') ?></td>
                        <td class="right">
                            <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit"><?= e(t('delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
