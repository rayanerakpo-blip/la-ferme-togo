<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$active     = 'recoltes';
$page_title = t('recoltes_title');
$u          = current_user();

/* --- Traitement des actions --- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $rec = fetch_one(
            'SELECT r.*, c.nom AS culture FROM recoltes r JOIN cultures c ON c.id = r.culture_id WHERE r.id = ?',
            [(int)$_POST['id']]
        );
        if ($rec) {
            // Annule l'entrée en stock créée lors de l'enregistrement de la récolte.
            q(
                'UPDATE stocks SET quantite = GREATEST(0, quantite - ?), updated_at = NOW()
                 WHERE produit = ? AND categorie = "recolte"',
                [(float)$rec['quantite'], $rec['culture']]
            );
            q('DELETE FROM recoltes WHERE id = ?', [(int)$rec['id']]);
            flash('success', t('deleted_success'));
        }
    } else {
        $culture = fetch_one('SELECT * FROM cultures WHERE id = ?', [(int)($_POST['culture_id'] ?? 0)]);
        $quantite = (float)($_POST['quantite'] ?? 0);
        if (!$culture || $quantite <= 0) {
            flash('error', t('required_fields'));
        } else {
            $unite = $_POST['unite'] ?? 'kg';
            q(
                'INSERT INTO recoltes (culture_id, parcelle_id, date_recolte, quantite, unite, qualite, user_id) VALUES (?,?,?,?,?,?,?)',
                [
                    $culture['id'],
                    $culture['parcelle_id'],
                    $_POST['date_recolte'] ?? date('Y-m-d'),
                    $quantite,
                    $unite,
                    $_POST['qualite'] ?? 'bonne',
                    $u['id'],
                ]
            );
            // Fin du cycle : la culture passe au stade "récolte", la parcelle se libère.
            q('UPDATE cultures SET stade = "recolte" WHERE id = ?', [$culture['id']]);
            q('UPDATE parcelles SET statut = "libre" WHERE id = ?', [$culture['parcelle_id']]);

            // Intégration : la récolte entre en stock (catégorie "récolte").
            $existing = fetch_one('SELECT * FROM stocks WHERE produit = ? AND categorie = "recolte"', [$culture['nom']]);
            if ($existing) {
                q('UPDATE stocks SET quantite = quantite + ?, updated_at = NOW() WHERE id = ?', [$quantite, $existing['id']]);
            } else {
                q('INSERT INTO stocks (produit, categorie, quantite, unite, emplacement, seuil_alerte) VALUES (?,?,?,?,?,0)',
                  [$culture['nom'], 'recolte', $quantite, $unite, t('warehouse')]);
            }
            flash('success', t('created_success'));
        }
    }
    redirect('recoltes.php');
}

/* --- Données --- */
$cultures = fetch_all(
    'SELECT c.id, c.nom, c.unite, p.nom AS parcelle FROM cultures c JOIN parcelles p ON p.id = c.parcelle_id ORDER BY (c.stade = "recolte"), c.nom'
);
$recoltes = fetch_all(
    'SELECT r.*, c.nom AS culture, p.nom AS parcelle, u.nom AS auteur
     FROM recoltes r
     JOIN cultures c ON c.id = r.culture_id
     JOIN parcelles p ON p.id = r.parcelle_id
     LEFT JOIN users u ON u.id = r.user_id
     ORDER BY r.date_recolte DESC, r.id DESC'
);
$total = (float)fetch_value('SELECT COALESCE(SUM(quantite),0) FROM recoltes');
$showForm = ($_GET['action'] ?? '') === 'new';
$preselect = (int)($_GET['culture_id'] ?? 0);

require __DIR__ . '/includes/header.php';
?>

<?php if ($showForm): ?>
<div class="card">
    <h2><?= e(t('new_recolte')) ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
            <div class="field">
                <label><?= e(t('culture_name')) ?> / <?= e(t('parcelle')) ?></label>
                <select name="culture_id" required>
                    <option value="">—</option>
                    <?php foreach ($cultures as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $preselect === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['nom'] . ' — ' . $c['parcelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(t('date_recolte')) ?></label>
                <input type="date" name="date_recolte" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label><?= e(t('quantite')) ?></label>
                <input type="number" step="0.01" min="0" name="quantite" required>
            </div>
            <div class="field">
                <label><?= e(t('unite')) ?></label>
                <input type="text" name="unite" value="kg">
            </div>
            <div class="field">
                <label><?= e(t('qualite')) ?></label>
                <select name="qualite">
                    <?php foreach (['bonne', 'moyenne', 'faible'] as $q): ?>
                        <option value="<?= $q ?>"><?= e(te('qualite', $q)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/recoltes.php"><?= e(t('cancel')) ?></a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="kpi-grid">
    <div class="kpi">
        <div class="label"><?= e(t('total_recolte')) ?></div>
        <div class="value"><?= number_format($total, 0, ',', ' ') ?></div>
        <div class="sub">kg (<?= count($recoltes) ?> <?= e(lc(t('recoltes_title'))) ?>)</div>
    </div>
</div>
<div class="card">
    <div class="card-head">
        <h2><?= e(t('recoltes_title')) ?></h2>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/recoltes.php?action=new">+ <?= e(t('new_recolte')) ?></a>
    </div>
    <?php if (!$recoltes): ?>
        <p class="empty"><?= e(t('no_records')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th><?= e(t('date_recolte')) ?></th>
                        <th><?= e(t('culture_name')) ?></th>
                        <th><?= e(t('parcelle')) ?></th>
                        <th class="right"><?= e(t('quantite')) ?></th>
                        <th><?= e(t('qualite')) ?></th>
                        <th><?= e(t('assigne_a')) ?></th>
                        <th class="right"><?= e(t('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recoltes as $r): ?>
                    <tr>
                        <td><?= e(fr_date($r['date_recolte'])) ?></td>
                        <td><strong><?= e($r['culture']) ?></strong></td>
                        <td><?= e($r['parcelle']) ?></td>
                        <td class="right"><?= number_format((float)$r['quantite'], 2, ',', ' ') ?> <?= e($r['unite']) ?></td>
                        <td><span class="badge badge-<?= $r['qualite'] === 'bonne' ? 'green' : ($r['qualite'] === 'moyenne' ? 'amber' : 'red') ?>"><?= e(te('qualite', $r['qualite'])) ?></span></td>
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
