# Dashboard Factory — Local Development Setup

> Hướng dẫn cài đặt và phát triển local cho cả 2 factory: **FlashShip (FLS)** và **PrintDash (PD)**.

## Yêu cầu hệ thống

| Tool | Version | Ghi chú |
|------|---------|---------|
| PHP | ≥ 8.2 | `php -v` |
| Composer | ≥ 2.x | `composer -V` |
| MySQL | ≥ 8.0 | MAMP hoặc native |
| Node.js | ≥ 18 | (nếu chạy FE) |

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
| `serve-all` | Start 2 server HTTP (`:8000` + `:8001`) | ❌ |
| `serve-https` | Start 2 server + Caddy HTTPS proxy | ❌ |
| `proxy-start` | Chỉ start Caddy HTTPS proxy | ❌ |
| `proxy-stop` | Dừng Caddy HTTPS proxy | ❌ |
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

### HTTP only (đơn giản)

```bash
serve-all
```

```
  🏭 Starting both factory instances...
  ┌──────────────────────────────────────────────┐
  │  FLS (FlashShip)  → http://localhost:8000    │
  │  PD  (PrintDash)  → http://localhost:8001    │
  └──────────────────────────────────────────────┘
```

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

### Dừng server

Nhấn `Ctrl+C` — tất cả instance dừng cùng lúc.

---

## 6. Cài đặt HTTPS Local (Caddy)

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

Caddy **rewrite `Host` header** trước khi forward request đến Laravel:

```
Browser → https://api-dashboard-fls.local:2443
              ↓  Caddy: header_up Host→ api-dashboard-factory.flashtech.local
Laravel  ← Host: api-dashboard-factory.flashtech.local  ← khớp API_URL trong .env
              ↓
         Routes resolve → 200 OK
```

Nhờ đó **không cần sửa `.env`** hay bất kỳ config nào khi chuyển từ domain cũ sang domain mới.

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

## 7. Chạy lệnh riêng cho từng factory

```bash
# Chạy artisan cho FLS
fls artisan tinker

# Chạy test cho PD
pd artisan test

# Xem factory info
fls artisan factory:info
pd artisan factory:info
```

---

## 8. Cấu trúc Factory

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

## 9. Troubleshooting

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
lsof -ti :8000 | xargs kill -9
lsof -ti :8001 | xargs kill -9
```

---

## 10. File liên quan

| File | Mô tả |
|------|--------|
| `scripts/factory-env.sh` | Helper script (source vào shell) |
| `Caddyfile` | Caddy HTTPS proxy config |
| `bin/caddy` | Caddy binary (gitignored) |
| `config/factory.php` | Factory config (`FACTORY` env) |
| `docs/api-factory-decoupling.md` | API documentation cho FE |
| `postman/DashboardFactory.postman_collection.json` | Postman collection |
