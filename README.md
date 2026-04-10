# Dashboard Factory API

Backend API cho Dashboard giám sát sản xuất realtime — hỗ trợ multi-factory (FlashShip + PrintDash) trên cùng một codebase.

**Stack**: Laravel Apiato (Porto SAP) + Octane (FrankenPHP) + Passport + Reverb (WebSocket)

## Architecture

Mỗi factory chạy trên một **Octane process** riêng biệt, sử dụng file `.env` riêng:

| Factory    | Env file   | DB                    | Port | HTTPS Domain                           |
|------------|------------|-----------------------|------|----------------------------------------|
| FlashShip  | `.env.fls` | `dashboard_fls_local` | 8000 | `https://api-dashboard-fls.local:2443` |
| PrintDash  | `.env.pd`  | `dashboard_pd_local`  | 8001 | `https://api-dashboard-pd.local:2443`  |

> **Octane** giữ app in-memory, không bootstrap mỗi request → response time ~50ms (thay vì ~200ms với PHP-FPM/artisan serve).

Caddy reverse proxy cung cấp HTTPS local cho cả 2 domain.

## Get Started

### 1. Install dependencies

```bash
composer install
```

### 2. Setup environment files

```bash
cp .env.fls.example .env.fls
cp .env.pd.example .env.pd
php artisan key:generate    # Copy APP_KEY vào cả 2 file .env.fls và .env.pd
```

### 3. Add local domains to /etc/hosts

```bash
sudo sh -c 'echo "127.0.0.1  api-dashboard-fls.local api-dashboard-pd.local" >> /etc/hosts'
```

### 4. Load factory helpers

```bash
source scripts/factory-env.sh
```

### 5. Setup databases

```bash
fresh-all    # Tạo DB, migrate, seed cho cả 2 factory
```

### 6. Create Passport clients

```bash
fls artisan passport:client --password --name="FlashShip Password Grant Client" --provider="users"
# → Điền CLIENT_WEB_ID + CLIENT_WEB_SECRET vào .env.fls

pd artisan passport:client --password --name="PrintDash Password Grant Client" --provider="users"
# → Điền CLIENT_WEB_ID + CLIENT_WEB_SECRET vào .env.pd
```

### 7. Start development servers

```bash
serve-https    # Start cả 2 Octane servers + Caddy HTTPS proxy
```

Hoặc với auto-reload khi thay đổi code:
```bash
serve-watch    # Start cả 2 Octane servers với file watching
```

> **Lưu ý:** `serve-watch` cần `chokidar` — cài bằng: `npm install --save-dev chokidar`

## Factory Helper Commands

Load helpers: `source scripts/factory-env.sh`

| Command       | Description                                     |
|---------------|-------------------------------------------------|
| `fls <cmd>`   | Chạy command dưới context FlashShip             |
| `pd <cmd>`    | Chạy command dưới context PrintDash             |
| `serve-all`   | Start Octane + Horizon cho cả 2 factory          |
| `serve-watch` | Start Octane + Horizon + auto-reload              |
| `serve-https` | Start Octane + Horizon + Caddy HTTPS              |
| `migrate-all` | Migrate cả 2 DB (non-destructive)               |
| `seed-all`    | Seed cả 2 DB (non-destructive)                  |
| `fresh-all`   | ⚠️ Drop + recreate + seed cả 2 DB               |

### Examples

```bash
fls artisan tinker              # Tinker với DB FlashShip
pd artisan migrate:status       # Xem migration status của PrintDash
fls artisan horizon             # Horizon queue dashboard cho FlashShip
fls artisan octane:status       # Kiểm tra Octane server status
```

## API Testing

```bash
# Login FlashShip
curl -k -X POST 'https://api-dashboard-fls.local:2443/v1/clients/web/login' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'email=admin@admin.com&password=admin'

# Login PrintDash
curl -k -X POST 'https://api-dashboard-pd.local:2443/v1/clients/web/login' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'email=admin@admin.com&password=admin'
```

## WebSocket

```bash
php artisan reverb:start   # ws://localhost:8080
```
