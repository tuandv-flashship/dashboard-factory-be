# Production Deployment Guide — Dashboard Factory

> Tài liệu hướng dẫn triển khai, vận hành và bảo trì hệ thống Dashboard Factory trên môi trường **Production** (Ubuntu 22.04+ / Debian).

---

## Mục lục

1. [Tổng quan kiến trúc](#1-tổng-quan-kiến-trúc)
2. [Yêu cầu hệ thống](#2-yêu-cầu-hệ-thống)
3. [Cài đặt server](#3-cài-đặt-server)
4. [Cấu hình ứng dụng](#4-cấu-hình-ứng-dụng)
5. [Cấu hình Supervisor](#5-cấu-hình-supervisor)
6. [Cấu hình Crontab](#6-cấu-hình-crontab)
7. [Cấu hình Nginx](#7-cấu-hình-nginx)
8. [SSL Certificate](#8-ssl-certificate)
9. [Triển khai lần đầu](#9-triển-khai-lần-đầu)
10. [Deploy code mới](#10-deploy-code-mới)
11. [Vận hành hàng ngày](#11-vận-hành-hàng-ngày)
12. [Monitoring & Alerting](#12-monitoring--alerting)
13. [Backup & Recovery](#13-backup--recovery)
14. [Troubleshooting](#14-troubleshooting)
15. [Phụ lục](#15-phụ-lục)

---

## 1. Tổng quan kiến trúc

### Multi-Factory Architecture

Hệ thống chạy **cùng 1 codebase** nhưng deploy thành **2 instance riêng biệt**:

|                  | FlashShip (FLS)          | PrintDash (PD)          |
| ---------------- | ------------------------ | ----------------------- |
| **Directory**    | `/var/www/dashboard-fls` | `/var/www/dashboard-pd` |
| **Database**     | `dashboard_fls`          | `dashboard_pd`          |
| **Octane port**  | `8000`                   | `8001`                  |
| **Redis prefix** | `fls_`                   | `pd_`                   |
| **Domain**       | `fls-api.example.com`    | `pd-api.example.com`    |

### Technology Stack

| Component           | Technology                  | Vai trò                                        |
| ------------------- | --------------------------- | ---------------------------------------------- |
| **Web Server**      | Laravel Octane + FrankenPHP | Persistent in-memory app server                |
| **Reverse Proxy**   | Nginx                       | SSL termination, static assets, load balancing |
| **Queue**           | Redis + Laravel Horizon     | Background jobs, auto-scaling workers          |
| **Cache**           | Redis                       | Application cache (<1ms response)              |
| **Database**        | MySQL 8.0+                  | Primary data store                             |
| **Scheduler**       | Crontab + Laravel Scheduler | Periodic tasks                                 |
| **Process Manager** | Supervisor                  | Keep Octane + Horizon alive                    |

### Request Flow

```
Client (HTTPS)
    │
    ▼
┌─────────┐     ┌─────────────────────────────────────┐
│  Nginx  │────▶│  Octane (FrankenPHP) :8000 / :8001  │
│  :443   │     │  ┌─ In-memory Laravel app           │
└─────────┘     │  ├─ Redis cache (<1ms)              │
                │  └─ MySQL queries                   │
                └───────────────┬─────────────────────┘
                                │ dispatch jobs
                                ▼
                ┌─────────────────────────────────────┐
                │  Horizon (Queue Workers)            │
                │  ├─ default queue                   │
                │  ├─ sync queue (parallel dept sync)  │
                │  ├─ notifications queue             │
                │  └─ media queue                     │
                └───────────────┬─────────────────────┘
                                │
                                ▼
                         ┌──────────┐
                         │  Redis   │
                         │ Queue +  │
                         │  Cache   │
                         └──────────┘
```

### Architecture Diagram

```
┌─────────────────────────────────────────────────────┐
│  FlashShip (FLS) — /var/www/dashboard-fls           │
│                                                     │
│  Supervisor                                         │
│  ├── dashboard-fls-octane  (1 process)              │ ← Octane FrankenPHP :8000
│  └── dashboard-fls-horizon (1 process)              │ ← Queue workers (auto-managed)
│                                                     │
│  Crontab                                            │
│  └── * * * * * schedule:run                         │ ← Short-lived, mỗi phút
│      ├── ActivateHourlyRecordsJob  (hourly)         │
│      ├── CreateDailyShiftJob       (04:50)          │
│      └── horizon:snapshot          (5min)           │
├─────────────────────────────────────────────────────┤
│  PrintDash (PD) — /var/www/dashboard-pd             │
│                                                     │
│  Supervisor                                         │
│  ├── dashboard-pd-octane   (1 process)              │ ← Octane FrankenPHP :8001
│  └── dashboard-pd-horizon  (1 process)              │ ← Queue workers (auto-managed)
│                                                     │
│  Crontab                                            │
│  └── * * * * * schedule:run                         │ ← Short-lived, mỗi phút
│      ├── ActivateHourlyRecordsJob  (hourly)         │
│      ├── CreateDailyShiftJob       (04:50)          │
│      └── horizon:snapshot          (5min)           │
└─────────────────────────────────────────────────────┘
          │                │
    ┌─────▼─────┐    ┌────▼────┐
    │   Redis    │    │  Nginx  │ ← SSL + static assets
    │ Queue+Cache│    └─────────┘
    └───────────┘
```

---

## 2. Yêu cầu hệ thống

### Hardware tối thiểu

| Resource | Minimum   | Recommended |
| -------- | --------- | ----------- |
| CPU      | 2 cores   | 4+ cores    |
| RAM      | 2 GB      | 4+ GB       |
| Disk     | 20 GB SSD | 50+ GB SSD  |

### Software

| Package    | Version | Lệnh kiểm tra           |
| ---------- | ------- | ----------------------- |
| Ubuntu     | 22.04+  | `lsb_release -a`        |
| PHP        | 8.2+    | `php -v`                |
| MySQL      | 8.0+    | `mysql -V`              |
| Redis      | 7.0+    | `redis-server -v`       |
| Nginx      | 1.18+   | `nginx -v`              |
| Supervisor | 4.0+    | `supervisorctl version` |
| Composer   | 2.x     | `composer -V`           |
| Git        | 2.x     | `git --version`         |

### PHP Extensions cần thiết

```
php-cli php-fpm php-mysql php-redis php-mbstring php-xml
php-curl php-zip php-bcmath php-gd php-intl php-opcache
```

> **Quan trọng:** Production nên dùng **php-redis** (C extension, nhanh hơn 2-5x) thay vì predis (pure PHP dùng cho dev).

---

## 3. Cài đặt server

### 3.1 Cài packages

```bash
# System packages
sudo apt-get update
sudo apt-get install -y \
    nginx supervisor redis-server git unzip curl \
    php8.3-cli php8.3-fpm php8.3-mysql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
    php8.3-bcmath php8.3-gd php8.3-intl php8.3-opcache

# Enable services
sudo systemctl enable nginx supervisor redis-server
sudo systemctl start nginx supervisor redis-server
```

### 3.2 Cài Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3.3 Cấu hình Redis

```bash
sudo nano /etc/redis/redis.conf
```

Thay đổi:

```ini
# Bảo mật: đặt password
requirepass YOUR_STRONG_REDIS_PASSWORD

# Memory limit (tùy RAM server)
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence (cho queue — tránh mất job khi restart)
appendonly yes
appendfsync everysec
```

```bash
sudo systemctl restart redis-server

# Verify
redis-cli -a YOUR_STRONG_REDIS_PASSWORD ping
# → PONG
```

### 3.4 Cấu hình PHP OPcache

```bash
sudo nano /etc/php/8.3/cli/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=30000
opcache.validate_timestamps=0   ; Production: don't check file changes (faster)
opcache.save_comments=1          ; Required for Laravel annotations
opcache.jit=1255                 ; JIT enabled
opcache.jit_buffer_size=256M
```

> **⚠️ `validate_timestamps=0`:** PHP không tự detect file changes → phải chạy `octane:reload` sau deploy để load code mới.

### 3.5 Tạo directories

```bash
# Tạo web directories
sudo mkdir -p /var/www/dashboard-fls
sudo mkdir -p /var/www/dashboard-pd

# Set owner
sudo chown -R www-data:www-data /var/www/dashboard-fls
sudo chown -R www-data:www-data /var/www/dashboard-pd
```

---

## 4. Cấu hình ứng dụng

### 4.1 Clone code

```bash
# FLS
cd /var/www/dashboard-fls
sudo -u www-data git clone <repo-url> .

# PD (cùng codebase)
cd /var/www/dashboard-pd
sudo -u www-data git clone <repo-url> .
```

### 4.2 Install dependencies

```bash
cd /var/www/dashboard-fls
sudo -u www-data composer install --no-dev --optimize-autoloader

cd /var/www/dashboard-pd
sudo -u www-data composer install --no-dev --optimize-autoloader
```

### 4.3 Cấu hình .env

Mỗi factory có `.env` riêng:

#### `/var/www/dashboard-fls/.env`

```env
# ── App ──────────────────────────────────
APP_NAME=FlashShip
FACTORY=FLS
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://fls-api.example.com
API_URL=https://fls-api.example.com
FRONTEND_URL=https://fls-dashboard.example.com
APP_TIMEZONE=America/Chicago

# ── Database ─────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dashboard_fls
DB_USERNAME=dashboard_user
DB_PASSWORD=STRONG_DB_PASSWORD

# ── Fplatform (read-only remote DB) ─────
DB_CONNECTION_FPLATFORM=mysql
DB_HOST_FPLATFORM=your-rds-endpoint.amazonaws.com
DB_PORT_FPLATFORM=3306
DB_DATABASE_FPLATFORM=fplatform
DB_USERNAME_FPLATFORM=dashboard_data
DB_PASSWORD_FPLATFORM=FPLATFORM_DB_PASSWORD

# ── Redis ────────────────────────────────
REDIS_CLIENT=phpredis          # ← Production: dùng C extension
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=YOUR_STRONG_REDIS_PASSWORD
REDIS_PORT=6379
REDIS_PREFIX=fls_              # ← QUAN TRỌNG: khác PD

# ── Queue ────────────────────────────────
QUEUE_CONNECTION=redis

# ── Cache ────────────────────────────────
CACHE_STORE=redis
CACHE_PREFIX=fls_cache_

# ── Session ──────────────────────────────
SESSION_DRIVER=database
SESSION_LIFETIME=120

# ── Logging ──────────────────────────────
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=error               # ← Production: chỉ log errors

# ── Octane ───────────────────────────────
OCTANE_SERVER=frankenphp
OCTANE_HTTPS=true

# ── Mail (Production: dùng SMTP) ────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# ── Shift Schedule ───────────────────────
DAILY_SHIFT_JOB_AT=04:50      # Chạy 1h10 phút trước ca sớm nhất

# ── Passport OAuth Client ────────────────
# Credentials để Frontend (Next.js) xác thực với API
CLIENT_WEB_ID=<uuid-từ-bảng-oauth_clients>
CLIENT_WEB_SECRET=<secret-từ-bảng-oauth_clients>
```

> **⚠️ `CLIENT_WEB_ID` / `CLIENT_WEB_SECRET`:**
> **Laravel Passport OAuth client credentials**.
> Mỗi factory **phải có cặp credentials riêng** vì chúng nằm trong database riêng biệt.

#### Cách tạo Passport Client cho Production

```bash
# FLS — Tạo Password Grant Client
cd /var/www/dashboard-fls
php artisan passport:client --password --name="FLS Web Client"
# → Ghi lại Client ID và Client Secret hiển thị
# → Điền vào .env: CLIENT_WEB_ID=<id>  CLIENT_WEB_SECRET=<secret>

# PD — Tạo Password Grant Client
cd /var/www/dashboard-pd
php artisan passport:client --password --name="PD Web Client"
# → Ghi lại Client ID và Client Secret hiển thị
# → Điền vào .env: CLIENT_WEB_ID=<id>  CLIENT_WEB_SECRET=<secret>
```

> **📝 Lưu ý:**
>
> - Client credentials được lưu trong bảng `oauth_clients` của **mỗi database factory**.
> - **Không dùng lẫn** credentials giữa FLS và PD (khác database → khác bảng `oauth_clients`).
> - Nếu mất secret → tạo client mới và cập nhật `.env` + `config:cache`.

#### `/var/www/dashboard-pd/.env`

Tương tự FLS, **thay đổi các biến sau:**

```env
APP_NAME=PrintDash
FACTORY=PD
APP_URL=https://pd-api.example.com
API_URL=https://pd-api.example.com
FRONTEND_URL=https://pd-dashboard.example.com
DB_DATABASE=dashboard_pd
REDIS_PREFIX=pd_               # ← KHÁC FLS
CACHE_PREFIX=pd_cache_
CLIENT_WEB_ID=<pd-client-id>   # ← TẠO RIÊNG cho PD
CLIENT_WEB_SECRET=<pd-secret>  # ← TẠO RIÊNG cho PD
```

> **⚠️ CRITICAL:** `REDIS_PREFIX` **phải khác nhau** giữa FLS và PD. Nếu trùng → queue jobs, cache data, Horizon metrics sẽ bị cross-contamination.

### 4.4 Permissions

```bash
# FLS
sudo chown -R www-data:www-data /var/www/dashboard-fls/storage
sudo chown -R www-data:www-data /var/www/dashboard-fls/bootstrap/cache
sudo chmod -R 775 /var/www/dashboard-fls/storage
sudo chmod -R 775 /var/www/dashboard-fls/bootstrap/cache

# PD
sudo chown -R www-data:www-data /var/www/dashboard-pd/storage
sudo chown -R www-data:www-data /var/www/dashboard-pd/bootstrap/cache
sudo chmod -R 775 /var/www/dashboard-pd/storage
sudo chmod -R 775 /var/www/dashboard-pd/bootstrap/cache
```

### 4.5 Cache ứng dụng (production optimization)

```bash
# FLS
cd /var/www/dashboard-fls
php artisan key:generate       # Chỉ lần đầu
php artisan config:cache       # Cache config → file (không cần parse .env mỗi request)
php artisan route:cache        # Cache routes → file
php artisan view:cache         # Compile tất cả Blade templates
php artisan event:cache        # Cache event-listener mappings

# PD — lặp lại tương tự
cd /var/www/dashboard-pd
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 4.6 Database

```bash
# Tạo database
mysql -u root -p -e "CREATE DATABASE dashboard_fls CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE DATABASE dashboard_pd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Tạo user (least privilege)
mysql -u root -p -e "
  CREATE USER 'dashboard_user'@'localhost' IDENTIFIED BY 'STRONG_DB_PASSWORD';
  GRANT ALL PRIVILEGES ON dashboard_fls.* TO 'dashboard_user'@'localhost';
  GRANT ALL PRIVILEGES ON dashboard_pd.* TO 'dashboard_user'@'localhost';
  FLUSH PRIVILEGES;
"

# Run migrations
cd /var/www/dashboard-fls && php artisan migrate --force
cd /var/www/dashboard-pd && php artisan migrate --force

# Seed initial data (users, departments, machines, shift templates)
cd /var/www/dashboard-fls && php artisan db:seed --force
cd /var/www/dashboard-pd && php artisan db:seed --force

# Sync permissions từ container configs vào bảng permissions
cd /var/www/dashboard-fls && php artisan apiato:permissions-sync
cd /var/www/dashboard-pd && php artisan apiato:permissions-sync
```

> **📝 `apiato:permissions-sync`:**
> Command này đọc tất cả permission configs từ các Container và sync vào bảng `permissions`.
>
> - **Phải chạy sau `db:seed`** để đảm bảo bảng permissions đã tồn tại.
> - **Phải chạy lại mỗi lần deploy** nếu code có thêm/sửa permissions mới.
> - Thêm `--prune` để xóa permissions không còn trong config: `php artisan apiato:permissions-sync --prune`

---

## 5. Cấu hình Supervisor

Supervisor quản lý 2 loại long-running process:

- **Octane** — web server (in-memory)
- **Horizon** — queue worker manager (auto-scaling)

### 5.1 Tạo config file

```bash
sudo nano /etc/supervisor/conf.d/dashboard-factory.conf
```

### 5.2 Nội dung config

```ini
;============================================================
; FlashShip (FLS) — Octane Server (FrankenPHP)
;============================================================
[program:dashboard-fls-octane]
process_name=%(program_name)s
command=php /var/www/dashboard-fls/artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000
directory=/var/www/dashboard-fls
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dashboard-fls/storage/logs/octane.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=10
environment=APP_ENV="production",FACTORY="FLS"

;============================================================
; FlashShip (FLS) — Horizon (Queue Dashboard + Workers)
;============================================================
[program:dashboard-fls-horizon]
process_name=%(program_name)s
command=php /var/www/dashboard-fls/artisan horizon
directory=/var/www/dashboard-fls
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dashboard-fls/storage/logs/horizon.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=3600
environment=APP_ENV="production",FACTORY="FLS"

;============================================================
; PrintDash (PD) — Octane Server (FrankenPHP)
;============================================================
[program:dashboard-pd-octane]
process_name=%(program_name)s
command=php /var/www/dashboard-pd/artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8001
directory=/var/www/dashboard-pd
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dashboard-pd/storage/logs/octane.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=10
environment=APP_ENV="production",FACTORY="PD"

;============================================================
; PrintDash (PD) — Horizon (Queue Dashboard + Workers)
;============================================================
[program:dashboard-pd-horizon]
process_name=%(program_name)s
command=php /var/www/dashboard-pd/artisan horizon
directory=/var/www/dashboard-pd
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dashboard-pd/storage/logs/horizon.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=3600
environment=APP_ENV="production",FACTORY="PD"
```

### 5.3 Giải thích tham số

#### Octane

| Tham số            | Giá trị                            | Giải thích                                             |
| ------------------ | ---------------------------------- | ------------------------------------------------------ |
| `command`          | `octane:start --server=frankenphp` | FrankenPHP app server — giữ Laravel in-memory          |
| `--host=127.0.0.1` |                                    | Chỉ listen local (Nginx proxy phía trước)              |
| `--port`           | `8000` / `8001`                    | Port riêng cho mỗi factory                             |
| `numprocs`         | `1`                                | 1 Octane master process (tự quản lý workers bên trong) |
| `stopwaitsecs`     | `10`                               | Thời gian chờ graceful shutdown                        |

#### Horizon

| Tham số        | Giá trị              | Giải thích                                               |
| -------------- | -------------------- | -------------------------------------------------------- |
| `command`      | `horizon`            | Horizon master — tự spawn/balance queue workers          |
| `numprocs`     | `1`                  | Chỉ cần 1 Horizon process (tự quản lý workers bên trong) |
| `stopwaitsecs` | `3600`               | Đợi job hiện tại xong trước khi kill (max 1h)            |
| Workers config | `config/horizon.php` | Số workers, strategy, queues — config bằng code          |

> **Horizon vs queue:work:** Horizon **thay thế** `queue:work`. Không chạy cả 2. Horizon cung cấp: web dashboard `/horizon`, auto-scaling, job metrics, failed job management.

### 5.4 Load config

```bash
sudo supervisorctl reread
sudo supervisorctl update

# Verify
sudo supervisorctl status
# dashboard-fls-octane      RUNNING   pid 12340, uptime 0:00:05
# dashboard-fls-horizon     RUNNING   pid 12345, uptime 0:00:05
# dashboard-pd-octane       RUNNING   pid 12350, uptime 0:00:05
# dashboard-pd-horizon      RUNNING   pid 12355, uptime 0:00:05
```

---

## 6. Cấu hình Crontab

Laravel Scheduler chạy qua crontab (1 phút/lần, short-lived).

```bash
sudo crontab -u www-data -e
```

Thêm:

```cron
# Dashboard Factory — Laravel Scheduler
* * * * * cd /var/www/dashboard-fls && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /var/www/dashboard-pd && php artisan schedule:run >> /dev/null 2>&1
```

### Scheduled Jobs hiện tại

| Job                        | Schedule          | Mô tả                                                       |
| -------------------------- | ----------------- | ----------------------------------------------------------- |
| `SyncHourlyRecordsJob`     | Mỗi N phút        | Dispatch batch song song `SyncDepartmentHourlyJob` per dept |
| `ActivateHourlyRecordsJob` | Mỗi giờ (`:00`)   | Chuyển status hourly records: pending → active → completed  |
| `CreateDailyShiftJob`      | `04:50` hàng ngày | Tự tạo Ca 1, fetch tồn đầu ngày từ Fplatform                |
| `horizon:snapshot`         | Mỗi 5 phút        | Capture metrics cho Horizon dashboard graphs                |

### Verify

```bash
# Kiểm tra crontab
sudo crontab -u www-data -l

# Xem scheduled jobs
cd /var/www/dashboard-fls && php artisan schedule:list
cd /var/www/dashboard-pd && php artisan schedule:list
```

### Tại sao Crontab thay vì Supervisor?

| Tiêu chí          | Crontab ✅                  | Supervisor ❌         |
| ----------------- | --------------------------- | --------------------- |
| **Timing**        | OS kernel trigger chính xác | `sleep 60` bị drift   |
| **Resource**      | Spawn khi cần, tự exit      | Giữ process idle 24/7 |
| **Best practice** | Laravel docs chính thức     | Workaround            |

> **Quy tắc:** Supervisor cho **long-running** (Octane, Horizon). Crontab cho **periodic** (Scheduler).

---

## 7. Cấu hình Nginx

Nginx đóng vai trò reverse proxy:

- SSL termination (HTTPS)
- Serve static assets trực tiếp (images, CSS, JS)
- WebSocket proxy (nếu cần Reverb)

### 7.1 FLS Config

```bash
sudo nano /etc/nginx/sites-available/dashboard-fls
```

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

server {
    listen 80;
    server_name fls-api.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name fls-api.example.com;

    ssl_certificate /etc/letsencrypt/live/fls-api.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/fls-api.example.com/privkey.pem;

    # Security headers
    server_tokens off;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    root /var/www/dashboard-fls/public;
    index index.php;
    charset utf-8;

    # Static assets — serve directly (bypass Octane)
    location /build/ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    location /storage/ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public";
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # All requests → proxy to Octane
    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;

        proxy_pass http://127.0.0.1:8000;

        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # Request size limit (file uploads)
    client_max_body_size 50M;

    # Deny hidden files
    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

### 7.2 PD Config

Copy FLS config, thay đổi:

- `server_name` → `pd-api.example.com`
- `root` → `/var/www/dashboard-pd/public`
- `proxy_pass` → `http://127.0.0.1:8001`
- SSL paths → PD domain

```bash
sudo cp /etc/nginx/sites-available/dashboard-fls /etc/nginx/sites-available/dashboard-pd
sudo nano /etc/nginx/sites-available/dashboard-pd
# Sửa 3 điểm trên
```

### 7.3 Enable sites

```bash
sudo ln -sf /etc/nginx/sites-available/dashboard-fls /etc/nginx/sites-enabled/
sudo ln -sf /etc/nginx/sites-available/dashboard-pd /etc/nginx/sites-enabled/

# Test config
sudo nginx -t

# Reload
sudo systemctl reload nginx
```

---

## 8. SSL Certificate

### Dùng Let's Encrypt (miễn phí)

```bash
# Cài certbot
sudo apt-get install -y certbot python3-certbot-nginx

# FLS
sudo certbot --nginx -d fls-api.example.com

# PD
sudo certbot --nginx -d pd-api.example.com

# Auto-renew (certbot tự thêm cron)
sudo certbot renew --dry-run
```

---

## 9. Triển khai lần đầu

### Checklist

```
□ Server đã cài đủ packages (Section 3)
□ Redis chạy + có password
□ MySQL databases đã tạo (dashboard_fls, dashboard_pd)
□ MySQL user đã tạo + grant privileges
□ Code đã clone vào /var/www/dashboard-fls và /var/www/dashboard-pd
□ composer install --no-dev đã chạy
□ .env đã config đúng cho cả 2 factory
□ REDIS_PREFIX khác nhau (fls_ vs pd_)
□ php artisan key:generate đã chạy
□ php artisan migrate --force đã chạy
□ php artisan db:seed --force đã chạy
□ php artisan apiato:permissions-sync đã chạy (cả FLS + PD)
□ Passport clients đã tạo (passport:client --password) cho cả FLS + PD
□ CLIENT_WEB_ID / CLIENT_WEB_SECRET đã điền vào .env cả 2 factory
□ php artisan config:cache đã chạy
□ php artisan route:cache đã chạy
□ Permissions storage + bootstrap/cache đã set
□ Supervisor config đã tạo + reread + update
□ Supervisor status: 4 processes RUNNING
□ Crontab đã thêm 2 dòng schedule:run
□ Nginx config đã tạo + enable + test
□ SSL certificate đã cài
□ curl https://fls-api.example.com → response OK
□ curl https://pd-api.example.com → response OK
□ Horizon dashboard accessible: /horizon
```

### Verify

```bash
# 1. Supervisor
sudo supervisorctl status
# Expect: 4 RUNNING processes

# 2. API response
curl -s https://fls-api.example.com | head -5
curl -s https://pd-api.example.com | head -5

# 3. Redis
redis-cli -a YOUR_REDIS_PASSWORD ping
# → PONG

# 4. Horizon
# Browser: https://fls-api.example.com/horizon
# Browser: https://pd-api.example.com/horizon

# 5. Scheduler
sudo crontab -u www-data -l
cd /var/www/dashboard-fls && php artisan schedule:list
cd /var/www/dashboard-pd && php artisan schedule:list

# 6. Queue test
cd /var/www/dashboard-fls
php artisan tinker --execute="dispatch(fn() => logger('Queue FLS working!'));"
tail -f storage/logs/laravel.log
# Expect: "Queue FLS working!" xuất hiện trong log
```

---

## 10. Deploy code mới

### Deploy Script

Lưu tại `/var/www/deploy.sh`:

```bash
#!/bin/bash
# deploy.sh — Deploy both factory instances
# Usage: sudo -u www-data bash /var/www/deploy.sh
set -e

deploy_factory() {
    local FACTORY=$1
    local APP_DIR=$2

    echo ""
    echo "╔══════════════════════════════════════════╗"
    echo "║  Deploying ${FACTORY} (${APP_DIR})"
    echo "╚══════════════════════════════════════════╝"

    cd $APP_DIR

    echo "[${FACTORY}] 1/7 Pull code..."
    git pull origin main

    echo "[${FACTORY}] 2/7 Install dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction

    echo "[${FACTORY}] 3/7 Run migrations..."
    php artisan migrate --force

    echo "[${FACTORY}] 4/7 Sync permissions..."
    php artisan apiato:permissions-sync

    echo "[${FACTORY}] 5/7 Optimize caches..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    echo "[${FACTORY}] 6/7 Reload Octane (graceful)..."
    php artisan octane:reload

    echo "[${FACTORY}] 7/7 Restart Horizon (graceful)..."
    php artisan horizon:terminate

    echo "[${FACTORY}] ✅ Deploy complete!"
}

echo "🚀 Starting deployment..."
echo "   $(date '+%Y-%m-%d %H:%M:%S')"

deploy_factory "FLS" "/var/www/dashboard-fls"
deploy_factory "PD"  "/var/www/dashboard-pd"

echo ""
echo "═══════════════════════════════════════════"
echo "  ✅ All deployments complete!"
echo "  $(date '+%Y-%m-%d %H:%M:%S')"
echo "═══════════════════════════════════════════"

# Verify
echo ""
echo "📊 Supervisor status:"
sudo supervisorctl status
```

```bash
# Set executable
chmod +x /var/www/deploy.sh

# Run
sudo -u www-data bash /var/www/deploy.sh
```

### Deploy flow giải thích

```
git pull
    │
composer install --no-dev --optimize-autoloader
    │   → Autoload class map tối ưu, không có dev packages
    │
migrate --force
    │   → Chạy migrations mới (nếu có)
    │
apiato:permissions-sync
    │   → Sync permissions mới từ container configs vào DB
    │
config:cache + route:cache + view:cache + event:cache
    │   → PHP compiled config → file (không parse .env runtime)
    │
octane:reload
    │   → Graceful: workers xong request hiện tại → reload code mới
    │   → Zero downtime (không cần supervisor restart)
    │
horizon:terminate
    │   → Graceful: workers xong job hiện tại → exit
    │   → Supervisor auto-restart Horizon với code mới
```

> **Zero downtime:** Không cần `supervisorctl restart`. Octane reload + Horizon terminate đều graceful.

---

## 11. Vận hành hàng ngày

### Lệnh thường dùng

```bash
# ── Status ────────────────────────────────────
sudo supervisorctl status                          # Tất cả processes
cd /var/www/dashboard-fls && php artisan horizon:status  # Horizon status

# ── Logs ──────────────────────────────────────
# Application logs
tail -f /var/www/dashboard-fls/storage/logs/laravel.log
tail -f /var/www/dashboard-pd/storage/logs/laravel.log

# Octane logs
tail -f /var/www/dashboard-fls/storage/logs/octane.log

# Horizon logs
tail -f /var/www/dashboard-fls/storage/logs/horizon.log

# Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# ── Restart ───────────────────────────────────
# Octane (graceful — sau code change)
cd /var/www/dashboard-fls && php artisan octane:reload
cd /var/www/dashboard-pd && php artisan octane:reload

# Horizon (graceful — Supervisor auto-restart)
cd /var/www/dashboard-fls && php artisan horizon:terminate
cd /var/www/dashboard-pd && php artisan horizon:terminate

# Full restart (nếu cần)
sudo supervisorctl restart dashboard-fls-octane
sudo supervisorctl restart dashboard-fls-horizon

# ── Queue ─────────────────────────────────────
# Xem failed jobs
cd /var/www/dashboard-fls && php artisan queue:failed

# Retry failed job
php artisan queue:retry <job-id>

# Retry all
php artisan queue:retry all

# Clear cache
php artisan cache:clear

# ── Production Resync ─────────────────────
# Resync hourly records từ FPlatform (chủ động)
php artisan production:resync                                    # Ca hiện tại
php artisan production:resync --date=2026-04-14 --shift=1        # Ngày + ca cụ thể

# Hoặc qua API:
# POST /v1/admin/production/resync?date=2026-04-14&shift=1
```

### Horizon Dashboard

| Factory | URL                                   |
| ------- | ------------------------------------- |
| FLS     | `https://fls-api.example.com/horizon` |
| PD      | `https://pd-api.example.com/horizon`  |

Dashboard hiển thị:

- **Jobs:** Recent, pending, completed, failed
- **Batches:** `sync-hourly:*` — parallel department sync batches
- **Metrics:** Throughput, runtime, wait time (graphs)
- **Workers:** Active processes, balancing status
- **Failed Jobs:** Error details, retry button

> **Quyền truy cập:** Chỉ users có role `admin` mới xem được Horizon dashboard trong production. Config tại `app/Providers/HorizonServiceProvider.php`.

---

## 12. Monitoring & Alerting

### Health Checks

Tạo endpoint health check (hoặc dùng default `/`):

```bash
# Cron health check (mỗi 5 phút)
*/5 * * * * curl -sf https://fls-api.example.com/ > /dev/null || echo "FLS API down!" | mail -s "ALERT" ops@example.com
*/5 * * * * curl -sf https://pd-api.example.com/ > /dev/null || echo "PD API down!" | mail -s "ALERT" ops@example.com
```

### Disk Space

```bash
# Alert khi disk > 85%
0 */6 * * * df -h / | awk 'NR==2{if(int($5)>85) print "Disk usage: "$5}' | mail -s "DISK ALERT" ops@example.com
```

### Redis Memory

```bash
# Check Redis memory usage
redis-cli -a YOUR_REDIS_PASSWORD info memory | grep used_memory_human
```

### Log Rotation

Laravel daily logs tự rotate. Thêm Nginx log rotation:

```bash
sudo nano /etc/logrotate.d/dashboard-factory
```

```
/var/www/dashboard-*/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
}
```

---

## 13. Backup & Recovery

### Database Backup

```bash
# Daily backup script — /var/www/backup-db.sh
#!/bin/bash
BACKUP_DIR="/var/backups/dashboard"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# FLS
mysqldump -u dashboard_user -pSTRONG_DB_PASSWORD dashboard_fls | gzip > "$BACKUP_DIR/fls_${DATE}.sql.gz"

# PD
mysqldump -u dashboard_user -pSTRONG_DB_PASSWORD dashboard_pd | gzip > "$BACKUP_DIR/pd_${DATE}.sql.gz"

# Clean backups older than 30 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "[$(date)] Backup done: fls_${DATE}.sql.gz, pd_${DATE}.sql.gz"
```

```bash
chmod +x /var/www/backup-db.sh

# Cron: 2AM hàng ngày
echo "0 2 * * * root /var/www/backup-db.sh >> /var/log/dashboard-backup.log 2>&1" | sudo tee /etc/cron.d/dashboard-backup
```

### Recovery

```bash
# Restore FLS database
gunzip < /var/backups/dashboard/fls_20260410_020000.sql.gz | mysql -u root -p dashboard_fls

# Restart services
sudo supervisorctl restart dashboard-fls-octane dashboard-fls-horizon
```

---

## 14. Troubleshooting

### Bảng lỗi thường gặp

| Triệu chứng              | Nguyên nhân                 | Giải pháp                                        |
| ------------------------ | --------------------------- | ------------------------------------------------ |
| `FATAL: no such process` | Supervisor config chưa load | `supervisorctl reread && update`                 |
| Octane crash loop        | PHP error / OOM             | `tail -f storage/logs/octane.log`                |
| Horizon not running      | Redis connection refused    | `systemctl status redis-server`                  |
| Job không chạy           | `QUEUE_CONNECTION=sync`     | Đổi sang `redis` trong `.env` + `config:cache`   |
| Permission denied        | Sai user/permission         | `chown -R www-data:www-data storage`             |
| Redis connection refused | Redis chưa chạy             | `systemctl start redis-server`                   |
| Code mới không phản ánh  | Octane giữ code in-memory   | `php artisan octane:reload`                      |
| Config mới không apply   | Config đã cache             | `php artisan config:cache` (rebuild)             |
| Memory tăng liên tục     | Leaky singleton/static      | Thêm class vào `flush` trong `config/octane.php` |
| Queue FLS nhận job PD    | `REDIS_PREFIX` trùng        | Đảm bảo prefix khác nhau giữa 2 factory          |
| Horizon dashboard 403    | User không có role admin    | Kiểm tra `HorizonServiceProvider::gate()`        |
| SSL certificate expired  | Certbot renewal fail        | `sudo certbot renew`                             |
| Scheduler không chạy     | Crontab thiếu               | `sudo crontab -u www-data -l`                    |

### Debug checklist

```bash
# 1. Supervisor processes
sudo supervisorctl status

# 2. Octane responding?
curl http://127.0.0.1:8000
curl http://127.0.0.1:8001

# 3. Redis connected?
redis-cli -a YOUR_REDIS_PASSWORD ping

# 4. App logs
tail -50 /var/www/dashboard-fls/storage/logs/laravel.log

# 5. Horizon status
cd /var/www/dashboard-fls && php artisan horizon:status

# 6. Scheduler
cd /var/www/dashboard-fls && php artisan schedule:list

# 7. Queue failed jobs
cd /var/www/dashboard-fls && php artisan queue:failed

# 8. Disk space
df -h

# 9. Memory
free -h

# 10. PHP processes
ps aux | grep php | grep -v grep
```

---

## 15. Phụ lục

### A. Scaling Guide

| Khi nào              | Hành động                                                   |
| -------------------- | ----------------------------------------------------------- |
| API response > 500ms | Tăng Octane workers: `--workers=8` trong supervisor command |
| Queue backlog tăng   | Tăng `maxProcesses` trong `config/horizon.php` → deploy     |
| Redis memory > 200MB | Tăng `maxmemory` trong `/etc/redis/redis.conf`              |
| MySQL queries chậm   | Thêm indexes, check slow query log                          |
| Disk > 85%           | Xóa old logs, tăng disk                                     |

### B. Horizon Worker Tuning

Config tại `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'maxProcesses' => 10,        // Max workers tổng
            'balanceMaxShift' => 1,      // Thêm/bớt 1 worker mỗi lần
            'balanceCooldown' => 3,      // Chờ 3s giữa mỗi lần scale
        ],
    ],
],
```

| Param                 | Default | Mô tả                                       |
| --------------------- | ------- | ------------------------------------------- |
| `maxProcesses`        | `5`     | Giới hạn tổng số workers                    |
| `balance`             | `auto`  | Auto-scale dựa trên queue backlog           |
| `autoScalingStrategy` | `time`  | Scale theo wait time (không phải job count) |
| `tries`               | `3`     | Retry job 3 lần trước khi mark failed       |
| `timeout`             | `120`   | Kill job sau 120s                           |

### C. File quan trọng

| File                                                                             | Mô tả                                                 |
| -------------------------------------------------------------------------------- | ----------------------------------------------------- |
| `config/horizon.php`                                                             | Queue worker config (processes, queues, retry)        |
| `config/octane.php`                                                              | Octane warm/flush config (singleton management)       |
| `config/database.php`                                                            | Redis connection + prefix config                      |
| `app/Providers/HorizonServiceProvider.php`                                       | Horizon dashboard authorization                       |
| `app/Providers/TelescopeServiceProvider.php`                                     | Telescope debug dashboard authorization               |
| `app/Containers/AppSection/Authentication/Configs/appSection-authentication.php` | Passport OAuth client config (`CLIENT_WEB_ID/SECRET`) |
| `app/Containers/AppSection/Shift/Providers/ShiftServiceProvider.php`             | Scheduled jobs registration                           |

### D. Useful Artisan Commands

```bash
# Config
php artisan config:cache       # Cache .env + config files
php artisan config:clear       # Clear cached config
php artisan route:cache        # Cache routes
php artisan view:cache         # Pre-compile Blade templates
php artisan event:cache        # Cache event-listener mapping

# Octane
php artisan octane:status      # Check if Octane is running
php artisan octane:reload      # Graceful reload workers
php artisan octane:stop        # Stop Octane server

# Horizon
php artisan horizon:status     # Check if Horizon is running
php artisan horizon:terminate  # Graceful terminate (Supervisor restarts)
php artisan horizon:pause      # Pause processing (jobs queue up)
php artisan horizon:continue   # Resume processing

# Queue
php artisan queue:failed       # List failed jobs
php artisan queue:retry all    # Retry all failed jobs
php artisan queue:flush        # Delete all failed jobs

# Database
php artisan migrate --force    # Run pending migrations
php artisan migrate:status     # Check migration status
```
