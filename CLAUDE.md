# CLAUDE.md

> Pendant de `.github/copilot-instructions.md` (instructions Copilot) : toute règle commune ajoutée ou modifiée ici est à reporter là-bas, et inversement.

## Contexte

Plugin Jeedom **Monitoring** : surveillance d'un système (Linux, Synology, QNAP, Proxmox, AsusWRT, LXC) en local ou à distance par SSH. Plugin **PHP uniquement**, sans démon.

- Logique principale : `core/class/Monitoring.class.php` (`Monitoring` étend `eqLogic`, `MonitoringCmd` étend `cmd`).
- Actions AJAX : `core/ajax/Monitoring.ajax.php`. Interface : `desktop/php/Monitoring.php`, `desktop/js/Monitoring.js`, modale santé `desktop/modal/health.monitoring.php`.
- Widgets : `core/template/{dashboard,mobile}/` (un template par type : générique, Synology, AsusWRT).
- Connexions SSH : déléguées au plugin **SSH Manager** (`sshmanager`, repo voisin `../SSH-Manager`), dont l'état conditionne `dependancy_info()`. Installation des dépendances via `core/php/Monitoringcli.php`.

## Environnement

- Dev : Windows 11, PowerShell 7. Utiliser l'outil **PowerShell** (pas Bash) pour les commandes ; les outils Grep/Glob pour chercher dans le code. Toute commande proposée à l'utilisateur est en PowerShell.
- Cible : Jeedom sur Debian 11/12/13. Aucun Jeedom local : valider par `php -l`, la CI et une relecture, et signaler ce qui reste à tester sur une box.

## Workflow Git

- **Commits** : faits exclusivement par l'utilisateur via GitHub Desktop. Ne jamais commiter, pousser ni ouvrir de PR soi-même. En fin de tâche, lister les fichiers modifiés. Quand c'est utile pour garder des commits propres et séparés (par exemple avant d'enchaîner sur une autre tâche), signaler que c'est le bon moment pour committer, en précisant le périmètre du commit.
- Branches : `dev` → PR vers `beta` → stable. La CI tourne sur les PR vers `beta`.
- `core/i18n/*.json` est généré par le workflow `translations.yml` : ne jamais l'éditer à la main.

## Compatibilité PHP

- Le code doit fonctionner en PHP 7.4 (Debian 11), 8.2 (Debian 12) et 8.4 (Debian 13). Debian 11 est en fin de vie : la compatibilité 7.4 est maintenue par choix, le temps que les utilisateurs migrent leur Jeedom vers Debian 12 minimum.
- Aucune syntaxe ni fonction propre à PHP 8.x : `str_starts_with`/`str_ends_with`/`str_contains` (→ `strpos`/`substr`), `match` (→ `switch`), named arguments, `enum`, `readonly`, `fibers`, union types (→ pas de type natif sur le paramètre, documenter par un docblock `@param`). Les arrow functions `fn()` sont autorisées (PHP 7.4).
- La CI (`checkPHPCompat.yml`, PR vers `beta`) analyse `core/`, `desktop/` et `plugin_info/` avec PHPStan niveau 0 en PHP 7.4, 8.2 et 8.4.
- Le poste de dev a PHP 8.4 dans le PATH, pour faire apparaître les régressions et les `Deprecated` (installation : `../Documentation/_docs/php-windows-dev.md`). Contrôle après modification, depuis la racine du repo :

  ```powershell
  Get-ChildItem core, desktop, plugin_info -Recurse -Filter *.php |
      ForEach-Object { php -l $_.FullName 2>&1 } |
      Where-Object { $_ -notmatch '^No syntax errors' }
  ```

  PHP 8.4 ne signale pas une syntaxe 8.x incompatible avec 7.4 : ce contrôle-là relève de la règle ci-dessus et de la CI.

## Conventions de code

- Code en anglais et camelCase ; snake_case hérité du Core toléré (`deamon_start`, `dependancy_info`…). Commentaires, logs et labels UI en français.
- Accolades style K&R. Indentation : garder celle du fichier modifié, sans reformatage massif.
- Ne jamais réimplémenter une fonction du Core Jeedom (`log::add`, `config::byKey`, `eqLogic::byType`, `jeedom::getTmpFolder`…) : chercher d'abord dans `../Jeedom/core`.
- Chaînes traduisibles : `__('...', __FILE__)` en PHP, `{{...}}` dans les templates `desktop/php`. Rédiger en français uniquement.
- JavaScript : vanilla JS, pas de jQuery, sauf pour les ponts imposés par Jeedom.

## Documentation utilisateur (repo voisin `../Documentation`, branche `beta`)

- Changelog stable : `../Documentation/fr_FR/Monitoring/changelog.md` ; beta : `../Documentation/fr_FR/Monitoring/beta/changelog.md` ; doc : `../Documentation/fr_FR/Monitoring/index.md`.
- Format et ton : voir `../Documentation/CLAUDE.md`. Commits séparés de ceux de ce repo.
