# Rightsize CLIA Compliance — Deployment & Production Hardening

A checklist for taking the app from local dev (Herd + SQLite) to a production,
HIPAA-appropriate deployment (Linux + MySQL). Work top to bottom.

---

## 0. Host

- **Compute:** a small Linux VM is plenty (1–2 vCPU, 2 GB RAM).
- **Pick a host that will sign a BAA** (HIPAA): AWS (EC2/Lightsail + RDS), or DigitalOcean
  (BAA on request, business account). Encrypt disks/DB at rest.
- **Software:** PHP **8.4** with extensions `pdo_mysql, mbstring, openssl, ctype, json,
  bcmath, fileinfo, dom, curl`, Composer 2, Node 20+ (only needed to build assets), and
  a web server (Nginx + PHP-FPM recommended) or Apache (the original LAMP plan).

---

## 1. Get the code & dependencies

```bash
git clone <your-repo> /var/www/rightsize-compliance   # or copy the project
cd /var/www/rightsize-compliance

composer install --no-dev --optimize-autoloader
npm ci
npm run build            # compiles Tailwind/JS into public/build (no CDN at runtime)
```

> Assets are committed-as-built or built on the host. The app no longer depends on the
> Tailwind Play CDN — `npm run build` is required so `public/build/manifest.json` exists.

---

## 2. Environment (`.env`)

Copy `.env.example` to `.env` and set:

```dotenv
APP_NAME="Rightsize CLIA Compliance"
APP_ENV=production
APP_DEBUG=false                      # never true in production
APP_URL=https://compliance.yourdomain.com

# generate once:
#   php artisan key:generate
APP_KEY=base64:...

DB_CONNECTION=mysql
DB_HOST=127.0.0.1                    # or RDS endpoint
DB_PORT=3306
DB_DATABASE=rightsize
DB_USERNAME=rightsize
DB_PASSWORD=<strong-password>

SESSION_DRIVER=database              # default; sessions table is migrated
QUEUE_CONNECTION=sync                # mail is sent synchronously; no worker required

# Mail — Google Workspace SMTP
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=compliance@yourdomain.com
MAIL_PASSWORD=<app-password>         # Workspace app password
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="compliance@yourdomain.com"
MAIL_FROM_NAME="Rightsize CLIA Compliance"

# Google SSO — add the production redirect URI in Google Console
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
AUTH_ALLOWED_DOMAIN=yourdomain.com   # lock logins to your Workspace

# Google Drive auto-filing (optional; Null filer used until set)
GOOGLE_DRIVE_CREDENTIALS=/var/www/secrets/drive-service-account.json
GOOGLE_DRIVE_ROOT_FOLDER_ID=<id of a Drive folder shared with the service account>
```

---

## 3. Database

```sql
CREATE DATABASE rightsize CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rightsize'@'%' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON rightsize.* TO 'rightsize'@'%';
```

```bash
php artisan migrate --force            # --force: required in production

# Create the real lab — provisions the full 17-obligation register (C01–C17), no demo data:
php artisan lab:create "Triad Behavioral Resources"
```

> **Do NOT run `php artisan db:seed` in production.** `DatabaseSeeder` is DEV-ONLY: it creates a
> demo lab plus six placeholder users with `@example.com` emails and the password `password`.
> In production, use `lab:create` (above) for each lab, then onboard real staff via Google SSO.
>
> **Bootstrap the first admin:** have the first real person sign in with Google (creates their
> user with no lab access), then grant them super-admin once:
> ```bash
> php artisan tinker --execute="App\Models\User::where('email','you@yourdomain.com')->update(['is_super_admin'=>true]);"
> ```
> They can then grant lab access + roles to everyone else from **Users & Access** / **Manage Labs**.
> Enable **encryption at rest** on the DB volume / RDS instance.

### MySQL parity check (optional, local, needs Docker)

```bash
docker run -d --name rsl-mysql -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=rightsize -p 3307:3306 mysql:8.4
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=rightsize \
  DB_USERNAME=root DB_PASSWORD=secret php artisan migrate:fresh --seed
```

---

## 4. Optimize (production caches)

```bash
php artisan optimize        # config:cache + route:cache + view:cache + events
php artisan storage:link    # only if you later serve files from storage (not required today)
```

Routes are closure-free, so `route:cache` works. Re-run `php artisan optimize` after any
deploy that changes config/routes/views. To undo: `php artisan optimize:clear`.

---

## 5. Scheduler (reminders)

The reminder ladder + weekly digest run via the Laravel scheduler. Add ONE system cron
entry (the only cron you need):

```cron
* * * * * cd /var/www/rightsize-compliance && php artisan schedule:run >> /dev/null 2>&1
```

This fires `compliance:reminders` daily at 07:00 and `compliance:overdue-digest`
Mondays 07:30 (defined in `routes/console.php`).

---

## 6. Web server (Nginx example)

Point the document root at `public/`, pass PHP to PHP-FPM, force HTTPS.

```nginx
server {
    listen 443 ssl;
    server_name compliance.yourdomain.com;
    root /var/www/rightsize-compliance/public;

    ssl_certificate     /etc/letsencrypt/live/.../fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/.../privkey.pem;

    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known) { deny all; }
}
# redirect :80 -> :443
```

Permissions: `storage/` and `bootstrap/cache/` must be writable by the web user
(`chown -R www-data:www-data storage bootstrap/cache`).

---

## 7. Google setup for production

- **OAuth:** in Google Console add `https://compliance.yourdomain.com/auth/google/callback`
  as an Authorized redirect URI (keep the localhost one for dev). Consent screen = **Internal**.
- **Drive service account:** create a service account, download its JSON key to a path
  **outside** the web root, create a Drive root folder and **share it with the service
  account's email**, then set `GOOGLE_DRIVE_CREDENTIALS` + `GOOGLE_DRIVE_ROOT_FOLDER_ID`.
  This flips the binding from `NullDriveFiler` to `GoogleDriveFiler` automatically.

---

## 8. HIPAA / security checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS enforced; HSTS recommended
- [ ] DB + disk **encrypted at rest**; automated encrypted backups
- [ ] BAA signed with host (and with Workspace; DocuSign/SendGrid if later added)
- [ ] Google login locked to the Workspace domain (`AUTH_ALLOWED_DOMAIN`)
- [ ] App stores **no PHI** — only metadata + Drive links (documents stay in Drive)
- [ ] `audit_log` retained ≥ 7 years; included in backups
- [ ] Secrets (`.env`, Drive key) not in the repo and not world-readable
- [ ] Rotate `GOOGLE_CLIENT_SECRET` before go-live

---

## 9. Smoke test after deploy

1. Visit the URL → redirected to `/login`.
2. Sign in with Google (a Workspace account) → lands on the dashboard.
3. Full Register → set a date → confirm it saves and the Activity tab logs it under your name.
4. Send an obligation for signature → mark signed → mark complete → confirm it files
   (Drive link if configured) and drops off Awaiting Signature.
5. `php artisan compliance:reminders` → check mail is delivered (or logged).
6. Completeness → Export PDF/CSV downloads.
