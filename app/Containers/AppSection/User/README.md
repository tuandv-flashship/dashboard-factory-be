### User Container

Container path: `app/Containers/AppSection/User`

### Scope

- Internal user profile and account management.
- Admin user listing/find/update/delete.
- Password update and current profile retrieval.

### API Routes

Route files:
- `app/Containers/AppSection/User/UI/API/Routes/ListUsers.v1.private.php`
- `app/Containers/AppSection/User/UI/API/Routes/FindUserById.v1.private.php`
- `app/Containers/AppSection/User/UI/API/Routes/UpdateUser.v1.private.php`
- `app/Containers/AppSection/User/UI/API/Routes/DeleteUser.v1.private.php`
- `app/Containers/AppSection/User/UI/API/Routes/UpdatePassword.v1.private.php`
- `app/Containers/AppSection/User/UI/API/Routes/GetUserProfile.v1.private.php`
- `app/Containers/AppSection/User/UI/API/Routes/_user.v1.private.php`

Auth notes:
- All routes are private and use admin/user auth guard (`auth:api`).

### Main Config

- `app/Containers/AppSection/User/Configs/appSection-user.php`

Operational config notes:
- No container-specific runtime keys are required.

### Operational Notes

- IDs in API responses are transformed/hashed via transformers.
- Keep route model ID constraints centralized in `_user.v1.private.php`.
- Update password flow should remain isolated from profile update flow.
- Route middleware contract is `auth:api` for all endpoints.

### Tests

Available tests:
- `app/Containers/AppSection/User/Tests`

Run:

```bash
php artisan test app/Containers/AppSection/User/Tests
```

### Change Log

- `2026-02-07`: Expanded User container README with routes/config/tests notes.
