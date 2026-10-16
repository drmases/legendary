# Legendary score board

Score board for all Legendary games (Marvel, Alien, James Bond, Predator, Matrix, Firefly), live at https://globbin.se/legendary/

- `index.html` – frontend. Game list, card types and scoring are configured in `FRANCHISES` at the top of the script.
- `api.php` – PHP/MySQL backend. Reads secrets from `config.php` (never committed, see `config.example.php`).
  Schema changes are numbered migrations in `migrate()`; the current version is stored in the `legendary_meta` table.
- `redirect/marvel-legendary/` – the old address redirects to `/legendary/#marvel` (301 via `.htaccess`, meta refresh as fallback).
- Deploy: GitHub Actions (`.github/workflows/deploy.yml`) builds `config.php` from repo secrets and uploads via SFTP.

Required secrets: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_FRONTEND_DIR` (= `webroots/www/legendary/`),
`DB_PASS`, `EDIT_PASSWORD`. Database host, name and user are set in the workflow.
