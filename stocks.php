<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'stocks';
$page_title = t('stocks_title');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        q('DELETE FROM stocks WHERE id = ?', [(int)$_POST['id']]);
        flash('success', t('deleted_success'));
    } elseif ($action === 'move') {
        $delta = (float)($_POST['delta'] ?? 0);
        $st = fetch_one('SELECT * FROM stocks WHERE id = ?', [(int)$_POST['id']]);
        if ($st && $delta != 0) {
            $new = max(0, (float)$st['quantite'] + $delta);
            q('UPDATE stocks SET quantite = ?, updated_at = NOW() WHERE id = ?', [$new, $st['id']]);
            flash('success', t('updated_success'));
        }
    } else {
        $data = [
            trim($_POST['produit'] ?? ''),
            $_POST['categorie'] ?? 'intrant',
            (float)($_POST['quantite'] ?? 0),
            $_POST['unite'] ?? 'kg',
            trim($_POST['emplacement'] ?? ''),
            (float)($_POST['seuil_alerte'] ?? 0),
        ];
        if ($data[0] === '') {
            flash('error', t('required_fields'));
        } elseif ($action === 'update') {
            $data[] = (int)$_POST['id'];
            q('UPDATE stocks SET produit=?, categorie=?, quantite=?, unite=?, emplacement=?, seuil_alerte=?, updated_at=NOW() WHERE id=?', $data);
            flash('success', t('updated_success'));
        } else {
            q('INSERT INTO stocks (produit, categorie, quantite, unite, emplacement, seuil_alerte) VALUES (?,?,?,?,?,?)', $data);
            flash('success', t('created_success'));
        }
    }
    redirect('stocks.php');
}

$edit   = null;
$action = $_GET['action'] ?? '';
if ($action === 'edit' && isset($_GET['id'])) {
    $edit = fetch_one('SELECT * FROM stocks WHERE id = ?', [(int)$_GET['id']]);
} elseif ($action === 'new') {
    $edit = [];
}
$showForm = $edit !== null;

$cat = $_GET['categorie'] ?? '';
$where = in_array($cat, ['intrant', 'recolte', 'transforme'], true) ? 'WHERE categorie = ?' : '';
$stocks = fetch_all("SELECT * FROM stocks $where ORDER BY categorie, produit", $where ? [$cat] : []);

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= empty($edit['id']) ? e(t('new_stock')) : e(t('details')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= empty($edit['id']) ? 'create' : 'update' ?>">
        <?php if (!empty($edit['id'])): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label><?= e(t('produit')) ?></label>
                <input type="text" name="produit" required value="<?= e($edit['produit'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('categorie')) ?></label>
                <select name="categorie">
                    <?php foreach (['intrant', 'recolte', 'transforme'] as $c): ?>
                        <option value="<?= $c ?>" <?= ($edit['categorie'] ?? '') === $c ? 'selected' : '' ?>><?= e(te('cat', $c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('quantite')) ?></label>
                <input type="number" step="0.01" min="0" name="quantite" value="<?= e($edit['quantite'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('unite')) ?></label>
                <input type="text" name="unite" value="<?= e($edit['unite'] ?? 'kg') ?>">
            </div>
            <div class="field">
                <label><?= e(t('emplacement')) ?></label>
                <input type="text" name="emplacement" value="<?= e($edit['emplacement'] ?? t('warehouse')) ?>">
            </div>
            <div class="field">
                <label><?= e(t('seuil_alerte')) ?></label>
                <input type="number" step="0.01" min="0" name="seuil_alerte" value="<?= e($edit['seuil_alerte'] ?? 0) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/stocks.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="card-head">
        <h2><?= e(t('stocks_title')) ?></h2>
        <div class="row-between" style="gap:.5rem">
            <a class="btn btn-sm <?= $cat === '' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(BASE_URL) ?>/stocks.php"><?= e(t('all')) ?></a>
            <?php foreach (['intrant', 'recolte', 'transforme'] as $c): ?>
                <a class="btn btn-sm <?= $cat === $c ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(BASE_URL) ?>/stocks.php?categorie=<?= $c ?>"><?= e(te('cat', $c)) ?></a>
            <?php endforeach; ?>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/stocks.php?action=new">+ <?= e(t('new_stock')) ?></a>
        </div>
    </div>
    <?php if (!$stocks): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><?= e(t('produit')) ?></th>
                        <th><?= e(t('categorie')) ?></th>
                        <th class="right"><?= e(t('quantite')) ?></th>
                        <th><?= e(t('emplacement')) ?></th>
                        <th><?= e(t('stock_status')) ?></th>
                        <th class="right"><?= e(t('adjust_quantity')) ?></th>
                        <th class="right"><?= e(t('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stocks as $s): ?>
                    <?php $low = (float)$s['quantite'] <= (float)$s['seuil_alerte']; ?>
                    <tr>
                        <td><strong><?= e($s['produit']) ?></strong></td>
                        <td><span class="badge badge-blue"><?= e(te('cat', $s['categorie'])) ?></span></td>
                        <td class="right"><?= number_format((float)$s['quantite'], 2, ',', ' ') ?> <?= e($s['unite']) ?></td>
                        <td><?= e($s['emplacement'] ?: '—') ?></td>
                        <td>
                            <?php if ($low): ?>
                                <span class="badge badge-red"><?= e(t('stock_faible')) ?></span>
                                <small class="muted">≤ <?= number_format((float)$s['seuil_alerte'], 0, ',', ' ') ?></small>
                            <?php else: ?>
                                <span class="badge badge-green"><?= e(t('stock_ok')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="right">
                            <form method="post" class="inline-form" style="display:flex;gap:.25rem;justify-content:flex-end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <input type="number" step="1" name="delta" style="width:70px;padding:.25rem" placeholder="±">
                                <button class="btn btn-sm btn-outline" type="submit">OK</button>
                            </form>
                        </td>
                        <td class="right">
                            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/stocks.php?action=edit&id=<?= (int)$s['id'] ?>"><?= e(t('edit')) ?></a>
                            <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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
