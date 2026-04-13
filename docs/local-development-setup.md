# Dashboard Factory — Local Development Setup

> Hướng dẫn cài đặt và phát triển local cho cả 2 factory: **FlashShip (FLS)** và **PrintDash (PD)**.

## Yêu cầu hệ thống

| Tool | Version | Ghi chú |
|------|---------|---------|
| PHP | ≥ 8.4 | `php -v` (khuyên dùng Homebrew: `brew install php@8.5`) |
| Composer | ≥ 2.x | `composer -V` |
| MySQL | ≥ 8.0 | MAMP hoặc native |
| Redis | ≥ 7.x | `brew install redis && brew services start redis` |
| Node.js | ≥ 18 | Cần cho `--watch` mode |

---

## 1. Clone & Install

```bash
git clone <repo-url> dashboard-factory
cd dashboard-factory/dashboard-be

composer install
cp .env.example .env
php artisan key:generate
```

---

## 2. Cấu hình Database

Hệ thống dùng **2 database riêng biệt**, mỗi factory 1 database:

| Factory | Database | Port (MAMP) |
|---------|----------|:-----------:|
| FLS (FlashShip) | `dashboard_fls_local` | 8889 |
| PD (PrintDash) | `dashboard_pd_local` | 8889 |

### Cập nhật `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=dashboard_fls_local
DB_USERNAME=root
DB_PASSWORD=root

FACTORY=FLS
```

> ⚠️ **Không cần sửa `.env` khi chuyển factory** — dùng helper script bên dưới.

---

## 3. Factory Helper Script

Source file helper để có đầy đủ các lệnh:

```bash
source scripts/factory-env.sh
```

> ✅ **Tự động load mỗi khi mở terminal** — đã được thêm vào `~/.zshrc`:
> ```bash
> # Dashboard Factory helpers
> source ~/Data/FlashShip/dashboard-factory/dashboard-be/scripts/factory-env.sh
> ```
> Các lệnh `fls`, `pd`, `serve-all`, `serve-https`... sẽ luôn sẵn có trong mọi terminal session.

### Các lệnh có sẵn

| Lệnh | Mô tả | Mất data? |
|------|--------|:---------:|
| `fls <cmd>` | Chạy lệnh với context FlashShip | — |
| `pd <cmd>` | Chạy lệnh với context PrintDash | — |
| `serve-all` | Start Octane + Horizon cho cả 2 factory | ❌ |
| `serve-watch` | Start Octane + Horizon + auto-reload khi code đổi | ❌ |
| `serve-https` | Start Octane + Horizon + Caddy HTTPS proxy | ❌ |
| `serve-https-watch` | Start Octane + Horizon + Caddy HTTPS + auto-reload | ❌ |
| `migrate-all` | Chạy migrations mới (pending) cho cả 2 DB | ❌ |
| `seed-all` | Chạy seeders (skip nếu data đã có) | ❌ |
| `fresh-all` | ⚠️ Drop + recreate + seed cả 2 DB | ✅ Yes |

---

## 4. Tạo Database & Seed dữ liệu

### Lần đầu setup (hoặc reset toàn bộ):

```bash
source scripts/factory-env.sh
fresh-all
```

Script sẽ tự động:
1. Tạo database nếu chưa có (`CREATE DATABASE IF NOT EXISTS`)
2. Chạy `migrate:fresh --seed` cho cả FLS và PD
3. Hỏi xác nhận trước khi thực hiện

### Chạy migration mới (hàng ngày):

```bash
migrate-all
```

### Chạy seeders bổ sung:

```bash
seed-all
```

---

## 5. Chạy server

Hệ thống dùng **Laravel Octane** với **FrankenPHP** — app được boot 1 lần và giữ in-memory, giúp response time nhanh hơn ~5-10x so với `artisan serve` truyền thống.

### HTTP only (đơn giản)

```bash
serve-all
```

```
  🏭 Starting both factory instances (Octane FrankenPHP)...
  ┌──────────────────────────────────────────────┐
  │  FLS (FlashShip)  → http://localhost:8000    │
  │  PD  (PrintDash)  → http://localhost:8001    │
  └──────────────────────────────────────────────┘
```

### HTTP + Auto-reload khi code thay đổi

```bash
serve-watch
```

> Cần cài `chokidar` (1 lần): `npm install --save-dev chokidar`

### HTTPS (production-like)

Cần cài thêm **Caddy** (1 lần) — xem [Section 6](#6-cài-đặt-https-local-caddy).

```bash
serve-https
```

```
  🔒 Starting Caddy HTTPS proxy...
  ┌────────────────────────────────────────────────────────┐
  │  FLS  → https://api-dashboard-fls.local:2443                │
  │  PD   → https://api-dashboard-pd.local:2443                 │
  └────────────────────────────────────────────────────────┘
```

### HTTPS + Auto-reload (khuyên dùng khi dev với FE)

```bash
serve-https-watch
```

Kết hợp Caddy HTTPS proxy + auto-reload khi code thay đổi. Ideal khi chạy FE local cần gọi API qua HTTPS.

### Dừng server

Nhấn `Ctrl+C` — tất cả instance dừng cùng lúc.

### Octane utility commands

```bash
# Xem trạng thái server
fls artisan octane:status

# Reload workers (sau khi sửa config, .env)
fls artisan octane:reload

# Dừng Octane server cho factory
fls artisan octane:stop
```

### Queue Dashboard (Horizon)

Horizon **tự động start** cùng với `serve-all` / `serve-watch` / `serve-https`. Dashboard truy cập tại:
- **FLS:** `http://localhost:8000/horizon`
- **PD:** `http://localhost:8001/horizon`

> Horizon tự quản lý queue workers — không cần chạy `queue:work` riêng.

---

## 6. Redis (Queue + Cache)

Redis được dùng cho **queue** (Horizon) và **cache** (thay MySQL).

### Cài đặt (1 lần)

```bash
brew install redis
brew services start redis    # Auto-start khi boot

# Verify
redis-cli ping   # → PONG
```

### Kiểm tra config

```bash
fls artisan tinker --execute="echo app('redis')->ping();"
# → PONG
```

> `.env.fls`/`.env.pd` đã config sẵn: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_PREFIX=fls_`/`pd_` để isolate data giữa 2 factory.

---

## 7. Cài đặt HTTPS Local (Caddy)

### 6.1 Cài Caddy binary

**Option A — Homebrew:**
```bash
brew install caddy
```

**Option B — Download trực tiếp (Apple Silicon):**
```bash
mkdir -p bin
curl -L https://github.com/caddyserver/caddy/releases/download/v2.11.2/caddy_2.11.2_mac_arm64.tar.gz | tar xz -C bin/
```

> Binary được lưu tại `bin/caddy` (đã thêm vào `.gitignore`).

### 6.2 Thêm domain vào `/etc/hosts` (1 lần)

```bash
sudo sh -c 'echo "127.0.0.1  api-dashboard-fls.local api-dashboard-pd.local" >> /etc/hosts'
```

Kiểm tra:
```bash
cat /etc/hosts | grep dashboard
# 127.0.0.1  api-dashboard-fls.local api-dashboard-pd.local
```

### 6.3 Trust Caddy CA certificate (1 lần)

Caddy cần đang chạy để trust CA. Chạy lần đầu:

```bash
./bin/caddy run --config Caddyfile
```

> Lần đầu chạy sẽ hiện prompt hỏi password macOS để thêm CA vào Keychain. Nhập password → xong → `Ctrl+C` dừng lại.

Sau đó dùng `serve-https` bình thường — không hỏi password nữa.

### 6.4 Giải thích hoạt động

```
Browser → https://api-dashboard-fls.local:2443
              ↓  Caddy: reverse_proxy → localhost:8000
Octane   ← Host: api-dashboard-fls.local  ← FrankenPHP process (in-memory)
              ↓
         Routes resolve → 200 OK (~50ms)
```

Caddy proxy tới Octane FrankenPHP server đang chạy trên port 8000/8001.

### 6.5 Kiểm tra

```bash
source scripts/factory-env.sh
serve-https
```

Mở browser:
- `https://api-dashboard-fls.local:2443` → FlashShip API
- `https://api-dashboard-pd.local:2443` → PrintDash API

> 🔒 Có icon khóa xanh = thành công!

---

## 8. Chạy lệnh riêng cho từng factory

```bash
# Chạy artisan cho FLS
fls artisan tinker

# Chạy test cho PD
pd artisan test

# Xem factory info
fls artisan factory:info
pd artisan factory:info

# Octane status
fls artisan octane:status
```

---

## 9. Cấu trúc Factory

### FLS (FlashShip) — 1 line, 5 departments

```
DTF
 ├── Print       (code: print)
 ├── Pick        (code: pick)
 ├── Cut         (code: cut)
 ├── Mock Up     (code: mockup)
 └── Pack & Ship (code: pack_ship)
```

### PD (PrintDash) — 3 lines, 9 departments

```
DTF
 ├── Print       (code: print)
 ├── Pick        (code: pick)
 ├── Cut         (code: cut)
 └── Mock Up     (code: mockup)

DTG
 ├── Pick DTG    (code: pick_dtg)           ← standalone
 ├── Apollo      (code: apollo)    ┐
 ├── Atlas-01    (code: atlas_01)  ├─ group: "dtg_print"
 └── Atlas-02    (code: atlas_02)  ┘

Pack & Ship
 └── Pack & Ship (code: pack_ship)
```

---

## 10. Troubleshooting

### "Table 'cache' doesn't exist"
Database chưa migrate. Chạy:
```bash
fresh-all
```

### "command not found: serve-all"
Chưa source helper script:
```bash
source scripts/factory-env.sh
```

### "Could not create database"
Kiểm tra MySQL credentials trong `scripts/factory-env.sh`:
```bash
LOCAL_DB_USER="root"
LOCAL_DB_PASS="root"
LOCAL_DB_PORT=8889    # MAMP default
```

### HTTPS báo "Not Secure"
Chưa trust Caddy CA:
```bash
./bin/caddy trust
```

### Port 8000/8001 đã bị chiếm
```bash
# Stop Octane servers
fls artisan octane:stop
pd artisan octane:stop

# Hoặc kill trực tiếp
lsof -ti :8000 | xargs kill -9
lsof -ti :8001 | xargs kill -9
```

### Octane không phản ánh code mới
App đang giữ in-memory — cần reload:
```bash
fls artisan octane:reload
pd artisan octane:reload
```

Hoặc dùng `serve-watch` để tự động reload khi code thay đổi.

### Memory tăng dần theo thời gian
Octane tự restart worker sau 500 requests (default). Nếu vẫn leak, kiểm tra static properties:
```bash
# Điều chỉnh max requests
php artisan octane:start --max-requests=250
```

---

## 11. File liên quan

| File | Mô tả |
|------|--------|
| `scripts/factory-env.sh` | Helper script (source vào shell) |
| `Caddyfile` | Caddy HTTPS proxy config |
| `config/octane.php` | Octane server config (FrankenPHP, listeners, flush bindings) |
| `bin/caddy` | Caddy binary (gitignored) |
| `frankenphp` | FrankenPHP binary (gitignored, auto-downloaded by Octane) |
| `config/factory.php` | Factory config (`FACTORY` env) |
| `docs/api-factory-decoupling.md` | API documentation cho FE |
| `docs/production-deployment.md` | Production deployment guide |
| `postman/DashboardFactory.postman_collection.json` | Postman collection |
