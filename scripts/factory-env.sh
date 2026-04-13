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
#   serve-all            Start both Octane servers (HTTP)
#   serve-watch          Start both Octane servers with auto-reload
#   serve-https          Start both Octane servers + Caddy HTTPS proxy
#   serve-https-watch    Start both Octane servers + Caddy HTTPS + auto-reload
#   horizon-all          Start Horizon queue dashboard for both factories
#   migrate-all          Run pending migrations on both DBs (safe)
#   seed-all             Run only seeders on both DBs (safe)
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

fls() {
    APP_ENV=fls "$@"
}

pd() {
    APP_ENV=pd "$@"
}

artisan() {
    php artisan "$@"
}

# ── Internal helpers ───────────────────────────────────

_kill_stale_servers() {
    local stale_pids
    stale_pids=$(pgrep -f 'artisan serve|artisan octane|frankenphp' 2>/dev/null)
    if [ -n "$stale_pids" ]; then
        echo "  🧹 Killing stale server processes..."
        echo "$stale_pids" | xargs kill 2>/dev/null
        sleep 1
    fi
}

# Start both Octane servers with optional flags and cleanup callback
# Usage: _start_octane_pair [extra_artisan_flags...] [-- cleanup_fn]
_start_octane_pair() {
    local extra_flags=()
    local cleanup_fn=""

    # Parse args: flags before --, cleanup fn after --
    while [[ $# -gt 0 ]]; do
        if [[ "$1" == "--" ]]; then
            shift
            cleanup_fn="$1"
            break
        fi
        extra_flags+=("$1")
        shift
    done

    # Start Octane servers (filter FrankenPHP internal Caddy warnings)
    APP_ENV=fls php artisan octane:start --server=frankenphp --host=localhost --port=$FLS_PORT "${extra_flags[@]}" 2>&1 | grep -v 'Caddyfile input is not formatted\|HTTP/2 skipped\|HTTP/3 skipped' | sed 's/^/  [FLS] /' &
    local fls_pid=$!

    APP_ENV=pd php artisan octane:start --server=frankenphp --host=localhost --port=$PD_PORT "${extra_flags[@]}" 2>&1 | grep -v 'Caddyfile input is not formatted\|HTTP/2 skipped\|HTTP/3 skipped' | sed 's/^/  [PD]  /' &
    local pd_pid=$!

    # Start Horizon queue workers (auto-start with server)
    APP_ENV=fls php artisan horizon > /dev/null 2>&1 &
    local fls_horizon_pid=$!

    APP_ENV=pd php artisan horizon > /dev/null 2>&1 &
    local pd_horizon_pid=$!

    # Start Scheduler (runs schedule:run every minute)
    APP_ENV=fls php artisan schedule:work > /dev/null 2>&1 &
    local fls_scheduler_pid=$!

    APP_ENV=pd php artisan schedule:work > /dev/null 2>&1 &
    local pd_scheduler_pid=$!

    local all_pids="$fls_pid $pd_pid $fls_horizon_pid $pd_horizon_pid $fls_scheduler_pid $pd_scheduler_pid"

    if [ -n "$cleanup_fn" ]; then
        trap "echo ''; echo '  🛑 Stopping all...'; kill $all_pids 2>/dev/null; $cleanup_fn; trap - INT; return" INT
    else
        trap "echo ''; echo '  🛑 Stopping all...'; kill $all_pids 2>/dev/null; trap - INT; return" INT
    fi
    wait $fls_pid $pd_pid
}

_caddy_start() {
    if ! [ -f "$CADDY_BIN" ]; then
        echo "  ❌ Caddy not found at $CADDY_BIN"
        echo "  Run: curl -L https://github.com/caddyserver/caddy/releases/download/v2.11.2/caddy_2.11.2_mac_arm64.tar.gz | tar xz -C bin/"
        return 1
    fi

    if ! grep -q 'api-dashboard-fls.local' /etc/hosts 2>/dev/null; then
        echo "  ⚠️  Add to /etc/hosts (requires sudo):"
        echo "     sudo sh -c 'echo \"127.0.0.1  api-dashboard-fls.local api-dashboard-pd.local\" >> /etc/hosts'"
        echo ""
    fi

    echo "  🔒 Starting Caddy HTTPS proxy..."
    "$CADDY_BIN" start --config "$CADDYFILE" 2>&1
}

_caddy_stop() {
    if [ -f "$CADDY_BIN" ]; then
        "$CADDY_BIN" stop 2>&1
        echo "  🛑 Caddy proxy stopped."
    fi
}

# ── Serve Commands ─────────────────────────────────────

serve-all() {
    _kill_stale_servers
    echo ""
    echo "  🏭 Starting both factory instances (Octane + Horizon + Scheduler)..."
    echo "  ┌──────────────────────────────────────────────┐"
    echo "  │  FLS (FlashShip)  → http://localhost:$FLS_PORT    │"
    echo "  │  PD  (PrintDash)  → http://localhost:$PD_PORT    │"
    echo "  │  📊 Horizon + ⏰ Scheduler auto-started       │"
    echo "  └──────────────────────────────────────────────┘"
    echo "  Press Ctrl+C to stop all."
    echo ""

    _start_octane_pair
}

serve-watch() {
    _kill_stale_servers
    echo ""
    echo "  🏭 Starting both factory instances (Octane + watch + Horizon + Scheduler)..."
    echo "  ┌──────────────────────────────────────────────┐"
    echo "  │  FLS (FlashShip)  → http://localhost:$FLS_PORT    │"
    echo "  │  PD  (PrintDash)  → http://localhost:$PD_PORT    │"
    echo "  │  📊 Horizon + ⏰ Scheduler auto-started       │"
    echo "  └──────────────────────────────────────────────┘"
    echo "  📂 Watching for file changes..."
    echo "  Press Ctrl+C to stop all."
    echo ""

    _start_octane_pair --watch
}

serve-https() {
    _kill_stale_servers
    _caddy_start || return 1
    echo ""
    echo "  🏭 Starting both factory instances (Octane + HTTPS)..."
    echo "  ┌────────────────────────────────────────────────────────┐"
    echo "  │  FLS (FlashShip)  → $FLS_HTTPS  │"
    echo "  │  PD  (PrintDash)  → $PD_HTTPS   │"
    echo "  └────────────────────────────────────────────────────────┘"
    echo "  Press Ctrl+C to stop both."
    echo ""

    _start_octane_pair -- _caddy_stop
}

serve-https-watch() {
    _kill_stale_servers
    _caddy_start || return 1
    echo ""
    echo "  🏭 Starting both factory instances (Octane + HTTPS + watch)..."
    echo "  ┌────────────────────────────────────────────────────────┐"
    echo "  │  FLS (FlashShip)  → $FLS_HTTPS  │"
    echo "  │  PD  (PrintDash)  → $PD_HTTPS   │"
    echo "  └────────────────────────────────────────────────────────┘"
    echo "  📂 Watching for file changes..."
    echo "  Press Ctrl+C to stop all."
    echo ""

    _start_octane_pair --watch -- _caddy_stop
}

# ── Horizon ────────────────────────────────────────────

horizon-all() {
    echo ""
    echo "  📊 Starting Horizon for both factories..."
    echo "  ┌──────────────────────────────────────────────┐"
    echo "  │  FLS (FlashShip)  → /horizon on :$FLS_PORT   │"
    echo "  │  PD  (PrintDash)  → /horizon on :$PD_PORT   │"
    echo "  └──────────────────────────────────────────────┘"
    echo "  Press Ctrl+C to stop both."
    echo ""

    APP_ENV=fls php artisan horizon > >(sed 's/^/  [FLS] /') 2>&1 &
    local fls_pid=$!

    APP_ENV=pd php artisan horizon > >(sed 's/^/  [PD]  /') 2>&1 &
    local pd_pid=$!

    trap "echo ''; echo '  🛑 Stopping Horizon...'; kill $fls_pid $pd_pid 2>/dev/null; trap - INT; return" INT
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
    grep '^DB_DATABASE=' ".env.$1" 2>/dev/null | cut -d= -f2
}

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

# ── Load message ───────────────────────────────────────

echo "🏭 Factory helpers loaded: fls, pd, serve-all, serve-watch, serve-https, serve-https-watch, migrate-all, seed-all, fresh-all"
echo "   Using per-factory env: .env.fls (FLS) / .env.pd (PD)"
echo "   Server: Laravel Octane (FrankenPHP) | Queue: Redis + Horizon | ⏰ Scheduler"
