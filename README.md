# Eemo-Find

Admin-only Laravel 12 application with Blade templates, Bootstrap 5 grid/display utilities, and a responsive navy/violet interface. The only navigation is Dashboard, My Profile, and Security (Activity Log, Active Sessions). No normal-user features or public registration.

## Requirements

- PHP **8.3** for deployment, Composer 2, MySQL **8**
- Extensions: PDO MySQL, mbstring, openssl, fileinfo, XML/DOM, ctype, tokenizer, curl, **GD** with PNG/JPEG/WebP
- Node.js **22 LTS**, npm for Vite; HTTPS and SMTP for production recovery

Laravel 12 supports PHP 8.2–8.5 (https://laravel.com/docs/12.x/releases). Composer retains PHP 8.2 compatibility for the available local runtime; deployment targets PHP 8.3. Vite 6 supports Node 22. Lockfiles pin installed dependencies.

## Install

~~~sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
~~~

On PowerShell replace cp with Copy-Item. Use npm install if a package lockfile is not yet present.

Create a dedicated MySQL database/application account and set DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE=eemo_find, DB_USERNAME and DB_PASSWORD in .env. Then:

~~~sh
php artisan migrate --seed
php artisan storage:link
php artisan admin:create
php artisan serve --host=127.0.0.1 --port=8000
~~~

Open **http://127.0.0.1:8000/admin/login**. The admin:create command prompts for name, email and a hidden, confirmed password. It creates a Super Admin; no default credentials are seeded. Passwords require 12 characters, uppercase/lowercase, a number and symbol.

For disposable SQLite development set DB_CONNECTION=sqlite and create an empty database/database.sqlite before migrations. The ignored workspace .env uses SQLite; .env.example targets MySQL. Never use migrate:fresh on data you need.

## Assets

Use npm run build and php artisan serve for preview. Standalone assets in public/assets render without Node and mirror the resources CSS/JS. Synchronize copies using **php scripts/sync-assets.php** after edits. Layouts use the Vite build when its manifest exists.

CSP targets the production build. Vite HMR needs a local-only policy adjustment for its origin/WebSocket connection; use npm run build for preview. Do not weaken production CSP.

## Password recovery

Configure MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS and MAIL_FROM_NAME. MAIL_SCHEME=smtp supports STARTTLS; use smtps with the service's port for implicit TLS.

APP_URL must be the canonical origin. Reset links use this configuration rather than the incoming Host header. Known/unknown emails get the same UI message; tokens expire in 60 minutes and are single-use. Recovery is rate limited by email and IP.

Use MAIL_MAILER=array locally without sending mail. **Do not use the log mailer** for recovery: it writes reset links in email bodies to logs. The local workspace uses the array mailer.

## Security and authorization

- Private routes use auth:admin and auth.session; authentication pages use guest:admin.
- Login rotates the session ID; logout invalidates it and rotates the CSRF token.
- Sign-in: five failures/minute by email/IP and 20 requests/minute by IP. Recovery is limited by email and IP.
- Mutations use POST/PATCH/PUT/DELETE with CSRF; no GET changes account data.
- Admins edit only their own profile/sessions. Regular admins read only their own audit events; Super Admins read all events and manage branding.
- JPEG/PNG/WebP uploads only: 2 MB maximum, 32–2,000 pixels per dimension. GD re-encodes to PNG, strips metadata/appended content and uses generated filenames. SVG is rejected.
- Only validated fields are assigned. Passwords use Laravel hashing; password/current-password/reset-token fields are excluded from flashed validation input.
- Password changes require current-password verification/confirmation and invalidate other sessions. Resets invalidate all sessions.
- Central audit service accepts fixed descriptions/allowlisted metadata; no request bodies, passwords, reset tokens or raw session IDs. No audit edit/delete routes.
- Events cover login success/failure, logout, profile/photo/logo/password changes and session revocation. A security-settings event supports future operations; no settings module is included.
- Responses include CSP, frame protection, nosniff, referrer policy, no-store and HSTS on HTTPS. Blade escapes dynamic values.

Audit records are read-only **through the interface**, not tamper-proof against database operators. They contain IP/user-agent data; restrict database access and establish operational retention rules.

## Active sessions

~~~dotenv
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_LIFETIME=120
~~~

The migration uses Laravel's principal column name user_id, referencing **admins**, not a users table. The default admin guard associates database sessions with admins.

Only the current admin's sessions within the idle lifetime are listed. Sessions save at request completion; the first listing may need refresh. Current-device detection is internal. Device details are browser-reported and approximate; no fake geolocation.

Revocation uses an encrypted opaque form reference, ownership validation, CSRF and DELETE. Raw session IDs, cookies, authentication tokens and payloads are never displayed. The reference is only a selector and cannot authenticate a request. Revoking the current session signs the admin out.

Other drivers show a clear limitation and disable individual revocation. auth.session rejects stale password hashes on the next request after password change/reset even without enumeration. Persistent remember-me login is not enabled.

Already executing requests cannot be rolled back by revocation. On multiple nodes, use shared database sessions and a shared rate-limit cache (e.g. Redis), and synchronize clocks. Avoid cookie sessions for server-side revocation.

## Tests and verification

~~~sh
php artisan test
composer validate --strict
php artisan view:cache
php artisan route:list --path=admin
npm run build
~~~

PHPUnit uses in-memory SQLite, array mail/cache and temporary upload storage. Coverage includes authentication/rate limits, route protection, profile/email validation, upload replacement/removal/rejection, branding authorization, password verification/change/reset, audit scoping/secret exclusion/filtering/pagination, sessions/revocation/logout, CSRF and headers. Session tests select database where needed.

Tests do not send real email. SQLite does not substitute for MySQL staging validation. **VERIFICATION.md** records checks actually executed.

## Production deployment

1. Serve only **public/** through Nginx/Apache and PHP 8.3 FPM.
2. Run composer install --no-dev --optimize-autoloader and npm ci followed by npm run build.
3. Supply generated APP_KEY, database and SMTP credentials, canonical APP_URL and:

~~~dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-admin-domain.example
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=file
LOG_LEVEL=warning
~~~

4. Run:

~~~sh
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan admin:create
~~~

5. Only storage/ and bootstrap/cache/ should be writable by the web process. Restrict secrets, logs, backups and database files. Use separate migration credentials where appropriate.
6. Terminate HTTPS correctly. Configure trusted proxy addresses explicitly when proxied, for reliable client IP/secure cookies.
7. Set upload limits for 2 MB files, disable script execution under uploads, keep APP_DEBUG=false.
8. Verify MySQL migrations, SMTP, recovery, multiple-browser revocation and mobile layouts in staging. Back up database/uploads before releases.

Sites/Cloudflare Workers cannot run PHP/Laravel. Deploy to a PHP-capable host.

## Structure

- app/Http/Controllers/Admin: separate HTTP controllers
- app/Http/Requests: profile/image/password validation and authorization
- app/Services: audit, branding, profile, image and session operations
- app/Models: Admin, AdminActivityLog, ApplicationSetting
- app/Console/Commands/CreateAdmin.php: secure setup
- resources/views/layouts and components: shared layouts, fields, alerts, cards, tables, buttons, modal, pagination, avatars and branding
- resources/views/admin: required pages only
- database/migrations: admin/session/audit/branding and admin password-reset token tables
- tests/Feature/AdminPanelTest.php: behavior and security tests
