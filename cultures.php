<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'cultures';
$page_title = t('cultures_title');
$u          = current_user();

/* --- Traitement des actions --- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        q('DELETE FROM cultures WHERE id = ?', [(int)$_POST['id']]);
        flash('success', t('deleted_success'));
    } elseif ($action === 'advance') {
        $c = fetch_one('SELECT * FROM cultures WHERE id = ?', [(int)$_POST['id']]);
        if ($c) {
            $next = next_stage($c['stade']);
            if ($next) {
                q('UPDATE cultures SET stade = ? WHERE id = ?', [$next, $c['id']]);
                if ($next === 'recolte') {
                    q('UPDATE parcelles SET statut = "libre" WHERE id = ?', [$c['parcelle_id']]);
                }
                flash('success', t('updated_success'));
            }
        }
    } else {
        $data = [
            (int)($_POST['parcelle_id'] ?? 0),
            trim($_POST['nom'] ?? ''),
            trim($_POST['variete'] ?? ''),
            $_POST['date_semis'] ?? date('Y-m-d'),
            $_POST['stade'] ?? 'semis',
            ($_POST['date_recolte_prevue'] ?? '') !== '' ? $_POST['date_recolte_prevue'] : null,
            ($_POST['quantite_semee'] ?? '') !== '' ? (float)$_POST['quantite_semee'] : null,
            $_POST['unite'] ?? 'kg',
            $u['id'],
        ];
        if ($data[0] === 0 || $data[1] === '') {
            flash('error', t('required_fields'));
        } elseif ($action === 'update') {
            $id = (int)$_POST['id'];
            $data[] = $id;
            q('UPDATE cultures SET parcelle_id=?, nom=?, variete=?, date_semis=?, stade=?, date_recolte_prevue=?, quantite_semee=?, unite=?, user_id=? WHERE id=?', $data);
            q('UPDATE parcelles SET statut = "en_culture" WHERE id = ?', [$data[0]]);
            flash('success', t('updated_success'));
        } else {
            q('INSERT INTO cultures (parcelle_id, nom, variete, date_semis, stade, date_recolte_prevue, quantite_semee, unite, user_id) VALUES (?,?,?,?,?,?,?,?,?)', $data);
            q('UPDATE parcelles SET statut = "en_culture" WHERE id = ?', [$data[0]]);
            flash('success', t('created_success'));
        }
    }
    redirect('cultures.php');
}

/* --- Formulaire ? --- */
$edit   = null;
$action = $_GET['action'] ?? '';
if ($action === 'edit' && isset($_GET['id'])) {
    $edit = fetch_one('SELECT * FROM cultures WHERE id = ?', [(int)$_GET['id']]);
} elseif ($action === 'new') {
    $edit = [];
}
$showForm = $edit !== null;

/* --- Données --- */
$parcelles = fetch_all('SELECT id, nom FROM parcelles ORDER BY nom');
$cultures  = fetch_all(
    'SELECT c.*, p.nom AS parcelle, u.nom AS auteur
     FROM cultures c
     JOIN parcelles p ON p.id = c.parcelle_id
     LEFT JOIN users u ON u.id = c.user_id
     ORDER BY (c.stade = "recolte"), c.date_semis DESC'
);

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= empty($edit['id']) ? e(t('new_culture')) : e(t('edit_culture')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= empty($edit['id']) ? 'create' : 'update' ?>">
        <?php if (!empty($edit['id'])): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label><?= e(t('parcelle')) ?></label>
                <select name="parcelle_id" required>
                    <option value=""><?= e(t('select_parcelle')) ?></option>
                    <?php foreach ($parcelles as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)($edit['parcelle_id'] ?? $_GET['parcelle_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('culture_name')) ?></label>
                <input type="text" name="nom" required value="<?= e($edit['nom'] ?? '') ?>" placeholder="Maïs, soja, manioc, tomate…">
            </div>
            <div class="field">
                <label><?= e(t('variete')) ?> <small class="muted">(<?= e(t('optional')) ?>)</small></label>
                <input type="text" name="variete" value="<?= e($edit['variete'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('date_semis')) ?></label>
                <input type="date" name="date_semis" required value="<?= e($edit['date_semis'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label><?= e(t('stade')) ?></label>
                <select name="stade">
                    <?php foreach (['semis', 'levee', 'croissance', 'floraison', 'maturation', 'recolte'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($edit['stade'] ?? 'semis') === $s ? 'selected' : '' ?>><?= e(te('stade', $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('date_recolte_prevue')) ?></label>
                <input type="date" name="date_recolte_prevue" value="<?= e($edit['date_recolte_prevue'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('quantite_semee')) ?></label>
                <input type="number" step="0.01" min="0" name="quantite_semee" value="<?= e($edit['quantite_semee'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('unite')) ?></label>
                <input type="text" name="unite" value="<?= e($edit['unite'] ?? 'kg') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/cultures.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="card-head">
        <h2><?= e(t('cultures_title')) ?></h2>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/cultures.php?action=new">+ <?= e(t('new_culture')) ?></a>
    </div>
    <?php if (!$cultures): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><?= e(t('culture_name')) ?></th>
                        <th><?= e(t('parcelle')) ?></th>
                        <th><?= e(t('date_semis')) ?></th>
                        <th><?= e(t('stade')) ?></th>
                        <th><?= e(t('date_recolte_prevue')) ?></th>
                        <th class="right"><?= e(t('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cultures as $c): ?>
                    <tr>
                        <td>
                            <strong><?= e($c['nom']) ?></strong>
                            <?php if ($c['variete']): ?><br><small class="muted"><?= e($c['variete']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($c['parcelle']) ?></td>
                        <td><?= e(fr_date($c['date_semis'])) ?><br><small class="muted"><?= (int)floor((time() - strtotime($c['date_semis'])) / 86400) ?> <?= e(t('days')) ?></small></td>
                        <td style="min-width:150px">
                            <span class="badge badge-<?= $c['stade'] === 'recolte' ? 'gray' : 'green' ?>"><?= e(te('stade', $c['stade'])) ?></span>
                            <div class="progress" style="margin-top:.35rem"><span style="width:<?= culture_progress($c['stade']) ?>%"></span></div>
                        </td>
                        <td><?= e(fr_date($c['date_recolte_prevue'])) ?></td>
                        <td class="right">
                            <?php if (next_stage($c['stade'])): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="advance">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn btn-sm btn-primary" type="submit" title="<?= e(t('advance_stage')) ?>">▶ <?= e(te('stade', next_stage($c['stade']))) ?></button>
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/recoltes.php?action=new&culture_id=<?= (int)$c['id'] ?>">🌾</a>
                            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/recommandations.php?parcelle_id=<?= (int)$c['parcelle_id'] ?>">🤖</a>
                            <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/cultures.php?action=edit&id=<?= (int)$c['id'] ?>"><?= e(t('edit')) ?></a>
                            <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
