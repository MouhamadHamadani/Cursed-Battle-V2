# Production Deployment Runbook (Hetzner VPS)

**⚠️ WARNING: NOT YET EXECUTED**

This document is a **reference for future deployment only**. None of these steps have been run. They are written for the day you deploy to production; don't execute them blindly — verify your VPS setup, adjust paths/IPs/domains, and test in staging first.

---

## Server Prerequisites

### OS & packages
- Debian 12 or Ubuntu 22.04+ on Hetzner
- `apt update && apt upgrade`

### PHP 8.3 + required extensions
```bash
apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-dom php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-opcache
```

### MySQL 8.0+ (InnoDB engine)
```bash
apt install -y mysql-server
```
Confirm InnoDB is the default engine:
```sql
SELECT @@default_storage_engine;  -- should return InnoDB
```

### nginx & php-fpm
```bash
apt install -y nginx php8.3-fpm
systemctl start nginx php8.3-fpm
systemctl enable nginx php8.3-fpm
```

### Composer, Node.js, git
```bash
apt install -y composer nodejs npm git
```

---

## Application Directory

Create a deploy user and application directory:
```bash
useradd -m -s /bin/bash deploy
mkdir -p /var/www/cursed-battle
chown -R deploy:deploy /var/www/cursed-battle
```

Clone the repository (or deploy via git/rsync — adjust to your workflow):
```bash
cd /var/www/cursed-battle
git clone <your-repo-url> .
```

---

## nginx + php-fpm configuration

### php-fpm pool
File: `/etc/php/8.3/fpm/pool.d/cursed-battle.conf`

```ini
[cursed-battle]
user = deploy
group = deploy
listen = /run/php/cursed-battle.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500
```

Reload: `systemctl reload php8.3-fpm`

### nginx server block
File: `/etc/nginx/sites-available/cursed-battle`

```nginx
server {
    listen 80;
    server_name cursed-battle.example.com;  # replace with your domain
    
    root /var/www/cursed-battle/public;
    index index.php index.html;
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/cursed-battle.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ /\. {
        deny all;
    }
    
    client_max_body_size 2M;
}
```

Enable and test:
```bash
ln -s /etc/nginx/sites-available/cursed-battle /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

---

## Production environment

### `.env` file
Create `/var/www/cursed-battle/.env` with:

```env
APP_NAME="Cursed Battle"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cursed-battle.example.com

APP_KEY=<generate-a-fresh-key>
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cursed_battle
DB_USERNAME=cursed_battle_user
DB_PASSWORD=<strong-password>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

QUEUE_CONNECTION=database
CACHE_STORE=database
BROADCAST_CONNECTION=log

LOG_CHANNEL=stack
LOG_LEVEL=info

MAIL_MAILER=log
```

**Key differences from `.env.example`:**
- `APP_ENV=production` (not `local`)
- `APP_DEBUG=false` (never `true` in production)
- Fresh `APP_KEY` generated during deployment
- Real DB credentials
- `QUEUE_CONNECTION=database` (no jobs queued today; a worker is optional; see Queue section below)
- `CACHE_STORE=database`, `SESSION_DRIVER=database` (no Redis/Memcached setup required for MVP)
- `LOG_LEVEL=info` (suppress debug noise)

### Generate application key
```bash
php artisan key:generate
```

---

## Release steps

Run these once per deployment:

### 1. Prepare dependencies
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 2. Publish storage symlink
```bash
php artisan storage:link
```

### 3. Run migrations
```bash
php artisan migrate --force
```

**Caution:** `--force` suppresses the production confirmation prompt. Always review your migration files before running in production.

### 4. Cache configuration & routes
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

These commands precompile Laravel's configuration, routes, views, and event listeners into single files for fast loading. No re-execution at request time.

### 5. Optimize autoloader
```bash
php artisan optimize
```

(Already done by `composer install --optimize-autoloader`, but safe to repeat.)

### 6. Fix file permissions
```bash
chown -R deploy:deploy /var/www/cursed-battle
chmod -R 755 /var/www/cursed-battle
chmod -R 775 /var/www/cursed-battle/storage
chmod -R 775 /var/www/cursed-battle/bootstrap/cache
```

---

## Database setup

Create the application database and user:
```sql
CREATE DATABASE cursed_battle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cursed_battle_user'@'localhost' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON cursed_battle.* TO 'cursed_battle_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## The regen scheduler (LOAD-BEARING — DO NOT SKIP)

Energy and health regeneration runs via Laravel's scheduled command `game:regen-tick` every 5 minutes. **Without this cron, the regen system does not work.**

Add to the `deploy` user's crontab (`crontab -e`):
```
* * * * * cd /var/www/cursed-battle && php artisan schedule:run >> /dev/null 2>&1
```

This cron runs the Laravel scheduler every minute; the scheduler itself decides which commands to execute based on their configured frequency (regen ticks every 5 min).

Verify:
```bash
cd /var/www/cursed-battle
php artisan schedule:list
# should show:  */5 * * * * php artisan game:regen-tick
```

---

## Queue worker (optional now, load-bearing later)

Currently **no jobs are queued** — regen is a scheduled command, not a job. The queue driver is set to `database` for simplicity on this small VPS.

If jobs are added in the future (e.g., email, analytics), run a worker under Supervisor:

### Install Supervisor
```bash
apt install -y supervisor
```

### Configure Supervisor
File: `/etc/supervisor/conf.d/cursed-battle-queue.conf`

```ini
[program:cursed-battle-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cursed-battle/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/cursed-battle-queue.log
user=deploy
```

Enable:
```bash
supervisorctl reread
supervisorctl update
supervisorctl start cursed-battle-queue:*
```

**For MVP, skip this.** Add only if you add background jobs.

---

## TLS/HTTPS (strongly recommended)

Use Let's Encrypt (free) via Certbot:
```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d cursed-battle.example.com
```

Certbot auto-updates your nginx config to redirect http→https and renews certificates automatically.

---

## Rollback

If a release breaks production:

1. **Redeploy the previous release:**
   ```bash
   git checkout <previous-commit>
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```

2. **If the migration direction changed:**
   ```bash
   php artisan migrate:rollback --step=1
   ```
   (Adjust `--step` to undo multiple migrations. Test rollback logic in staging first.)

3. **Clear application cache:**
   ```bash
   php artisan cache:clear
   php artisan view:clear
   ```

---

## Pre-deployment checklist

**DO NOT run any of these yet. This is a reference list to review before you deploy.**

- [ ] `.env` is configured with real DB credentials, `APP_DEBUG=false`, and a fresh `APP_KEY`
- [ ] Database is created and user has full privileges
- [ ] Migrations are reviewed; rollback paths are clear
- [ ] `composer install`, `npm install`, and `npm run build` succeed locally
- [ ] The scheduler cron is installed in the deploy user's crontab
- [ ] Storage permissions are correct (`bootstrap/cache`, `storage` writable by php-fpm)
- [ ] nginx config syntax is valid (`nginx -t`)
- [ ] TLS/HTTPS is configured (Certbot or manual cert)
- [ ] Database backups are in place (pre-deployment safety net)
- [ ] Monitoring/alerting is set up (optional; nice to have)
- [ ] A rollback plan exists (previous release commit, migration rollback steps)

---

## Monitoring & logs

### Application log
```bash
tail -f /var/www/cursed-battle/storage/logs/laravel.log
```

### nginx access/error
```bash
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

### php-fpm error log
```bash
tail -f /var/log/php8.3-fpm.log
```

### MySQL slow queries (optional)
In `/etc/mysql/mysql.conf.d/mysqld.cnf`:
```ini
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
```

### Cron execution log (optional; regen ticks)
Add `>> /var/log/cursed-battle-cron.log 2>&1` to the cron instead of `/dev/null`:
```
* * * * * cd /var/www/cursed-battle && php artisan schedule:run >> /var/log/cursed-battle-cron.log 2>&1
```

---

## Notes

- This runbook assumes a fresh Hetzner VPS. Adjust paths, usernames, and domains to your setup.
- Session, cache, and queue all use the `database` driver — suitable for MVP. If scaling later, move to Redis.
- The scheduler cron (`schedule:run`) is the single most important line — without it, regen breaks silently.
- Confirm staging deployment works before running in production.
- Keep backups of your database before and after each release.
