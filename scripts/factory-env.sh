#!/bin/bash
#
# Factory Switch Helper
# Source this file in your shell: source scripts/factory-env.sh
#
# Each factory uses its own .env file:
#   FLS → .env.fls   (FlashShip)
#   PD  → .env.pd    (PrintDash)
#
# Commands:
#   fls <command>        Run any command as FlashShip
#   pd  <command>        Run any command as PrintDash
#   serve-all            Start both servers (port 8000 + 8001)
#   serve-https          Start both servers + Caddy HTTPS proxy
#   proxy-start          Start only the Caddy HTTPS proxy
#   proxy-stop           Stop the Caddy HTTPS proxy
#   migrate-all          Run pending migrations on both DBs (safe)
#   seed-all             Run only seeders on both DBs (safe, skips existing)
#   fresh-all            ⚠️  Drop + recreate + seed both DBs (destructive)

# ── Configuration ──────────────────────────────────────
FLS_PORT=8000
PD_PORT=8001
LOCAL_DB_USER="root"
LOCAL_DB_PASS="root"
LOCAL_DB_PORT=8889
CADDY_BIN="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && cd .. && pwd)/bin/caddy"
CADDYFILE="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && cd .. && pwd)/Caddyfile"
FLS_HTTPS="https://api-dashboard-fls.local:2443"
PD_HTTPS="https://api-dashboard-pd.local:2443"

# ── Core helpers ───────────────────────────────────────
# APP_ENV tells Laravel to load .env.fls or .env.pd instead of .env

fls() {
    APP_ENV=fls "$@"
}

pd() {
    APP_ENV=pd "$@"
}

artisan() {
    php artisan "$@"
}

# ── Serve ──────────────────────────────────────────────

_kill_stale_servers() {
    local stale_pids
    stale_pids=$(pgrep -f 'artisan serve' 2>/dev/null)
    if [ -n "$stale_pids" ]; then
        echo "  🧹 Killing stale artisan serve processes..."
        echo "$stale_pids" | xargs kill 2>/dev/null
        sleep 1
    fi
}

serve-all() {
    _kill_stale_servers
    echo ""
    echo "  🏭 Starting both factory instances..."
    echo "  ┌──────────────────────────────────────────────┐"
    echo "  │  FLS (FlashShip)  → http://localhost:$FLS_PORT  │"
    echo "  │  PD  (PrintDash)  → http://localhost:$PD_PORT  │"
    echo "  └──────────────────────────────────────────────┘"
    echo "  Press Ctrl+C to stop both."
    echo ""

    APP_ENV=fls php artisan serve --port=$FLS_PORT > >(sed 's/^/  [FLS] /') 2>&1 &
    local fls_pid=$!

    APP_ENV=pd php artisan serve --port=$PD_PORT > >(sed 's/^/  [PD]  /') 2>&1 &
    local pd_pid=$!

    trap "echo ''; echo '  🛑 Stopping...'; kill $fls_pid $pd_pid 2>/dev/null; trap - INT; return" INT
    wait $fls_pid $pd_pid
}

# ── HTTPS Proxy (Caddy) ────────────────────────────────

proxy-start() {
    if ! [ -f "$CADDY_BIN" ]; then
        echo "  ❌ Caddy not found at $CADDY_BIN"
        echo "  Run: curl -L https://github.com/caddyserver/caddy/releases/download/v2.11.2/caddy_2.11.2_mac_arm64.tar.gz | tar xz -C bin/"
        return 1
    fi

    # Check /etc/hosts
    if ! grep -q 'api-dashboard-fls.local' /etc/hosts 2>/dev/null; then
        echo "  ⚠️  Add to /etc/hosts (requires sudo):"
        echo "     sudo sh -c 'echo \"127.0.0.1  api-dashboard-fls.local api-dashboard-pd.local\" >> /etc/hosts'"
        echo ""
    fi

    echo "  🔒 Starting Caddy HTTPS proxy..."
    echo "  ┌──────────────────────────────────────────────┐"
    echo "  │  FLS (FlashShip)  → $FLS_HTTPS  │"
    echo "  │  PD  (PrintDash)  → $PD_HTTPS   │"
    echo "  └──────────────────────────────────────────────┘"

    "$CADDY_BIN" start --config "$CADDYFILE" 2>&1
}

proxy-stop() {
    if [ -f "$CADDY_BIN" ]; then
        "$CADDY_BIN" stop 2>&1
        echo "  🛑 Caddy proxy stopped."
    fi
}

# Start both Laravel servers + Caddy HTTPS proxy
serve-https() {
    _kill_stale_servers
    proxy-start
    echo ""

    echo "  🏭 Starting both factory instances (HTTPS mode)..."
    echo "  ┌──────────────────────────────────────────────┐"
    echo "  │  FLS (FlashShip)  → $FLS_HTTPS  │"
    echo "  │  PD  (PrintDash)  → $PD_HTTPS   │"
    echo "  └──────────────────────────────────────────────┘"
    echo "  Press Ctrl+C to stop both."
    echo ""

    APP_ENV=fls php artisan serve --port=$FLS_PORT > >(sed 's/^/  [FLS] /') 2>&1 &
    local fls_pid=$!

    APP_ENV=pd php artisan serve --port=$PD_PORT > >(sed 's/^/  [PD]  /') 2>&1 &
    local pd_pid=$!

    trap "echo ''; echo '  🛑 Stopping all...'; kill $fls_pid $pd_pid 2>/dev/null; proxy-stop; trap - INT; return" INT
    wait $fls_pid $pd_pid
}

# ── Database helpers ───────────────────────────────────

_ensure_db() {
    local db_name=$1
    mysql -u "$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" -P "$LOCAL_DB_PORT" -h 127.0.0.1 \
        -e "CREATE DATABASE IF NOT EXISTS \`$db_name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "  ✅ Database '$db_name' ready"
    else
        echo "  ⚠️  Could not create '$db_name' — create it manually"
    fi
}

_db_name_for() {
    # Read DB_DATABASE from the factory's .env file
    grep '^DB_DATABASE=' ".env.$1" 2>/dev/null | cut -d= -f2
}

# Run pending migrations only (non-destructive)
migrate-all() {
    local fls_db=$(_db_name_for fls)
    local pd_db=$(_db_name_for pd)

    echo ""
    echo "  📦 Ensuring databases exist..."
    _ensure_db "$fls_db"
    _ensure_db "$pd_db"

    echo ""
    echo "  🔄 Migrating FLS..."
    fls artisan migrate --force
    echo ""
    echo "  🔄 Migrating PD..."
    pd artisan migrate --force
    echo ""
    echo "  ✅ Both databases migrated!"
}

# Run seeders only (non-destructive, seeders have if count>0 guards)
seed-all() {
    echo ""
    echo "  🌱 Seeding FLS..."
    fls artisan db:seed
    echo ""
    echo "  🌱 Seeding PD..."
    pd artisan db:seed
    echo ""
    echo "  ✅ Both databases seeded!"
}

# ⚠️ Drop + recreate + seed (destructive)
fresh-all() {
    echo ""
    echo "  ⚠️  This will DROP and RECREATE both databases!"
    echo -n "  Continue? (y/N) "
    read -r confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        echo "  Cancelled."
        return
    fi

    local fls_db=$(_db_name_for fls)
    local pd_db=$(_db_name_for pd)

    echo ""
    echo "  📦 Ensuring databases exist..."
    _ensure_db "$fls_db"
    _ensure_db "$pd_db"

    echo ""
    echo "  🔥 Fresh FLS..."
    fls artisan migrate:fresh --seed
    echo ""
    echo "  🔥 Fresh PD..."
    pd artisan migrate:fresh --seed
    echo ""
    echo "  ✅ Both databases recreated!"
}

echo "🏭 Factory helpers loaded: fls, pd, serve-all, serve-https, proxy-start, proxy-stop, migrate-all, seed-all, fresh-all"
echo "   Using per-factory env: .env.fls (FLS) / .env.pd (PD)"
