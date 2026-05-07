### Authorization Container — Department-Scoped RBAC

Container path: `app/Containers/AppSection/Authorization`

### Scope

- Role and permission management.
- Assign/sync/revoke roles and permissions for users.
- Permission tree exposure for admin UI.
- **Department-scoped RBAC** — data-level access control via `department_ids` pivot.

### Architecture

#### Two-Layer Authorization

```
┌─────────────────────────────────────────────────────┐
│  Layer 1: Feature Access (Spatie)                   │
│  "Can this user perform this action?"               │
│  → Request::authorize() → $user->can('flag')        │
├─────────────────────────────────────────────────────┤
│  Layer 2: Data Boundary (DepartmentScope)           │
│  "On which departments' data?"                      │
│  → role_has_permissions.department_ids (JSON)        │
│  → DepartmentScope::check() / applyToQuery()        │
└─────────────────────────────────────────────────────┘
```

#### Permission Tree Structure (3 Groups)

```
Department (order: 1) ← is_department_scopeable: true
├── Shifts (order: 1)
│   ├── Create
│   ├── Edit
│   └── Delete
├── Dashboard (order: 2)
│   └── View Dashboard
└── Hourly Issues (order: 3)
    ├── Create
    ├── Edit
    └── Delete

System (order: 2)
├── KPI Rating Levels (order: 1)
├── Reason Codes (order: 2)
├── Roles (order: 3)
├── Users (order: 4)
├── Media (order: 5)
├── Audit Log (order: 6)
└── Request Log (order: 7)

Settings (order: 3)
├── Shift Templates (order: 1)
├── Production Lines (order: 2)
├── Departments (order: 3)
└── Settings (order: 4)
```

### Data Schema

#### Pivot: `role_has_permissions`

| Column | Type | Description |
|---|---|---|
| `permission_id` | int | FK → permissions.id |
| `role_id` | int | FK → roles.id |
| `department_ids` | JSON, nullable | **NEW** — `null` = Global, `[1,2,3]` = Scoped |

### API Routes

#### Permission Tree (for admin UI rendering)

```
GET /v1/permissions/tree
```

Response: Hierarchical permission tree, sorted by `order`, with `is_department_scopeable` flag.

#### Role CRUD with Department Scopes

```
PATCH /v1/roles/{role_id}
```

**New fields:**
- `permission_scopes[]` — array of `{ permission_id, department_ids[] }`

### Core Support Classes

#### `App\Ship\Supports\DepartmentScope`

| Method | Purpose |
|---|---|
| `resolve($user, $permission)` | Returns `int[]` of allowed dept IDs, or `null` for global |
| `applyToQuery($query, $user, $permission)` | Adds `whereIn('department_id', [...])` to query |
| `check($user, $permission, $deptId)` | Boolean: can user access this specific department? |

### Config

- `app/Ship/Configs/permissions.php` — Root groups (Department, System, Settings)
- `app/Containers/AppSection/*/Configs/permissions.php` — Per-container permissions

### Operational Notes

- Keep permission flags stable to avoid breaking role mappings.
- `department_ids = NULL` in pivot means **Global Access** (all departments).
- `department_ids = [1,2,3]` means **Scoped Access** (only those departments).
- SuperAdmin bypasses all scope checks.
- Run `php artisan apiato:permissions-sync --prune` after config changes.

### Change Log

- `2026-02-07`: Expanded Authorization container README with routes/config/tests notes.
- `2026-05-07`: Added Department-Scoped RBAC — `department_ids` pivot, DepartmentScope class, permission tree with order + is_department_scopeable.
