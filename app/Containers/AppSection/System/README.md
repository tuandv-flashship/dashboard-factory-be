### System Container

Container path: `app/Containers/AppSection/System`

### Scope

- Expose system info and package info.
- Expose app size and cache status.
- Clear cache.
- Run any Artisan command via dev-only API endpoint.

### API Routes

Route files:
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemInfo.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemPackages.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemAppSize.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemCacheStatus.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/ClearSystemCache.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/RunDevArtisanCommand.v1.private.php`

All System API endpoints use `auth:api`.

### Main Config

- `app/Containers/AppSection/System/Configs/appSection-system.php`
- `app/Containers/AppSection/System/Configs/permissions.php`

Common env keys:
- `SYSTEM_PACKAGES_CACHE_SECONDS`, `SYSTEM_APP_SIZE_CACHE_SECONDS`

### Dev Artisan Runner

`POST /v1/dev/artisan` — Run any artisan command synchronously (non-production + Super Admin only).

```json
{ "command": "translations:import", "options": {"--fresh": true} }
```

### Tests

Available tests:
- `app/Containers/AppSection/System/Tests/Functional/API`

Run:

```bash
php artisan test app/Containers/AppSection/System/Tests
```

### Change Log

- `2026-03-20`: Replaced whitelist command system with generic dev artisan runner.
- `2026-02-07`: Added dedicated System container documentation.
