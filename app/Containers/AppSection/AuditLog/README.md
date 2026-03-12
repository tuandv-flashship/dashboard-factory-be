### AuditLog Container

Container path: `app/Containers/AppSection/AuditLog`

### Scope

- Store and list audit history.
- Delete single audit log item.
- Delete all audit logs.
- Provide widget/activity feed for admin dashboard.

### API Routes

Route files:
- `app/Containers/AppSection/AuditLog/UI/API/Routes/ListAuditLogs.v1.private.php`
- `app/Containers/AppSection/AuditLog/UI/API/Routes/DeleteAuditLog.v1.private.php`
- `app/Containers/AppSection/AuditLog/UI/API/Routes/DeleteAllAuditLogs.v1.private.php`
- `app/Containers/AppSection/AuditLog/UI/API/Routes/GetAuditLogWidget.v1.private.php`

All AuditLog API endpoints currently use `auth:api`.

### Main Config

- `app/Containers/AppSection/AuditLog/Configs/audit-log.php`
- `app/Containers/AppSection/AuditLog/Configs/permissions.php`

Notes:
- Sensitive request fields excluded from audit payload are configured in `audit-log.excluded_request_keys`.

### Operational Notes

- Audit payload filtering must stay aligned with privacy requirements.
- Bulk delete should be restricted to privileged roles.
- Route middleware contract is `auth:api` for all endpoints.

### Tests

Available tests:
- `app/Containers/AppSection/AuditLog/Tests`

Run:

```bash
php artisan test app/Containers/AppSection/AuditLog/Tests
```

### Change Log

- `2026-02-07`: Added dedicated AuditLog container documentation.
