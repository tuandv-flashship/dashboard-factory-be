### Icon Container

Container path: `app/Containers/AppSection/Icon`

### Scope

- Manage and provide a list of icons for the frontend application.
- Sync icons from a local directory or GitHub repository (Tabler Icons).
- Provide an API endpoint to list and search available icons.

### API Routes

Route files:
- `app/Containers/AppSection/Icon/UI/API/Routes`

Main route groups:
- List icons (searchable, paginated).

Auth notes:
- Routes are private and intended for authenticated users.

### Main Config

- `app/Containers/AppSection/Icon/Configs/icon.php`

### Commands

This container includes a command to sync icons and generate a manifest file.

**Sync from local directory:**
```bash
php artisan icon:sync-manifest --source=/path/to/svg/directory
```

**Sync from GitHub (Tabler Icons):**
```bash
php artisan icon:sync-manifest --source=github
```

The manifest file is stored at `app/Containers/AppSection/Icon/Resources/icons-manifest.json`.

### Operational Notes

- The container relies on a JSON manifest file for performance, avoiding the need to scan directories or database queries on every request.
- The manifest is cached to further improve performance.
- The default icon prefix is configured in `Configs/icon.php` (default: `ti ti-`).

### Tests

Available tests:
- `app/Containers/AppSection/Icon/Tests`

Run:

```bash
php artisan test app/Containers/AppSection/Icon/Tests
```

### Change Log

- `2026-02-10`: Created Icon container with sync command and listing API.
