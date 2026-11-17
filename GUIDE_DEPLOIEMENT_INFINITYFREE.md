# Déployer « La Ferme » sur InfinityFree

Configuration **déjà finalisée** pour votre hébergement :

| Paramètre | Valeur |
|---|---|
| **MySQL Host** | `sql113.infinityfree.com` |
| **Database Name** | `if0_42464685_la_ferme` |
| **Username** | `if0_42464685` |
| **Password** | *(renseigné dans `config/config.php`)* |

La base **existe déjà** et contient vos données (2 comptes, 4 parcelles, cultures, stocks,
tâches, 8 recommandations). **Rien à recréer ni à réimporter.**

> `config/config.php` pointe déjà vers InfinityFree (production).
> Pour revenir à un usage **local** (Ampps), remplacez-le par `config/config.local.php`.

---

## Ce qu'il reste à faire : uploader le site

1. Connectez-vous à **app.infinityfree.com** → **Control Panel (vPanel)** de votre site.
2. Ouvrez **File Manager / Online File Manager**.
3. Allez dans le dossier **`htdocs`**.
4. Supprimez la page par défaut (`index2.html` ou `default.php`) si elle existe.
5. Uploadez **`La_Ferme_deploy.zip`** dans `htdocs`, puis clic droit → **Extract**.
6. Vérifiez que **`index.php` est directement dans `htdocs`** (pas dans un sous-dossier).
7. Ouvrez `http://votre-sous-domaine.infinityfreeapp.com`.

**Comptes :**
- Propriétaire : `proprietaire@laferme.tg`
- Collaborateur : `collaborateur@laferme.tg`

*(Mots de passe définis lors de votre installation. Utilisez « Utilisateurs » pour les changer.)*

---

## Recommandé après la mise en ligne

- `install.php` **n'est pas inclus** dans le paquet (la base est déjà en place).
- Changer les mots de passe des comptes de démonstration.
- Si vous voulez corriger l'état des parcelles, dans **phpMyAdmin** :
  ```sql
  UPDATE parcelles SET statut = 'libre' WHERE id IN (2,3);
  ```

---

## Contenu des fichiers

| Fichier | Rôle |
|---|---|
| `La_Ferme_deploy.zip` | Application complète, config InfinityFree incluse, prête à extraire dans `htdocs` |
| `config/config.php` | Config **production** (InfinityFree) |
| `config/config.local.php` | Config **locale** Ampps (root / mysql) |
| `sql/la_ferme.sql` | Votre export de base (sauvegarde) |

---

## Dépannage

| Symptôme | Cause / solution |
|---|---|
| « Connexion à la base impossible » | Vérifier Host/DB/User/Pass dans `config/config.php` (via File Manager). |
| Page blanche | Mettre temporairement `APP_DEBUG = true` dans `config/config.php`, recharger, lire l'erreur. |
| Erreur 404 sur les liens | Les fichiers ne sont pas à la racine de `htdocs`. |
| « Jeton de sécurité invalide » | Recharger la page (session expirée). |
| Page d'attente InfinityFree à la 1re visite | Normal sur l'offre gratuite, patientez quelques secondes. |
