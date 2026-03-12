### Setting Container

Container path: `app/Containers/AppSection/Setting`

### Scope

- Manage application settings for general, media, admin appearance, and phone rules.
- Expose settings read/write endpoints for admin panels.

### API Routes

Route files:
- `app/Containers/AppSection/Setting/UI/API/Routes`

Main route groups:
- Get/update general settings.
- Get/update media settings.
- Get/update admin appearance settings.
- Get/update phone number settings.

Auth notes:
- Routes are private and intended for authenticated admin/staff APIs.

### Main Config

- This container relies mostly on DB-backed settings and shared app configs.

### Operational Notes

- Settings updates may require cache invalidation depending on usage site.
- Keep setting keys backward-compatible for UI and seeded defaults.

### Tests

Available tests:
- `app/Containers/AppSection/Setting/Tests`

Run:

```bash
php artisan test app/Containers/AppSection/Setting/Tests
```

### Change Log

- `2026-02-07`: Added container README.
