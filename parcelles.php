<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'parcelles';
$page_title = t('parcelles_title');

/* --- Traitement des actions --- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        q('DELETE FROM parcelles WHERE id = ?', [(int)$_POST['id']]);
        flash('success', t('deleted_success'));
    } else {
        $data = [
            trim($_POST['nom'] ?? ''),
            trim($_POST['zone'] ?? ''),
            (float)($_POST['superficie'] ?? 0),
            $_POST['statut'] ?? 'libre',
        ];
        if ($data[0] === '') {
            flash('error', t('required_fields'));
        } elseif ($action === 'update') {
            $data[] = (int)$_POST['id'];
            q('UPDATE parcelles SET nom=?, zone=?, superficie=?, statut=? WHERE id=?', $data);
            flash('success', t('updated_success'));
        } else {
            q('INSERT INTO parcelles (nom, zone, superficie, statut) VALUES (?,?,?,?)', $data);
            flash('success', t('created_success'));
        }
    }
    redirect('parcelles.php');
}

/* --- Formulaire ? --- */
$edit  = null;
$action = $_GET['action'] ?? '';
if ($action === 'edit' && isset($_GET['id'])) {
    $edit = fetch_one('SELECT * FROM parcelles WHERE id = ?', [(int)$_GET['id']]);
} elseif ($action === 'new') {
    $edit = [];
}
$showForm = $edit !== null;

/* --- Liste --- */
$parcelles = fetch_all(
    'SELECT p.*,
            (SELECT c.nom FROM cultures c WHERE c.parcelle_id = p.id AND c.stade <> "recolte" ORDER BY c.date_semis DESC LIMIT 1) AS culture_actuelle
     FROM parcelles p ORDER BY p.nom'
);

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= empty($edit['id']) ? e(t('new_parcelle')) : e(t('edit_parcelle')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= empty($edit['id']) ? 'create' : 'update' ?>">
        <?php if (!empty($edit['id'])): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label><?= e(t('parcelle_name')) ?></label>
                <input type="text" name="nom" required value="<?= e($edit['nom'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('zone')) ?></label>
                <input type="text" name="zone" value="<?= e($edit['zone'] ?? '') ?>" placeholder="Site principal, Amdane…">
            </div>
            <div class="field">
                <label><?= e(t('superficie')) ?></label>
                <input type="number" step="0.01" min="0" name="superficie" value="<?= e($edit['superficie'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('statut')) ?></label>
                <select name="statut">
                    <?php foreach (['libre', 'en_culture', 'repos'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($edit['statut'] ?? '') === $s ? 'selected' : '' ?>><?= e(te('statut', $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/parcelles.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="card-head">
        <h2><?= e(t('parcelles_title')) ?></h2>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/parcelles.php?action=new">+ <?= e(t('new_parcelle')) ?></a>
    </div>
    <?php if (!$parcelles): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><?= e(t('parcelle_name')) ?></th>
                        <th><?= e(t('zone')) ?></th>
                        <th class="right"><?= e(t('superficie')) ?></th>
                        <th><?= e(t('statut')) ?></th>
                        <th><?= e(t('culture_actuelle')) ?></th>
                        <th class="right"><?= e(t('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($parcelles as $p): ?>
                    <tr>
                        <td><strong><?= e($p['nom']) ?></strong></td>
                        <td><?= e($p['zone'] ?: '—') ?></td>
                        <td class="right"><?= number_format((float)$p['superficie'], 2, ',', ' ') ?></td>
                        <td><span class="badge badge-<?= $p['statut'] === 'en_culture' ? 'green' : ($p['statut'] === 'repos' ? 'amber' : 'gray') ?>"><?= e(te('statut', $p['statut'])) ?></span></td>
                        <td><?= e($p['culture_actuelle'] ?: '—') ?></td>
                        <td class="right">
                            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/parcelles.php?action=edit&id=<?= (int)$p['id'] ?>"><?= e(t('edit')) ?></a>
                            <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
