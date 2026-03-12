### System Container

Container path: `app/Containers/AppSection/System`

### Scope

- Expose system info and package info.
- Expose app size and cache status.
- Clear cache.
- List and run whitelisted Artisan commands.
- Query background system command job status.

### API Routes

Route files:
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemInfo.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemPackages.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemAppSize.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemCacheStatus.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/ClearSystemCache.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/ListSystemCommands.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/RunSystemCommand.v1.private.php`
- `app/Containers/AppSection/System/UI/API/Routes/GetSystemCommandStatus.v1.private.php`

All System API endpoints currently use `auth:api`.

### Main Config

- `app/Containers/AppSection/System/Configs/appSection-system.php`
- `app/Containers/AppSection/System/Configs/system-commands.php`
- `app/Containers/AppSection/System/Configs/permissions.php`

Common env keys:
- `SYSTEM_PACKAGES_CACHE_SECONDS`, `SYSTEM_APP_SIZE_CACHE_SECONDS`
- `SYSTEM_COMMANDS_ENABLED`
- `SYSTEM_COMMANDS_RESULT_TTL`

### Operational Notes

- Allowed commands are whitelisted in `system-commands.commands`.
- Default behavior disables command execution in production unless explicitly enabled.

### Tests

Available tests:
- `app/Containers/AppSection/System/Tests/Functional/API`

Run:

```bash
php artisan test app/Containers/AppSection/System/Tests
```

### Change Log

- `2026-02-07`: Added dedicated System container documentation.
