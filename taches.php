<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'taches';
$page_title = t('taches_title');
$u          = current_user();
$owner      = is_proprietaire();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Mise à jour rapide du statut : autorisée pour le propriétaire ou l'assigné.
    if ($action === 'status') {
        $task = fetch_one('SELECT * FROM taches WHERE id = ?', [(int)$_POST['id']]);
        $can = $task && ($owner || $task['user_id'] === null || (int)$task['user_id'] === (int)$u['id']);
        if ($can) {
            q('UPDATE taches SET statut = ? WHERE id = ?', [$_POST['statut'] ?? 'a_faire', $task['id']]);
            flash('success', t('updated_success'));
        } else {
            flash('error', t('access_denied'));
        }
        redirect('taches.php');
    }

    // Création / modification / suppression : propriétaire uniquement.
    if (!$owner) {
        flash('error', t('access_denied'));
        redirect('taches.php');
    }

    if ($action === 'delete') {
        q('DELETE FROM taches WHERE id = ?', [(int)$_POST['id']]);
        flash('success', t('deleted_success'));
    } else {
        $data = [
            trim($_POST['titre'] ?? ''),
            trim($_POST['description'] ?? ''),
            ($_POST['user_id'] ?? '') !== '' ? (int)$_POST['user_id'] : null,
            ($_POST['parcelle_id'] ?? '') !== '' ? (int)$_POST['parcelle_id'] : null,
            ($_POST['date_echeance'] ?? '') !== '' ? $_POST['date_echeance'] : null,
            $_POST['priorite'] ?? 'moyenne',
            $_POST['statut'] ?? 'a_faire',
        ];
        if ($data[0] === '') {
            flash('error', t('required_fields'));
        } elseif ($action === 'update') {
            $data[] = (int)$_POST['id'];
            q('UPDATE taches SET titre=?, description=?, user_id=?, parcelle_id=?, date_echeance=?, priorite=?, statut=? WHERE id=?', $data);
            flash('success', t('updated_success'));
        } else {
            q('INSERT INTO taches (titre, description, user_id, parcelle_id, date_echeance, priorite, statut) VALUES (?,?,?,?,?,?,?)', $data);
            flash('success', t('created_success'));
        }
    }
    redirect('taches.php');
}

/* --- Formulaire --- */
$edit   = null;
$action = $_GET['action'] ?? '';
if ($action === 'edit' && isset($_GET['id'])) {
    $edit = fetch_one('SELECT * FROM taches WHERE id = ?', [(int)$_GET['id']]);
} elseif ($action === 'new' && $owner) {
    $edit = [];
}
$showForm = $edit !== null;

/* --- Données --- */
$users     = fetch_all('SELECT id, nom, role FROM users WHERE actif = 1 ORDER BY nom');
$parcelles = fetch_all('SELECT id, nom FROM parcelles ORDER BY nom');

$filtre = $_GET['statut'] ?? 'ouvert';
$where  = [];
$params = [];
if (!$owner) {
    $where[] = '(t.user_id = ? OR t.user_id IS NULL)';
    $params[] = $u['id'];
}
if ($filtre === 'ouvert') {
    $where[] = 't.statut <> "termine"';
} elseif (in_array($filtre, ['a_faire', 'en_cours', 'termine'], true)) {
    $where[] = 't.statut = ?';
    $params[] = $filtre;
}
$sql = 'SELECT t.*, p.nom AS parcelle, us.nom AS assigne FROM taches t
        LEFT JOIN parcelles p ON p.id = t.parcelle_id
        LEFT JOIN users us ON us.id = t.user_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY (t.statut = "termine"), (t.date_echeance IS NULL), t.date_echeance, FIELD(t.priorite,"haute","moyenne","basse")';
$taches = fetch_all($sql, $params);

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= empty($edit['id']) ? e(t('new_tache')) : e(t('edit_tache')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= empty($edit['id']) ? 'create' : 'update' ?>">
        <?php if (!empty($edit['id'])): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field" style="grid-column:1/-1">
                <label><?= e(t('tache_titre')) ?></label>
                <input type="text" name="titre" required value="<?= e($edit['titre'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('assigne_a')) ?></label>
                <select name="user_id">
                    <option value=""><?= e(t('unassigned')) ?></option>
                    <?php foreach ($users as $us): ?>
                        <option value="<?= (int)$us['id'] ?>" <?= (int)($edit['user_id'] ?? 0) === (int)$us['id'] ? 'selected' : '' ?>><?= e($us['nom']) ?> (<?= e(te('role', $us['role'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('parcelle')) ?></label>
                <select name="parcelle_id">
                    <option value="">—</option>
                    <?php foreach ($parcelles as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)($edit['parcelle_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('echeance')) ?></label>
                <input type="date" name="date_echeance" value="<?= e($edit['date_echeance'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('priorite')) ?></label>
                <select name="priorite">
                    <?php foreach (['basse', 'moyenne', 'haute'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($edit['priorite'] ?? 'moyenne') === $p ? 'selected' : '' ?>><?= e(te('priorite', $p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('statut')) ?></label>
                <select name="statut">
                    <?php foreach (['a_faire', 'en_cours', 'termine'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($edit['statut'] ?? 'a_faire') === $s ? 'selected' : '' ?>><?= e(te('statut', $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="grid-column:1/-1">
                <label><?= e(t('description')) ?></label>
                <textarea name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/taches.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="card-head">
        <h2><?= e(t('taches_title')) ?></h2>
        <div class="row-between" style="gap:.5rem">
            <?php foreach (['ouvert' => t('statut_a_faire'), 'en_cours' => t('statut_en_cours'), 'termine' => t('statut_termine'), 'tous' => t('all')] as $k => $lab): ?>
                <a class="btn btn-sm <?= $filtre === $k ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(BASE_URL) ?>/taches.php?statut=<?= $k ?>"><?= e($lab) ?></a>
            <?php endforeach; ?>
            <?php if ($owner): ?>
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/taches.php?action=new">+ <?= e(t('new_tache')) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$taches): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><?= e(t('tache_titre')) ?></th>
                        <th><?= e(t('assigne_a')) ?></th>
                        <th><?= e(t('parcelle')) ?></th>
                        <th><?= e(t('echeance')) ?></th>
                        <th><?= e(t('priorite')) ?></th>
                        <th><?= e(t('statut')) ?></th>
                        <th class="right"><?= e(t('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($taches as $tk): ?>
                    <?php $late = $tk['date_echeance'] && $tk['date_echeance'] < date('Y-m-d') && $tk['statut'] !== 'termine'; ?>
                    <tr>
                        <td>
                            <strong><?= e($tk['titre']) ?></strong>
                            <?php if ($tk['description']): ?><br><small class="muted"><?= e($tk['description']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($tk['assigne'] ?: t('unassigned')) ?></td>
                        <td><?= e($tk['parcelle'] ?: '—') ?></td>
                        <td><?= e(fr_date($tk['date_echeance'])) ?><?php if ($late): ?> <span class="badge badge-red"><?= e(t('overdue')) ?></span><?php endif; ?></td>
                        <td><span class="badge badge-<?= $tk['priorite'] === 'haute' ? 'red' : ($tk['priorite'] === 'moyenne' ? 'amber' : 'gray') ?>"><?= e(te('priorite', $tk['priorite'])) ?></span></td>
                        <td>
                            <?php
                                $canUpdate = $owner || $tk['user_id'] === null || (int)$tk['user_id'] === (int)$u['id'];
                            ?>
                            <?php if ($canUpdate): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int)$tk['id'] ?>">
                                    <select name="statut" onchange="this.form.submit()">
                                        <?php foreach (['a_faire', 'en_cours', 'termine'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $tk['statut'] === $s ? 'selected' : '' ?>><?= e(te('statut', $s)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-blue"><?= e(te('statut', $tk['statut'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="right">
                            <?php if ($owner): ?>
                                <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/taches.php?action=edit&id=<?= (int)$tk['id'] ?>"><?= e(t('edit')) ?></a>
                                <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$tk['id'] ?>">
                                    <button class="btn btn-sm btn-danger" type="submit"><?= e(t('delete')) ?></button>
                                </form>
                            <?php endif; ?>
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
