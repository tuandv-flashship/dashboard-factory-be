# Dashboard Factory API

Backend API cho Dashboard giám sát sản xuất realtime — hỗ trợ multi-factory (FlashShip + PrintDash) trên cùng một codebase.

**Stack**: Laravel Apiato (Porto SAP) + Passport + Reverb (WebSocket)

## Architecture

Mỗi factory chạy trên một process riêng biệt, sử dụng file `.env` riêng:

| Factory    | Env file   | DB                    | Port | HTTPS Domain                           |
|------------|------------|-----------------------|------|----------------------------------------|
| FlashShip  | `.env.fls` | `dashboard_fls_local` | 8000 | `https://api-dashboard-fls.local:2443` |
| PrintDash  | `.env.pd`  | `dashboard_pd_local`  | 8001 | `https://api-dashboard-pd.local:2443`  |

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
serve-https    # Start cả 2 Laravel servers + Caddy HTTPS proxy
```

## Factory Helper Commands

Load helpers: `source scripts/factory-env.sh`

| Command       | Description                                    |
|---------------|------------------------------------------------|
| `fls <cmd>`   | Chạy command dưới context FlashShip            |
| `pd <cmd>`    | Chạy command dưới context PrintDash            |
| `serve-all`   | Start cả 2 servers (HTTP only, port 8000+8001) |
| `serve-https` | Start servers + Caddy HTTPS proxy              |
| `proxy-start` | Chỉ start Caddy proxy                          |
| `proxy-stop`  | Stop Caddy proxy                               |
| `migrate-all` | Migrate cả 2 DB (non-destructive)              |
| `seed-all`    | Seed cả 2 DB (non-destructive)                 |
| `fresh-all`   | ⚠️ Drop + recreate + seed cả 2 DB              |

### Examples

```bash
fls artisan tinker              # Tinker với DB FlashShip
pd artisan migrate:status       # Xem migration status của PrintDash
fls artisan queue:work          # Queue worker cho FlashShip
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
