# Supervisor Setup — Production Guide

Hướng dẫn cài đặt và cấu hình Supervisor để chạy Laravel Queue Worker và Scheduler trên môi trường **Production / Staging / Dev** (Ubuntu/Debian).

## 1. Yêu cầu hệ thống

### Packages cần thiết

```bash
sudo apt-get update
sudo apt-get install -y supervisor redis-server php-redis
sudo systemctl enable supervisor redis-server
```

### .env (Production)

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Permissions

```bash
sudo chown -R www-data:www-data /var/www/dashboard-factory/storage
sudo chmod -R 775 /var/www/dashboard-factory/storage
```

---

## 2. Cấu hình Supervisor

### Tạo config file

```bash
sudo nano /etc/supervisor/conf.d/dashboard-factory.conf
```

### Nội dung config

```ini
;------------------------------------------------------------
; Dashboard Factory — Queue Worker
;------------------------------------------------------------
[program:dashboard-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/dashboard-factory/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/dashboard-factory
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/dashboard-factory/storage/logs/queue-worker.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=3600
environment=APP_ENV="production"
```

> **Note:** Supervisor chỉ quản lý Queue Worker (long-running process). Scheduler được chạy qua Crontab — xem [Section 3](#3-crontab-scheduler).

### Giải thích tham số

| Tham số | Giá trị | Ý nghĩa |
|---|---|---|
| `command` | `queue:work redis` | Queue driver dùng Redis |
| `numprocs` | `2` | 2 worker song song (tăng khi tải cao) |
| `--sleep=3` | | Nghỉ 3s nếu không có job trong queue |
| `--tries=3` | | Retry tối đa 3 lần nếu job fail |
| `--max-time=3600` | | Tự restart worker sau 1h (tránh memory leak) |
| `user` | `www-data` | Chạy dưới quyền web server |
| `stopasgroup` | `true` | Kill cả child processes khi stop |
| `stopwaitsecs` | `3600` | Đợi job hiện tại xong trước khi kill |
| `stdout_logfile_maxbytes` | `10MB` | Tự rotate log khi đầy |

### Điều chỉnh theo môi trường

| Config | Dev | Staging | Production |
|---|---|---|---|
| `numprocs` (queue) | `1` | `1` | `2–4` |
| Queue driver | `database` | `redis` | `redis` |
| `APP_ENV` | `local` | `staging` | `production` |

---

## 3. Crontab Scheduler

Laravel [chính thức khuyến nghị](https://laravel.com/docs/scheduling#running-the-scheduler) dùng Crontab để chạy Scheduler.

```bash
sudo crontab -u www-data -e
```

Thêm dòng:

```cron
* * * * * cd /var/www/dashboard-factory && php artisan schedule:run >> /dev/null 2>&1
```

### Tại sao Crontab thay vì Supervisor?

| Tiêu chí | Crontab ✅ | Supervisor ❌ |
|---|---|---|
| **Timing** | OS kernel trigger chính xác mỗi phút | `sleep 60` bị drift theo thời gian chạy lệnh |
| **Tài nguyên** | Process spawn khi cần, chạy xong tự exit | Giữ process idle 24/7 |
| **Best practice** | Laravel docs chính thức recommend | Workaround bằng `while true` loop |
| **Độ tin cậy** | Cron đã battle-tested hàng chục năm trên Unix | Hoạt động tốt nhưng không phải đúng use case |

> **Quy tắc:** Supervisor cho **long-running processes** (queue worker). Crontab cho **periodic short tasks** (scheduler).

### Kiểm tra crontab đã hoạt động

```bash
# Xem crontab hiện tại
sudo crontab -u www-data -l

# Kiểm tra schedule list
php artisan schedule:list
```

---

## 4. Khởi động Supervisor

```bash
# Đọc config mới
sudo supervisorctl reread

# Cập nhật processes
sudo supervisorctl update

# Khởi động queue workers
sudo supervisorctl start dashboard-queue:*
```

---

## 5. Lệnh quản lý

```bash
# Xem trạng thái Supervisor
sudo supervisorctl status
# dashboard-queue:dashboard-queue_00   RUNNING   pid 12345, uptime 2:30:00
# dashboard-queue:dashboard-queue_01   RUNNING   pid 12346, uptime 2:30:00

# Restart queue (sau deploy code mới)
sudo supervisorctl restart dashboard-queue:*

# Stop / Start queue workers
sudo supervisorctl stop dashboard-queue:*
sudo supervisorctl start dashboard-queue:*

# Xem log realtime
sudo tail -f /var/www/dashboard-factory/storage/logs/queue-worker.log
```

---

## 6. Deploy Script

```bash
#!/bin/bash
# deploy.sh
set -e

APP_DIR="/var/www/dashboard-factory"
cd $APP_DIR

echo "=== Pull code ==="
git pull origin main

echo "=== Install dependencies ==="
composer install --no-dev --optimize-autoloader

echo "=== Run migrations ==="
php artisan migrate --force

echo "=== Clear caches ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== Restart queue (graceful) ==="
php artisan queue:restart
# Dùng queue:restart thay vì supervisorctl restart
# để đợi job hiện tại xong mới restart (zero-downtime)

echo "=== Done ==="
```

> **Lưu ý:** `php artisan queue:restart` là graceful — worker đợi job đang chạy xong rồi mới tự restart. Không cần `supervisorctl restart`. Crontab scheduler tự động pick up code mới ở lần chạy tiếp theo.

---

## 7. Troubleshooting

| Lỗi | Nguyên nhân | Giải pháp |
|---|---|---|
| `FATAL: no such process` | Config chưa load | `supervisorctl reread && update` |
| Worker restart liên tục | PHP error / OOM | Check `storage/logs/queue-worker.log` |
| Job không chạy | `QUEUE_CONNECTION=sync` | Đổi sang `redis` trong `.env` |
| Permission denied | Sai user/permission | `chown -R www-data:www-data storage` |
| Redis connection refused | Redis chưa chạy | `systemctl start redis-server` |
| Scheduler không chạy | Crontab chưa thêm | `sudo crontab -u www-data -l` để kiểm tra |

### Kiểm tra queue hoạt động

```bash
php artisan tinker
>>> dispatch(new \App\Containers\AppSection\Shift\Jobs\ActivateHourlyRecordsJob());
# Xem log: tail -f storage/logs/queue-worker.log
```

### Kiểm tra scheduler

```bash
php artisan schedule:list
# Output: ActivateHourlyRecordsJob .... Every hour
```

---

## 8. Jobs hiện tại

| Job | Schedule | Mô tả |
|---|---|---|
| `ActivateHourlyRecordsJob` | `->hourly()` (mỗi giờ tại :00) | Chuyển status: pending→active→completed |

Đăng ký tại `ShiftServiceProvider`:

```php
$schedule->job(new ActivateHourlyRecordsJob())->hourly();
```

---

## 9. Architecture Overview

```
┌─────────────────────────────────────┐
│  Supervisor                         │
│  └── dashboard-queue (2 workers)    │  ← Long-running, cần auto-restart
├─────────────────────────────────────┤
│  Crontab                            │
│  └── * * * * * schedule:run         │  ← Short-lived, chạy mỗi phút
└─────────────────────────────────────┘
```

---

## 10. Alternative: Dùng Supervisor cho Scheduler

Nếu muốn quản lý tập trung mọi thứ qua Supervisor (không dùng crontab), thêm block sau vào `/etc/supervisor/conf.d/dashboard-factory.conf`:

```ini
;------------------------------------------------------------
; Dashboard Factory — Scheduler (alternative, thay crontab)
;------------------------------------------------------------
[program:dashboard-scheduler]
process_name=%(program_name)s
command=/bin/bash -c "while true; do php /var/www/dashboard-factory/artisan schedule:run --no-interaction >> /dev/null 2>&1; sleep 60; done"
directory=/var/www/dashboard-factory
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dashboard-factory/storage/logs/scheduler.log
stdout_logfile_maxbytes=5MB
environment=APP_ENV="production"
```

> **⚠️ Lưu ý:** Cách này hoạt động nhưng **không phải best practice**. `sleep 60` có thể bị drift và giữ process idle 24/7. Chỉ dùng khi không có quyền truy cập crontab hoặc muốn quản lý tập trung tuyệt đối.
