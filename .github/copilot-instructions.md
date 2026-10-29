# Instructions Copilot — Standards de développement

## Environnement et terminal
- OS : Windows 11 (PowerShell 7).
- Contrainte : ne jamais proposer bash/grep. Utiliser `Select-String`.

## Workflow Git
- **Commits** : faits exclusivement par l'utilisateur via GitHub Desktop. Ne jamais commiter, pousser ni ouvrir de PR soi-même. En fin de tâche, lister les fichiers modifiés. Quand c'est utile pour garder des commits propres et séparés (par exemple avant d'enchaîner sur une autre tâche), signaler que c'est le bon moment pour committer, en précisant le périmètre du commit.

## Backend et versions
- Python 3.12+ (typage strict).
- Node.js 22+ (ESM).
- PHP 8.2+ (priorité au Core Jeedom) — développement ciblé PHP 8.2, mais le code doit rester **compatible PHP 7.4** (Debian 11 encore supporté). Éviter les syntaxes PHP 8.0+ (union types, `str_starts_with`/`str_contains`, `match`, named arguments, `enum`, `readonly`, `fibers`) ; pour un type qui nécessiterait un union type, ne pas mettre de type hint natif sur le paramètre mais documenter via un docblock `@param`.

## Frontend et style
- Vanilla JS (pas de jQuery, sauf pour les ponts avec Jeedom).
- Nommage : camelCase.
- Accolades : style K&R.
