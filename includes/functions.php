<?php
/**
 * Fonctions utilitaires : i18n, sécurité, sessions, logique métier.
 */
require_once __DIR__ . '/db.php';

/* ------------------------------------------------------------------ */
/* Internationalisation                                                */
/* ------------------------------------------------------------------ */

/** Langue courante (fr par défaut). */
function current_lang(): string
{
    if (isset($_GET['lang']) && array_key_exists($_GET['lang'], LANGS)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    return $_SESSION['lang'] ?? 'fr';
}

/** Dictionnaire chargé une seule fois. */
function lang_dict(): array
{
    static $dict = null;
    if ($dict === null) {
        $dict = require __DIR__ . '/../lang/' . current_lang() . '.php';
    }
    return $dict;
}

/** Traduit une clé (retour lisible si absente). */
function t(string $key): string
{
    $dict = lang_dict();
    if (isset($dict[$key])) {
        return $dict[$key];
    }
    // Repli : "nav_dashboard" -> "Nav dashboard"
    return ucfirst(str_replace('_', ' ', $key));
}

/** Traduit une valeur d'énumération (ex: statut_en_culture). */
function te(string $prefix, $value): string
{
    return t($prefix . '_' . $value);
}

/* ------------------------------------------------------------------ */
/* Affichage / sécurité                                                */
/* ------------------------------------------------------------------ */

/** Échappe une valeur pour le HTML. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Redirige vers une page de l'application. */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

/** Ajoute un message flash. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Récupère (et vide) les messages flash. */
function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Jeton CSRF de la session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Champ caché CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Vérifie le jeton CSRF d'un POST. */
function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        die('Jeton de sécurité invalide. Merci de recharger la page.');
    }
}

/* ------------------------------------------------------------------ */
/* Authentification                                                    */
/* ------------------------------------------------------------------ */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $user = fetch_one('SELECT * FROM users WHERE id = ? AND actif = 1', [$_SESSION['user_id']]);
    }
    return $user ?: null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_proprietaire(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'proprietaire';
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_proprietaire(): void
{
    require_login();
    if (!is_proprietaire()) {
        http_response_code(403);
        flash('error', t('access_denied'));
        redirect('index.php');
    }
}

/* ------------------------------------------------------------------ */
/* Divers                                                             */
/* ------------------------------------------------------------------ */

/** Formate un montant. */
function money($amount): string
{
    return number_format((float)$amount, 0, ',', ' ') . ' ' . CURRENCY;
}

/** Met une chaîne en minuscules (compatible UTF-8 si mbstring présent). */
function lc(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/** Formate une date (Y-m-d -> d/m/Y). */
function fr_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '—';
}

/** Saison agronomique (Togo) approximative pour un mois donné. */
function season_for_month(int $month): string
{
    // Saison des pluies : avril à octobre. Saison sèche : novembre à mars.
    return ($month >= 4 && $month <= 10) ? 'rainy' : 'dry';
}

/** Libellé de la saison courante. */
function season_label(): string
{
    return t(season_for_month((int)date('n')) === 'rainy' ? 'season_rainy' : 'season_dry');
}

/** Prochain stade d'une culture. */
function next_stage(string $stage): ?string
{
    $order = ['semis', 'levee', 'croissance', 'floraison', 'maturation', 'recolte'];
    $i = array_search($stage, $order, true);
    if ($i === false || $i >= count($order) - 1) {
        return null;
    }
    return $order[$i + 1];
}

/**
 * Génère des recommandations IA (règles agronomiques génériques) pour une parcelle.
 * Croise culture, stade, superficie, saison et dernier apport hydrique.
 *
 * @return int nombre de recommandations créées
 */
function generer_recommandations_parcelle(int $parcelle_id): int
{
    $parcelle = fetch_one('SELECT * FROM parcelles WHERE id = ?', [$parcelle_id]);
    if (!$parcelle) {
        return 0;
    }

    $culture = fetch_one(
        'SELECT * FROM cultures WHERE parcelle_id = ? AND stade <> "recolte" ORDER BY date_semis DESC LIMIT 1',
        [$parcelle_id]
    );

    $now      = date('Y-m-d H:i:s');
    $created  = 0;
    $season   = season_for_month((int)date('n'));
    $libelle  = $parcelle['nom'] . ' (' . ($culture['nom'] ?? 'sans culture') . ')';

    // Empêche les doublons : une recommandation du même type dans les 7 derniers jours.
    $recent = fetch_value(
        'SELECT COUNT(*) FROM recommandations WHERE parcelle_id = ? AND type = ? AND created_at >= (NOW() - INTERVAL 7 DAY)',
        [$parcelle_id, 'plantation']
    );

    /* --- Recommandation de plantation --- */
    if ((int)$recent === 0) {
        if (!$culture) {
            $contenu = sprintf(
                "La parcelle « %s » (%.2f ha) est libre. %s : c'est une bonne période pour préparer le sol et lancer un nouveau semis (maïs, soja ou manioc selon votre rotation).",
                $parcelle['nom'],
                (float)$parcelle['superficie'],
                $season === 'rainy' ? t('season_rainy') : t('season_dry')
            );
        } else {
            $jours = (int)floor((time() - strtotime($culture['date_semis'])) / 86400);
            $contenu = sprintf(
                "Culture « %s » sur %s depuis %d %s (stade : %s). %s.",
                $culture['nom'],
                $parcelle['nom'],
                $jours,
                t('days'),
                te('stade', $culture['stade']),
                $season === 'rainy'
                    ? "Saison des pluies : privilégiez les semis en début de pluie et prévoyez un sarclage régulier"
                    : "Saison sèche : privilégiez des cultures résistantes et un paillage pour limiter l'évaporation"
            );
        }
        q(
            'INSERT INTO recommandations (parcelle_id, type, contenu, priorite, statut, created_at) VALUES (?,?,?,?,?,?)',
            [$parcelle_id, 'plantation', $contenu, 'moyenne', 'nouvelle', $now]
        );
        $created++;
    }

    /* --- Recommandation d'irrigation --- */
    $recentIrr = fetch_value(
        'SELECT COUNT(*) FROM recommandations WHERE parcelle_id = ? AND type = ? AND created_at >= (NOW() - INTERVAL 7 DAY)',
        [$parcelle_id, 'irrigation']
    );
    if ((int)$recentIrr === 0) {
        $superficie = max((float)$parcelle['superficie'], 0.1);
        $derniere   = fetch_one(
            'SELECT * FROM irrigations WHERE parcelle_id = ? ORDER BY date_irrigation DESC LIMIT 1',
            [$parcelle_id]
        );
        $joursDepuis = $derniere
            ? (int)floor((time() - strtotime($derniere['date_irrigation'])) / 86400)
            : null;

        if ($season === 'dry') {
            $litres    = (int)round($superficie * 5000); // ~5 m³/ha/jour en saison sèche
            $priorite  = ($joursDepuis === null || $joursDepuis >= 3) ? 'haute' : 'moyenne';
            $contenu = sprintf(
                "Saison sèche : apport conseillé d'environ %s %s d'eau pour %s (%.2f ha)%s.",
                number_format($litres, 0, ',', ' '),
                t('litres'),
                $parcelle['nom'],
                $superficie,
                $joursDepuis === null
                    ? ". Aucune irrigation enregistrée : démarrez un arrosage."
                    : sprintf('. Dernier apport il y a %d %s.', $joursDepuis, t('days'))
            );
        } else {
            $priorite = 'basse';
            $contenu = sprintf(
                "Saison des pluies : l'apport hydrique naturel est généralement suffisant pour %s. Surveillez les épisodes secs de plus de 5 jours%s.",
                $parcelle['nom'],
                $culture ? " et le stade " . te('stade', $culture['stade']) : ''
            );
        }
        q(
            'INSERT INTO recommandations (parcelle_id, type, contenu, priorite, statut, created_at) VALUES (?,?,?,?,?,?)',
            [$parcelle_id, 'irrigation', $contenu, $priorite, 'nouvelle', $now]
        );
        $created++;
    }

    return $created;
}

/** Progression (%) d'un cycle de culture selon son stade. */
function culture_progress(string $stage): int
{
    $map = [
        'semis'      => 10,
        'levee'      => 25,
        'croissance' => 50,
        'floraison'  => 65,
        'maturation' => 85,
        'recolte'    => 100,
    ];
    return $map[$stage] ?? 0;
}
