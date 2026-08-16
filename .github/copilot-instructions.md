# Global Development Standards

## Environment & Shell
- OS: Windows 11 (PowerShell 7).
- Constraint: Never suggest bash/grep. Use Select-String.

## Backend & Versions
- Python 3.12+ (Strict typing).
- Node.js 22+ (ESM).
- PHP 8.2+ (Jeedom Core priority) — développement ciblé PHP 8.2, mais le code doit rester **compatible PHP 7.4** (Debian 11 encore supporté). Éviter les syntaxes PHP 8.0+ (union types, `str_starts_with`/`str_contains`, `match`, named arguments, `enum`, `readonly`, `fibers`) ; pour un type qui nécessiterait un union type, ne pas mettre de type hint natif sur le paramètre mais documenter via un docblock `@param`.

## Frontend & Style
- Vanilla JS (No jQuery, except for Jeedom bridges).
- Naming: camelCase.
- Braces: K&R style.