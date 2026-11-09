# Instructions Copilot — Standards de développement

## Environnement et terminal
- OS : Windows 11 (PowerShell 7).
- Contrainte : ne jamais proposer bash/grep. Utiliser `Select-String`.

## Workflow Git
- **Commits** : faits exclusivement par l'utilisateur via GitHub Desktop. Ne jamais commiter, pousser ni ouvrir de PR soi-même. En fin de tâche, lister les fichiers modifiés. Quand c'est utile pour garder des commits propres et séparés (par exemple avant d'enchaîner sur une autre tâche), signaler que c'est le bon moment pour committer, en précisant le périmètre du commit.

## Backend et versions
- Python 3.12+ (typage strict).
- Node.js 22+ (ESM).
- PHP 7.4 / 8.2 / 8.4 (priorité au Core Jeedom) — le code doit fonctionner en PHP 7.4 (Debian 11), 8.2 (Debian 12) et 8.4 (Debian 13). Debian 11 est en fin de vie : la compatibilité 7.4 est maintenue par choix, le temps que les utilisateurs migrent leur Jeedom vers Debian 12 minimum. La CI (`checkPHPCompat.yml`, PR vers `beta`) analyse `core/`, `desktop/` et `plugin_info/` avec PHPStan niveau 0 en PHP 7.4, 8.2 et 8.4. Le poste de dev tourne en PHP 8.4, la version la plus haute, pour faire apparaître les régressions et les `Deprecated` (installation : `../Documentation/_docs/php-windows-dev.md`). Éviter les syntaxes PHP 8.0+ (union types, `str_starts_with`/`str_contains`, `match`, named arguments, `enum`, `readonly`, `fibers`) ; pour un type qui nécessiterait un union type, ne pas mettre de type hint natif sur le paramètre mais documenter via un docblock `@param`.

## Frontend et style
- Vanilla JS (pas de jQuery, sauf pour les ponts avec Jeedom).
- Nommage : camelCase.
- Accolades : style K&R.
