# Royal Travel — single Laravel root

This package is the **nested `royal-travel-backend` project only**, promoted to a single project root. The duplicate outer Laravel project is intentionally excluded; no outer source files are silently mixed except the corrected `routes/admin.php`.

## Install safely
1. Extract into a **new folder** such as `C:\wamp64\www\royal-travel-unified` (do not overwrite either old folder).
2. Copy your existing private `.env` from the working backend, verify PostgreSQL credentials and APP_URL, and never commit `.env`.
3. Run `composer install`, `npm install`, `npm run build` as needed.
4. Inspect `php artisan migrate:status` before applying migrations. **Never run migrate:fresh on your existing database.** Two historical creator-type migration filenames are retained and made idempotent. The original add-fields migration now uses bigint for new databases; existing databases are handled by the fix migration.
5. Run `php artisan route:list --path=admin/companies`, then `php artisan serve --port=8001` from THIS root.
6. Open `http://127.0.0.1:8001/admin/companies/create` after Admin login.

## Important unfinished functionality
The Add Company submit button is intentionally disabled: contact ID/phone/nationality persistence, password-reset routing to `/b2b`, mail configuration, role authorization tests, and transactional file handling need validation before production activation. Do not submit real identity documents yet.

## Security
No `.env`, `.git`, uploaded private files, logs, `vendor`, or `node_modules` are included. Run Composer/NPM to restore dependencies. The old local Git histories are left untouched on your computer.
