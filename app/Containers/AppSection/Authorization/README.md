### Authorization Container

Container path: `app/Containers/AppSection/Authorization`

### Scope

- Role and permission management.
- Assign/sync/revoke roles and permissions for users.
- Permission tree exposure for admin UI.

### API Routes

Route files are in:
- `app/Containers/AppSection/Authorization/UI/API/Routes`

Main route groups:
- Role CRUD and role-permission sync.
- Permission listing/find and permission tree.
- User-role and user-permission assignment/sync/revoke.

Auth notes:
- Endpoints are private/admin and guarded by `auth:api` plus permission checks.

### Main Config

- `app/Containers/AppSection/Authorization/Configs/appSection-authorization.php`
- `app/Containers/AppSection/Authorization/Configs/permission.php`
- `app/Containers/AppSection/Authorization/Configs/permissions.php`

Operational config notes:
- `super_admins` list defines privileged accounts.
- Permission seed/config maps feature flags used by gate/policy checks.

### Operational Notes

- Keep permission flags stable (`roles.*`, `users.*`) to avoid breaking role mappings.
- When adding new secured endpoints, add matching permission flags and seed/migration path.
- Route files `_role.v1.private.php` and `_permission.v1.private.php` are shared route param constraints.
- Route middleware contract is `auth:api` for all endpoints.

### Tests

Available tests:
- `app/Containers/AppSection/Authorization/Tests`

Run:

```bash
php artisan test app/Containers/AppSection/Authorization/Tests
```

### Change Log

- `2026-02-07`: Expanded Authorization container README with routes/config/tests notes.
