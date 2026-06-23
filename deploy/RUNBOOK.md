# Production Runbook — plain VPS, git deploy, MySQL, IP-first

Tailored to: a Linux VPS, code shipped via **git**, **MySQL already set up**, first bring-up on the
**server IP** (domain + SSL + Google SSO follow in Phase B). See `DEPLOY.md` for the full reference
and the HIPAA checklist. Run as a sudo-capable user; the app lives at `/var/www/rightsize-compliance`.

---

## One-time: get the code into git and onto the server

**On your machine** (already a git repo now):
```bash
# create a PRIVATE repo on GitHub/GitLab first, then:
git remote add origin git@github.com:<you>/rightsize-compliance.git
git push -u origin main
```

**On the server:**
```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone git@github.com:<you>/rightsize-compliance.git rightsize-compliance
sudo chown -R $USER:$USER rightsize-compliance
cd rightsize-compliance
```

**Ship the two secrets out-of-band** (they're git-ignored on purpose — use scp/sftp):
```bash
# from your machine:
scp .config/drive-sa.json   you@SERVER:/tmp/drive-sa.json
# on the server:
sudo mkdir -p /var/www/secrets && sudo mv /tmp/drive-sa.json /var/www/secrets/drive-sa.json
sudo chown www-data:www-data /var/www/secrets/drive-sa.json && sudo chmod 600 /var/www/secrets/drive-sa.json
```

---

## Server prerequisites (once)

PHP **8.4** (`pdo_mysql mbstring openssl ctype json bcmath fileinfo dom curl`), Composer 2,
Node 20+, Nginx + PHP-FPM. (Node is only needed to build assets.)

---

## Deploy / update (run on the server, in the project dir)

```bash
git pull                                   # first time: already cloned
composer install --no-dev --optimize-autoloader
npm ci && npm run build                     # builds public/build (no CDN at runtime)

# .env — first deploy only:
cp deploy/env.production.example .env
# edit .env: APP_URL=http://SERVER_IP, DB_PASSWORD, (Drive path already set)
php artisan key:generate

php artisan migrate --force                 # creates all tables
php artisan lab:create "Triad Behavioral Resources" --clia=<CLIA#>   # 17-obligation register, no demo data

php artisan optimize                        # config+route+view cache (re-run after every deploy)

sudo chown -R www-data:www-data storage bootstrap/cache
```

Then drop in the Nginx site (`deploy/nginx-rightsize.conf`) and reload Nginx (see that file's header).

---

## Scheduler (one cron entry)

```cron
* * * * * cd /var/www/rightsize-compliance && php artisan schedule:run >> /dev/null 2>&1
```
Fires `compliance:reminders` (daily 07:00), `compliance:overdue-digest` (Mon 07:30),
`compliance:ingest-evidence --apply` (daily 06:30). Mail uses the `log` driver until SMTP is set.

---

## Logging in during Phase A (IP, no Google yet)

Google OAuth can't redirect to a bare IP over http, and the dev-login shortcut only works when
`APP_ENV=local`. So you have two choices for the IP stage:

- **Validate infra only (recommended):** keep `APP_ENV=production`. Confirm the site loads and
  redirects to `/login`, assets render, `php artisan migrate`/`lab:create`/`schedule:run` work.
  Interactive login waits for Phase B.
- **Click through the UI now:** temporarily set `APP_ENV=local` (keep `APP_DEBUG=false`), `php artisan
  optimize:clear`, and use the dev-login button. ⚠️ This lets anyone who reaches the IP log in — only
  do it behind a firewall restricted to your IP, and switch back to `APP_ENV=production` before any real data.

Drive auto-filing, scanning, and the Drive SOP mirror work in either mode (service account, no user login).

---

## Phase B — domain cutover (when DNS + SSL are ready)

1. Point the domain's A record at the server; install a Let's Encrypt cert (`certbot --nginx`).
2. Switch `deploy/nginx-rightsize.conf` to the 443/SSL block (see its footer) + 80→443 redirect.
3. In Google Console: add `https://compliance.yourdomain.com/auth/google/callback` as an authorized
   redirect URI; consent screen = Internal.
4. `.env`: `APP_ENV=production`, `APP_URL=https://compliance.yourdomain.com`, set `GOOGLE_CLIENT_ID/SECRET`,
   `AUTH_ALLOWED_DOMAIN=yourdomain.com`, and switch mail to Workspace SMTP (see `DEPLOY.md` §2).
5. `php artisan optimize`. Bootstrap the first super-admin (DEPLOY.md §3), then onboard staff.

---

## Smoke test (DEPLOY.md §9)

Site loads → `/login`; (Phase B) Google sign-in → dashboard; set a register date → Activity logs it;
send for signature → sign → complete → files to Drive; `compliance:reminders` logs/sends mail;
Completeness → export PDF/CSV.
