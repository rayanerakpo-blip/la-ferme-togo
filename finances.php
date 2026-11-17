<?php
require_once __DIR__ . '/includes/functions.php';
require_proprietaire();

$active     = 'finances';
$page_title = t('finances_title');
$u          = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        q('DELETE FROM transactions_financieres WHERE id = ?', [(int)$_POST['id']]);
        flash('success', t('deleted_success'));
    } else {
        $data = [
            $_POST['type'] ?? 'achat',
            $_POST['activite'] ?? 'autre',
            trim($_POST['libelle'] ?? ''),
            (float)($_POST['montant'] ?? 0),
            ($_POST['quantite'] ?? '') !== '' ? (float)$_POST['quantite'] : null,
            trim($_POST['unite'] ?? ''),
            $_POST['date_op'] ?? date('Y-m-d'),
            $u['id'],
        ];
        if ($data[2] === '' || $data[3] <= 0) {
            flash('error', t('required_fields'));
        } elseif ($action === 'update') {
            $data[] = (int)$_POST['id'];
            q('UPDATE transactions_financieres SET type=?, activite=?, libelle=?, montant=?, quantite=?, unite=?, date_op=?, user_id=? WHERE id=?', $data);
            flash('success', t('updated_success'));
        } else {
            q('INSERT INTO transactions_financieres (type, activite, libelle, montant, quantite, unite, date_op, user_id) VALUES (?,?,?,?,?,?,?,?)', $data);
            flash('success', t('created_success'));
        }
    }
    redirect('finances.php');
}

$edit   = null;
$action = $_GET['action'] ?? '';
if ($action === 'edit' && isset($_GET['id'])) {
    $edit = fetch_one('SELECT * FROM transactions_financieres WHERE id = ?', [(int)$_GET['id']]);
} elseif ($action === 'new') {
    $edit = [];
}
$showForm = $edit !== null;

/* --- Période --- */
$periode = $_GET['periode'] ?? 'annee';
$periodSql = '';
if ($periode === 'mois') {
    $periodSql = 'AND date_op >= DATE_FORMAT(NOW(), "%Y-%m-01")';
} elseif ($periode === 'annee') {
    $periodSql = 'AND YEAR(date_op) = YEAR(NOW())';
}

$transactions = fetch_all(
    "SELECT tf.*, u.nom AS auteur FROM transactions_financieres tf
     LEFT JOIN users u ON u.id = tf.user_id
     WHERE 1=1 $periodSql
     ORDER BY tf.date_op DESC, tf.id DESC"
);

$recettes = (float)fetch_value("SELECT COALESCE(SUM(montant),0) FROM transactions_financieres WHERE type='vente' $periodSql");
$depenses = (float)fetch_value("SELECT COALESCE(SUM(montant),0) FROM transactions_financieres WHERE type='achat' $periodSql");

$parActivite = fetch_all(
    "SELECT activite,
            COALESCE(SUM(CASE WHEN type='vente' THEN montant ELSE 0 END),0) AS recettes,
            COALESCE(SUM(CASE WHEN type='achat'  THEN montant ELSE 0 END),0) AS depenses
     FROM transactions_financieres WHERE 1=1 $periodSql GROUP BY activite ORDER BY activite"
);

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= empty($edit['id']) ? e(t('new_transaction')) : e(t('details')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= empty($edit['id']) ? 'create' : 'update' ?>">
        <?php if (!empty($edit['id'])): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label><?= e(t('type')) ?></label>
                <select name="type">
                    <option value="vente" <?= ($edit['type'] ?? '') === 'vente' ? 'selected' : '' ?>><?= e(t('type_vente')) ?></option>
                    <option value="achat" <?= ($edit['type'] ?? '') === 'achat' ? 'selected' : '' ?>><?= e(t('type_achat')) ?></option>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('activite')) ?></label>
                <select name="activite">
                    <?php foreach (['soja', 'manioc', 'maraichage', 'elevage', 'autre'] as $a): ?>
                        <option value="<?= $a ?>" <?= ($edit['activite'] ?? 'autre') === $a ? 'selected' : '' ?>><?= e(te('act', $a)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="grid-column:1/-1">
                <label><?= e(t('libelle')) ?></label>
                <input type="text" name="libelle" required value="<?= e($edit['libelle'] ?? '') ?>" placeholder="Vente gari, achat engrais…">
            </div>
            <div class="field">
                <label><?= e(t('montant')) ?> (<?= e(CURRENCY) ?>)</label>
                <input type="number" step="0.01" min="0" name="montant" required value="<?= e($edit['montant'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('quantite')) ?> <small class="muted">(<?= e(t('optional')) ?>)</small></label>
                <input type="number" step="0.01" min="0" name="quantite" value="<?= e($edit['quantite'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('unite')) ?></label>
                <input type="text" name="unite" value="<?= e($edit['unite'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('date')) ?></label>
                <input type="date" name="date_op" required value="<?= e($edit['date_op'] ?? date('Y-m-d')) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/finances.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="kpi-grid">
    <div class="kpi">
        <div class="label"><?= e(t('recettes')) ?></div>
        <div class="value" style="color:var(--green-700)"><?= e(money($recettes)) ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= e(t('depenses')) ?></div>
        <div class="value" style="color:var(--danger)"><?= e(money($depenses)) ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= e(t('solde')) ?></div>
        <div class="value" style="color:<?= ($recettes - $depenses) >= 0 ? 'var(--green-700)' : 'var(--danger)' ?>"><?= e(money($recettes - $depenses)) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('period')) ?></h2>
        <div class="row-between" style="gap:.5rem">
            <?php foreach (['mois' => t('this_month'), 'annee' => t('this_year'), 'tout' => t('all_time')] as $k => $lab): ?>
                <a class="btn btn-sm <?= $periode === $k ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(BASE_URL) ?>/finances.php?periode=<?= $k ?>"><?= e($lab) ?></a>
            <?php endforeach; ?>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/finances.php?action=new">+ <?= e(t('new_transaction')) ?></a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr><th><?= e(t('activite')) ?></th><th class="right"><?= e(t('recettes')) ?></th><th class="right"><?= e(t('depenses')) ?></th><th class="right"><?= e(t('rentabilite')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($parActivite as $a): ?>
                <?php $solde = (float)$a['recettes'] - (float)$a['depenses']; ?>
                <tr>
                    <td><?= e(te('act', $a['activite'])) ?></td>
                    <td class="right"><?= e(money($a['recettes'])) ?></td>
                    <td class="right"><?= e(money($a['depenses'])) ?></td>
                    <td class="right" style="color:<?= $solde >= 0 ? 'var(--green-700)' : 'var(--danger)' ?>"><?= e(money($solde)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$parActivite): ?><tr><td colspan="4" class="empty"><?= e(t('no_records')) ?></td></tr><?php endif; ?>
            </tbody>
            <tfoot>
                <tr><td><?= e(t('solde')) ?></td><td class="right"><?= e(money($recettes)) ?></td><td class="right"><?= e(money($depenses)) ?></td><td class="right"><?= e(money($recettes - $depenses)) ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2><?= e(t('finances_title')) ?></h2></div>
    <?php if (!$transactions): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><?= e(t('date')) ?></th>
                        <th><?= e(t('type')) ?></th>
                        <th><?= e(t('libelle')) ?></th>
                        <th><?= e(t('activite')) ?></th>
                        <th class="right"><?= e(t('montant')) ?></th>
                        <th class="right"><?= e(t('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $tr): ?>
                    <tr>
                        <td><?= e(fr_date($tr['date_op'])) ?></td>
                        <td><span class="badge badge-<?= $tr['type'] === 'vente' ? 'green' : 'red' ?>"><?= e($tr['type'] === 'vente' ? t('type_vente') : t('type_achat')) ?></span></td>
                        <td><?= e($tr['libelle']) ?><?php if ($tr['quantite']): ?><br><small class="muted"><?= number_format((float)$tr['quantite'], 2, ',', ' ') ?> <?= e($tr['unite']) ?></small><?php endif; ?></td>
                        <td><?= e(te('act', $tr['activite'])) ?></td>
                        <td class="right"><?= e(money($tr['montant'])) ?></td>
                        <td class="right">
                            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/finances.php?action=edit&id=<?= (int)$tr['id'] ?>"><?= e(t('edit')) ?></a>
                            <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$tr['id'] ?>">
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
