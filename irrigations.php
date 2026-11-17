<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'irrigations';
$page_title = t('irrigations_title');
$u          = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        q('DELETE FROM irrigations WHERE id = ?', [(int)$_POST['id']]);
        flash('success', t('deleted_success'));
    } else {
        if (empty($_POST['parcelle_id'])) {
            flash('error', t('required_fields'));
        } else {
            q(
                'INSERT INTO irrigations (parcelle_id, date_irrigation, volume_litres, notes, user_id) VALUES (?,?,?,?,?)',
                [
                    (int)$_POST['parcelle_id'],
                    $_POST['date_irrigation'] ?? date('Y-m-d'),
                    ($_POST['volume_litres'] ?? '') !== '' ? (float)$_POST['volume_litres'] : null,
                    trim($_POST['notes'] ?? ''),
                    $u['id'],
                ]
            );
            flash('success', t('created_success'));
        }
    }
    redirect('irrigations.php');
}

$parcelles  = fetch_all('SELECT id, nom FROM parcelles ORDER BY nom');
$irrigations = fetch_all(
    'SELECT i.*, p.nom AS parcelle, u.nom AS auteur
     FROM irrigations i
     JOIN parcelles p ON p.id = i.parcelle_id
     LEFT JOIN users u ON u.id = i.user_id
     ORDER BY i.date_irrigation DESC, i.id DESC'
);

/* Dernière irrigation par parcelle + alerte */
$lastByParcelle = [];
foreach (fetch_all('SELECT parcelle_id, MAX(date_irrigation) AS derniere FROM irrigations GROUP BY parcelle_id') as $row) {
    $lastByParcelle[(int)$row['parcelle_id']] = $row['derniere'];
}
$dry = season_for_month((int)date('n')) === 'dry';

$showForm = ($_GET['action'] ?? '') === 'new';
$preselect = (int)($_GET['parcelle_id'] ?? 0);

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= e(t('new_irrigation')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
            <div class="field">
                <label><?= e(t('parcelle')) ?></label>
                <select name="parcelle_id" required>
                    <option value=""><?= e(t('select_parcelle')) ?></option>
                    <?php foreach ($parcelles as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $preselect === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('date')) ?></label>
                <input type="date" name="date_irrigation" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label><?= e(t('volume')) ?> (<?= e(t('litres')) ?>)</label>
                <input type="number" step="1" min="0" name="volume_litres">
            </div>
            <div class="field">
                <label><?= e(t('notes')) ?> <small class="muted">(<?= e(t('optional')) ?>)</small></label>
                <input type="text" name="notes">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/irrigations.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('last_irrigation')) ?><?= $dry ? ' · 💧 ' . e(t('season_dry')) : '' ?></h2>
    </div>
    <?php if (!$parcelles): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <ul class="list-plain">
            <?php foreach ($parcelles as $p): ?>
                <?php
                    $last  = $lastByParcelle[(int)$p['id']] ?? null;
                    $jours = $last ? (int)floor((time() - strtotime($last)) / 86400) : null;
                    // En saison sèche, une parcelle est "à irriguer" sans apport depuis 3 jours (ou jamais).
                    $need  = $dry && ($last === null || $jours >= 3);
                ?>
                <li>
                    <span><?= e($p['nom']) ?></span>
                    <span class="right">
                        <small class="muted"><?= $last ? e(fr_date($last)) : '—' ?></small>
                        <span class="badge badge-<?= $need ? 'red' : 'green' ?>"><?= e($need ? t('irrigation_needed') : t('irrigation_ok')) ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('irrigations_title')) ?></h2>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/irrigations.php?action=new">+ <?= e(t('new_irrigation')) ?></a>
    </div>
    <?php if (!$irrigations): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><?= e(t('date')) ?></th>
                        <th><?= e(t('parcelle')) ?></th>
                        <th class="right"><?= e(t('volume')) ?></th>
                        <th><?= e(t('notes')) ?></th>
                        <th><?= e(t('assigne_a')) ?></th>
                        <th class="right"><?= e(t('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($irrigations as $i): ?>
                    <tr>
                        <td><?= e(fr_date($i['date_irrigation'])) ?></td>
                        <td><strong><?= e($i['parcelle']) ?></strong></td>
                        <td class="right"><?= $i['volume_litres'] !== null ? number_format((float)$i['volume_litres'], 0, ',', ' ') . ' ' . e(t('litres')) : '—' ?></td>
                        <td><?= e($i['notes'] ?: '—') ?></td>
                        <td><?= e($i['auteur'] ?: '—') ?></td>
                        <td class="right">
                            <form method="post" class="inline-form" data-confirm="<?= e(t('confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
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
