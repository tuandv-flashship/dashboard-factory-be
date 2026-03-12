### RequestLog Container

Container path: `app/Containers/AppSection/RequestLog`

### Scope

- Capture request log entries.
- List request logs.
- Delete single request log item.
- Delete all request logs.
- Provide request-error widget feed for admin dashboard.

### API Routes

Route files:
- `app/Containers/AppSection/RequestLog/UI/API/Routes/ListRequestLogs.v1.private.php`
- `app/Containers/AppSection/RequestLog/UI/API/Routes/DeleteRequestLog.v1.private.php`
- `app/Containers/AppSection/RequestLog/UI/API/Routes/DeleteAllRequestLogs.v1.private.php`
- `app/Containers/AppSection/RequestLog/UI/API/Routes/GetRequestLogWidget.v1.private.php`

All RequestLog API endpoints currently use `auth:api`.

### Main Config

- `app/Containers/AppSection/RequestLog/Configs/request-log.php`
- `app/Containers/AppSection/RequestLog/Configs/permissions.php`

### Operational Notes

- Request logs can grow quickly, keep retention/cleanup strategy in place.
- Redact sensitive fields before persisting payload/body data.
- Route middleware contract is `auth:api` for all endpoints.

### Tests

Available tests:
- `app/Containers/AppSection/RequestLog/Tests`

Run:

```bash
php artisan test app/Containers/AppSection/RequestLog/Tests
```

### Change Log

- `2026-02-07`: Added dedicated RequestLog container documentation.
