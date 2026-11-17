<?php
/**
 * Configuration générale de l'application "La Ferme".
 * Adaptez les paramètres de connexion à votre environnement Ampps.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* --- Base de données (valeurs par défaut Ampps : root / mysql) --- */
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'la_ferme');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'mysql');

/* --- Application --- */
define('APP_NAME', 'La Ferme');
define('APP_DEBUG', true);

/* Langues disponibles */
define('LANGS', ['fr' => 'Français', 'en' => 'English']);

/* Devise affichée */
define('CURRENCY', 'FCFA');

/* URL de base détectée automatiquement (gère les sous-dossiers Ampps) */
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
define('BASE_URL', rtrim($scriptDir, '/'));

/* Affichage des erreurs selon le mode debug */
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

date_default_timezone_set('Africa/Lome');
