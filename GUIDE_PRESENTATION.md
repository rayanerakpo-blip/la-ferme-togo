# La Ferme — Guide de présentation du projet

> Application web de gestion de ferme intégrée (Togo) — PHP 8 / MySQL (PDO) / HTML-CSS-JS, sans framework.

---

## 1. En une phrase

**« La Ferme » centralise le pilotage d'une exploitation agricole multi-filières** :
parcelles et cultures, récoltes, irrigations, tâches du personnel, stocks, transformation
du manioc, finances — avec un moteur de recommandations agronomiques « IA » et un
contrôle d'accès par rôle (propriétaire / collaborateur), le tout bilingue français/anglais.

---

## 2. Contexte et objectifs (issus du cahier des charges)

- **Domaine** : ferme intégrée de 17 ha, V1 centrée sur les 2 ha exploitables.
- **Filières** : soja (vente brute), manioc (gari / tapioca / attiéké), maraîchage, élevage.
- **Objectifs** : sortir du papier, relier les filières entre elles (récolte → stock → transformation → vente),
  automatiser les conseils de plantation et d'irrigation, assurer la traçabilité.
- **Utilisateurs** : le **propriétaire** (supervision, finances, comptes) et les **collaborateurs** (saisie terrain).

### Matrice des droits réellement implémentée

| Fonctionnalité | Propriétaire | Collaborateur |
|---|---|---|
| Tableau de bord | Vue globale + finances | Vue limitée (ses tâches) |
| Parcelles / Cultures / Récoltes / Irrigations / Stocks / Transformations | Oui | Oui |
| Finances | Oui | Non (redirigé + message) |
| Utilisateurs | Oui | Non |
| Recommandations IA | Générer + valider | Consulter |
| Tâches | Créer/modifier/assigner | Mettre à jour ses tâches |

---

## 3. Stack technique

- **Langage** : PHP 8.2 (compatible 8.x), procédural + fonctions.
- **Base de données** : MySQL / MariaDB via **PDO** (requêtes préparées, `utf8mb4`).
- **Front** : HTML rendu côté serveur, CSS maison responsive, un peu de JS natif.
- **Aucune dépendance externe** (ni Composer, ni npm) → déploiement par simple copie.
- **Architecture** : classique « client-serveur », pages PHP = contrôleur + vue.

### Arborescence

```
La_Ferme/
├─ config/config.php        Constantes (BDD, langue, devise, BASE_URL, timezone)
├─ includes/
│  ├─ db.php                Connexion PDO unique + helpers q()/fetch_one()/fetch_all()/fetch_value()
│  ├─ functions.php         i18n, sécurité, session, logique métier (recommandations, stades…)
│  ├─ header.php            Layout : sidebar de navigation + barre supérieure
│  └─ footer.php            Fermeture du layout + script JS
├─ lang/fr.php, lang/en.php Dictionnaires de traduction (197 clés chacun)
├─ sql/schema.sql           Création des tables + jeu de données de démonstration
├─ assets/css/style.css     Thème (vert/terre), responsive, styles d'impression
├─ assets/js/app.js         Menu mobile + confirmation de suppression
├─ install.php              Installateur (base, tables, comptes par défaut) — verrouillé après installation
├─ login.php / logout.php   Authentification
└─ index.php                Tableau de bord
   + 1 fichier par module métier (parcelles, cultures, recoltes, irrigations,
     taches, stocks, transformations, finances, recommandations, utilisateurs)
```

---

## 4. Le modèle de données

10 tables, toutes en `InnoDB` / `utf8mb4`, reliées par clés étrangères :

| Table | Rôle | Relations |
|---|---|---|
| `users` | Comptes et rôles | — |
| `parcelles` | Terrains (nom, zone, superficie, statut) | — |
| `cultures` | Cycle de vie d'un semis (stade, dates, quantité) | → `parcelles`, `users` |
| `recoltes` | Production d'une culture | → `cultures`, `parcelles`, `users` |
| `irrigations` | Apports hydriques (date, volume, notes) | → `parcelles`, `users` |
| `taches` | Travail à faire (assigné, échéance, priorité, statut) | → `users`, `parcelles` |
| `stocks` | Intrants / récoltes / produits transformés + seuil d'alerte | — |
| `transformations` | Manioc → gari/tapioca/attiéké (rendement) | → `users` |
| `transactions_financieres` | Achats / ventes par filière | → `users` |
| `recommandations` | Conseils IA (plantation / irrigation) | → `parcelles` |

**Cycle de vie d'une culture** : `semis → levée → croissance → floraison → maturation → récolte`.

---

## 5. Les fonctionnalités, module par module

1. **Tableau de bord** : KPI (parcelles, surface exploitée, cultures en cours, tâches ouvertes,
   alertes de stock) ; recettes/dépenses/solde (propriétaire) ; listes « Mes tâches »,
   « Dernières recommandations », « Stocks faibles » ; rentabilité par filière ; actions rapides.

2. **Parcelles** : CRUD ; statut (libre / en culture / repos) et culture en cours affichée ;
   suppression **en cascade**.

3. **Cultures** : CRUD + **avancement de stade en un clic** avec barre de progression ;
   le passage à « récolté » libère automatiquement la parcelle.

4. **Récoltes** : saisie reliée à une culture → alimente **automatiquement le stock** (catégorie « récolte »)
   et clôt le cycle. Suppression → le stock est **décrémenté** en conséquence.

5. **Irrigations** : historique + **tableau de synthèse « Dernière irrigation » par parcelle** avec
   alerte « à irriguer » en saison sèche (dernier apport ≥ 3 jours ou jamais).

6. **Tâches** : CRUD, assignation, échéance (badge « en retard »), priorité, filtres
   (ouvertes / en cours / terminées / toutes). Le collaborateur ne peut mettre à jour que ses tâches.

7. **Stocks** : CRUD, filtrage par catégorie, **ajustement rapide de quantité (±)**,
   alerte visuelle sous le seuil.

8. **Transformations** : enregistre manioc transformé et produit fini, calcule le **rendement (%)** ;
   met à jour les stocks (**+ produit fini, − manioc brut**). Suppression → opérations inversées.

9. **Finances** (propriétaire) : recettes/dépenses/solde par **période** (mois / année / tout),
   **rentabilité par filière**, CRUD des transactions.

10. **Recommandations IA** : moteur à **règles agronomiques** qui croise culture en cours, stade,
    superficie, saison (pluies/sec) et dernier apport hydrique pour générer :
    - un conseil de **plantation** (préparer le sol / continuer la culture, selon la saison) ;
    - un conseil d'**irrigation** (volume estimé en saison sèche, surveillance en saison des pluies).
    Anti-doublon : pas plus d'une recommandation de chaque type par parcelle tous les 7 jours.

11. **Utilisateurs** (propriétaire) : CRUD, rôles, activation, langue ; garde-fous contre
    l'auto-suppression et l'auto-retrait des droits.

12. **Authentification** : connexion par e-mail + mot de passe haché (`password_hash`),
    régénération d'ID de session, comptes désactivables, déconnexion propre.

---

## 6. Sécurité (à mettre en avant en soutenance)

- **Mots de passe hachés** (bcrypt via `password_hash` / `password_verify`).
- **Jetons CSRF** sur *tous* les formulaires POST (`csrf_field()` / `verify_csrf()`).
- **Requêtes préparées PDO** partout → protection contre l'injection SQL.
- **Échappement HTML systématique** via `e()` (htmlspecialchars) → protection XSS.
- **Contrôle d'accès serveur** : `require_login()` et `require_proprietaire()`, plus vérifications
  fines (un collaborateur ne modifie que ses tâches ; finances et utilisateurs réservés au propriétaire).
- **Sessions sécurisées** : cookie de session, `session_regenerate_id()` à la connexion.
- **Installateur verrouillé** une fois la base installée.

---

## 7. Internationalisation

- Deux dictionnaires complets (`lang/fr.php`, `lang/en.php`, 197 clés identiques).
- `t('cle')` traduit un libellé, `te('prefixe', 'valeur')` traduit une valeur d'énumération
  (ex : `te('statut','en_culture')` → « En culture » / « Cultivated »).
- Sélecteur de langue dans la barre supérieure ; les paramètres d'URL (`action`, `id`…) sont conservés.

---

## 8. Scénario de démonstration conseillé (5–7 min)

1. **Connexion** propriétaire → tableau de bord (montrer KPI + finances + rentabilité par filière).
2. **Parcelles** : créer/modifier une parcelle, montrer le statut et la culture en cours.
3. **Cultures** : créer un semis, puis cliquer sur ▶ pour avancer les stades (barre de progression),
   jusqu'à « récolté » → la parcelle redevient « libre ».
4. **Récoltes** : enregistrer une récolte → montrer qu'elle apparaît **dans les stocks**.
5. **Transformations** : enregistrer une transformation de manioc → montrer le **rendement** et
   la mise à jour des stocks (produit fini +, manioc −).
6. **Recommandations IA** : générer pour toutes les parcelles → montrer plantation + irrigation.
7. **Tâches** : créer une tâche, l'assigner, changer son statut.
8. **Stocks** : ajuster une quantité pour déclencher une **alerte**.
9. **Changement de langue** FR → EN sur n'importe quelle page.
10. **Déconnexion**, puis reconnexion en **collaborateur** → montrer que Finances et Utilisateurs
    ne sont plus accessibles (retraité) — preuve du contrôle des rôles.

**Comptes de démonstration**
- Propriétaire : `proprietaire@laferme.tg` / `admin123`
- Collaborateur : `collaborateur@laferme.tg` / `collab123`

---

## 9. Corrections et finitions apportées

| # | Problème | Correction |
|---|---|---|
| 1 | Code JS mort (pré-remplissage jamais utilisé) | Supprimé |
| 2 | `$lastByParcelle` calculé mais jamais affiché | Affiché dans la synthèse « Dernière irrigation » |
| 3 | Supprimer une récolte laissait le stock gonflé | Stock décrémenté à la suppression |
| 4 | Supprimer une transformation laissait le stock incohérent | Produit fini retiré + manioc brut restitué |
| 5 | `install.php` réexécutable (risque de `DROP` des données) | Installateur verrouillé après installation |
| 6 | Changement de langue perdait les paramètres d'URL | Paramètres préservés |
| 7 | Le propriétaire pouvait se retirer ses droits (verrouillage) | Garde-fou ajouté |
| 8 | Style redondant sur un KPI | Nettoyé |

**Vérifications passées** : `php -l` sans erreur sur tous les fichiers ; clés i18n utilisées
toutes présentes et les deux langues strictement alignées (197 clés) ; CSRF et échappement OK.

---

## 10. Limites connues et évolutions

- **Élevage (cheptel)** : prévu au cahier des charges (§7.2, entité « Animal/Cheptel ») mais **non développé**.
- **Drone / relevés** : reporté en V2 par le cahier des charges lui-même.
- Le total récolté additionne des quantités d'unités potentiellement différentes (à normaliser).
- Extensions possibles : export PDF/Excel des rapports, mode hors-ligne, multi-fermes.

---

## 11. Installation (pour mémoire)

1. Démarrer **Apache** et **MySQL** dans Ampps.
2. Vérifier les identifiants dans `config/config.php` (par défaut `root` / `mysql`).
3. Ouvrir `http://localhost/.../install.php` → « Lancer l'installation ».
4. Se connecter avec un compte de démonstration.

> Pensez à passer `APP_DEBUG` à `false` dans `config/config.php` pour une mise en production.
