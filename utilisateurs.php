<?php
require_once __DIR__ . '/includes/functions.php';
require_proprietaire();

$active     = 'utilisateurs';
$page_title = t('utilisateurs_title');
$me         = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        if ((int)$_POST['id'] === (int)$me['id']) {
            flash('error', t('access_denied'));
        } else {
            q('DELETE FROM users WHERE id = ?', [(int)$_POST['id']]);
            flash('success', t('deleted_success'));
        }
    } else {
        $nom   = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'collaborateur';
        $langue = $_POST['langue'] ?? 'fr';
        $actif = isset($_POST['actif']) ? 1 : 0;
        $pass  = $_POST['mot_de_passe'] ?? '';

        // Unicité de l'e-mail
        $dup = fetch_one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, (int)($_POST['id'] ?? 0)]);
        if ($nom === '' || $email === '' || $dup) {
            flash('error', $dup ? 'Cet e-mail est déjà utilisé.' : t('required_fields'));
        } elseif ($action === 'update') {
            $id = (int)$_POST['id'];
            // Garde-fou : ne pas se retirer soi-même les droits de propriétaire / se désactiver.
            if ($id === (int)$me['id'] && ($role !== 'proprietaire' || !$actif)) {
                flash('error', t('cant_lock_self'));
            } else {
                q('UPDATE users SET nom=?, email=?, role=?, langue=?, actif=? WHERE id=?', [$nom, $email, $role, $langue, $actif, $id]);
                if ($pass !== '') {
                    q('UPDATE users SET mot_de_passe=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
                }
                flash('success', t('updated_success'));
            }
        } else {
            if ($pass === '') {
                flash('error', t('required_fields'));
            } else {
                q('INSERT INTO users (nom, email, mot_de_passe, role, langue, actif) VALUES (?,?,?,?,?,?)',
                  [$nom, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $langue, $actif]);
                flash('success', t('created_success'));
            }
        }
    }
    redirect('utilisateurs.php');
}

$edit   = null;
$action = $_GET['action'] ?? '';
if ($action === 'edit' && isset($_GET['id'])) {
    $edit = fetch_one('SELECT * FROM users WHERE id = ?', [(int)$_GET['id']]);
} elseif ($action === 'new') {
    $edit = [];
}
$showForm = $edit !== null;

$users = fetch_all('SELECT * FROM users ORDER BY role, nom');

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= empty($edit['id']) ? e(t('new_user')) : e(t('edit_user')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= empty($edit['id']) ? 'create' : 'update' ?>">
        <?php if (!empty($edit['id'])): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label><?= e(t('name')) ?></label>
                <input type="text" name="nom" required value="<?= e($edit['nom'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('email')) ?></label>
                <input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label><?= e(t('new_password')) ?></label>
                <input type="password" name="mot_de_passe" <?= empty($edit['id']) ? 'required' : '' ?>>
                <?php if (!empty($edit['id'])): ?><span class="hint"><?= e(t('leave_blank_keep')) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label><?= e(t('role')) ?></label>
                <select name="role">
                    <?php foreach (['proprietaire', 'collaborateur'] as $r): ?>
                        <option value="<?= $r ?>" <?= ($edit['role'] ?? 'collaborateur') === $r ? 'selected' : '' ?>><?= e(te('role', $r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('language')) ?></label>
                <select name="langue">
                    <?php foreach (LANGS as $code => $lab): ?>
                        <option value="<?= $code ?>" <?= ($edit['langue'] ?? 'fr') === $code ? 'selected' : '' ?>><?= e($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <label style="font-weight:400"><input type="checkbox" name="actif" value="1" <?= ($edit['actif'] ?? 1) ? 'checked' : '' ?>> <?= e(t('active')) ?></label>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/utilisateurs.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="card-head">
        <h2><?= e(t('utilisateurs_title')) ?></h2>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/utilisateurs.php?action=new">+ <?= e(t('new_user')) ?></a>
    </div>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th><?= e(t('name')) ?></th>
                    <th><?= e(t('email')) ?></th>
                    <th><?= e(t('role')) ?></th>
                    <th><?= e(t('language')) ?></th>
                    <th><?= e(t('statut')) ?></th>
                    <th class="right"><?= e(t('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $us): ?>
                <tr>
                    <td><strong><?= e($us['nom']) ?></strong></td>
                    <td><?= e($us['email']) ?></td>
                    <td><span class="badge badge-<?= $us['role'] === 'proprietaire' ? 'green' : 'blue' ?>"><?= e(te('role', $us['role'])) ?></span></td>
                    <td><?= e(strtoupper($us['langue'])) ?></td>
                    <td><span class="badge badge-<?= $us['actif'] ? 'green' : 'gray' ?>"><?= e($us['actif'] ? t('active') : t('inactive')) ?></span></td>
                    <td class="right">
                        <a class="btn btn-sm btn-outline" href="<?= e(BASE_URL) ?>/utilisateurs.php?action=edit&id=<?= (int)$us['id'] ?>"><?= e(t('edit')) ?></a>
                        <?php if ((int)$us['id'] !== (int)$me['id']): ?>
                            <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$us['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit"><?= e(t('delete')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
