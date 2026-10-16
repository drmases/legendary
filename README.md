# Legendary score board

Score board for all Legendary games (Marvel, Alien, James Bond, Predator, Matrix, Firefly), live at https://globbin.se/legendary/

- `index.html` – frontend. Game list, card types and scoring are configured in `FRANCHISES` at the top of the script.
- `api.php` – PHP/MySQL backend. Reads secrets from `config.php` (never committed, see `config.example.php`).
- Deploy: GitHub Actions (`.github/workflows/deploy.yml`) builds `config.php` from repo secrets and uploads via SFTP.

Required secrets: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_FRONTEND_DIR` (= `webroots/www/legendary/`),
`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `EDIT_PASSWORD`.
