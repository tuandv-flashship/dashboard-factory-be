# Table Container

Centralized metadata API for FE data tables + dynamic form rendering — column definitions, searchable fields, actions, bulk operations, form field metadata.

## Architecture

```
FE → GET /v1/table-meta?model=production_line
   ← { columns, header_actions, row_actions, bulk_actions, bulk_changes, default_sort, pagination }

FE → GET /v1/form-meta?model=department&action=create
   ← { groups, fields[] (with validation rules), submit{method, url} }
```

### Core Components

| Component | Mô tả |
|---|---|
| `BulkActionRegistry` | Singleton — auto-discover + resolve config + permission filter + cache |
| `ColumnDefinition` | Fluent builder cho column metadata (type, width, searchable, sortable, `enum()`) |
| `FormFieldDefinition` | Fluent builder cho form field metadata (11+ types, validation, groups) |
| `ValidationRuleParser` | Laravel Request `rules()` → react-hook-form validation JSON |
| `GetFormMetaAction` | Form metadata resolver: auto-detect + merge overrides + cache |
| `ActionDefinition` | Fluent builder cho actions (link vs API action, permission-gated) |
| `HasTableConfig` | Trait cho models muốn override auto-detection |
| `TableServiceProvider` | Registers singleton + loads translations |

### Resolution Priority (table-meta)

1. **Model trait** (`getTableColumns()`, `getTableRowActions()`, …)
2. **Config** (`table-models.php` → `columns`, `row_actions`, etc.)
3. **Auto-detection** (từ `$fillable`, `$casts`, `$fieldSearchable`)

## Config — Distributed Pattern

> **Convention**: Mỗi container tạo `Configs/table-models.php` → auto-discovered bởi Table container.

### Global Config: `Table/Configs/appSection-table.php`

```php
return [
    'cache_ttl'      => env('TABLE_META_CACHE_TTL', 3600),
    'max_bulk_items' => 100,
    'models'         => [],  // auto-discovered from containers
];
```

### Container Config Example: `Production/Configs/table-models.php`

```php
return [
    'production_line' => [
        'model'             => ProductionLine::class,
        'repository'        => ProductionLineRepository::class,
        'permission_prefix' => 'production-lines',
        'permission'        => 'production-lines.index',
        'api_prefix'        => '/v1/production-lines',
        'fe_prefix'         => '/production-lines',
        'forms' => [
            'create' => [
                'request'    => CreateProductionLineRequest::class,
                'permission' => 'production-lines.create',
                'submit'     => ['method' => 'POST', 'url' => '/v1/production-lines'],
                'groups'     => [...],
                'overrides'  => [...],
            ],
        ],
    ],
];
```

### Registered Models

| Key | Model | Container | Permission | Form Actions |
|---|---|---|---|---|
| `production_line` | ProductionLine | Production | `production-lines.*` | create, update |
| `department` | Department | Production | `departments.*` | create, update |

## API Endpoints

| Method | Endpoint | Auth | Mô tả |
|---|---|---|---|
| GET | `/v1/table-meta?model={key}` | 🔒 | Table metadata (bỏ `model` → list all) |
| GET | `/v1/form-meta?model={key}&action={action}` | 🔒 | Form metadata (fields, validation, groups) |
| POST | `/v1/bulk-actions` | 🔒 | Batch delete |
| POST | `/v1/bulk-changes` | 🔒 | Batch field update |
| PUT | `/v1/table-columns-visibility` | 🔒 | Save column prefs per user |

### Response `GET /v1/form-meta?model=department&action=create`

```json
{
  "data": {
    "model": "department",
    "action": "create",
    "groups": [
      { "key": "basic", "label": "Thông tin cơ bản", "order": 0 }
    ],
    "fields": [
      {
        "key": "code", "label": "Mã", "type": "text",
        "group": "basic", "order": 0, "col_span": 1,
        "validation": {
          "required": "Mã là bắt buộc",
          "maxLength": { "value": 30, "message": "Tối đa 30 ký tự" }
        }
      },
      {
        "key": "label", "label": "Nhãn", "type": "text",
        "group": "basic", "order": 1
      },
      {
        "key": "production_line_id", "label": "Dây chuyền", "type": "relation",
        "group": "basic", "order": 5,
        "validation": { "required": "Dây chuyền là bắt buộc" },
        "endpoint": "/v1/production-lines",
        "label_field": "name", "value_field": "id"
      }
    ],
    "submit": { "method": "POST", "url": "/v1/departments" }
  }
}
```

## FormFieldDefinition Types

| Type | Factory Method | FE Renders |
|---|---|---|
| `text` | `::text()` | Text input |
| `textarea` | `::textarea()` | Textarea |
| `number` | `::number()` | Number input |
| `select` | `::select()` | Select dropdown |
| `boolean` | `::boolean()` | Toggle/Checkbox |
| `relation` | `::relation()` | Async select (API) |
| `color` | `::color()` | Color picker |
| `icon` | `::icon()` | Icon picker |
| `date` / `datetime` | `::date()` / `::datetime()` | Date/DateTime picker |
| `hidden` | `::hidden()` | Hidden input |
| `json` | auto-detected | JSON editor |

## ValidationRuleParser Mapping

| Laravel Rule | react-hook-form | Notes |
|---|---|---|
| `required` | `required: "msg"` | Supports custom `messages()` |
| `max:N` / `min:N` (string) | `maxLength` / `minLength` | |
| `max:N` / `min:N` (number) | `max` / `min` | |
| `regex:/p/` | `pattern: {value, message}` | |
| `email` | `pattern: {email regex}` | |
| `Rule::enum()` | type → `select`, options auto | |
| `Rule::in()` | type → `select`, options auto | |
| `array` | type → `json` | Nested rules skipped |
| `exists:table,col` | type → `relation` | |

## Column Auto-Detection

Columns tự động detected từ model:
- `id` — always included
- `image` — if in `$fillable`
- `name` — if in `$fillable` (searchable)
- `email`, `phone` — if in `$fillable`
- `status` — if cast to enum (supports `enum()` for auto options)
- `is_featured` — if cast to bool
- `created_at` — if model uses timestamps

Searchable fields tự động sync từ `Repository::$fieldSearchable`.

## i18n

Translation namespace: `table::`

| File | Nội dung |
|---|---|
| `columns.php` | Column titles |
| `actions.php` | Action labels |
| `fields.php` | Form field labels |
| `groups.php` | Form group labels |
| `bulk_changes.php` | Bulk change placeholders |

## Cache Strategy

| Cache Key | TTL | Invalidation |
|---|---|---|
| `table_meta:{user_id}:{model}:{locale}` | `TABLE_META_CACHE_TTL` | Column visibility save |
| `form_meta:{model}:{action}` | `TABLE_META_CACHE_TTL` | Config change, `cache:clear` |

## Change Log

- `2026-03-24`: **Form-meta API** — `GET /v1/form-meta` + ValidationRuleParser + safe try-catch
- `2026-03-24`: **Distributed config** — model configs moved to `table-models.php` per container
- `2026-03-24`: **ColumnDefinition** — `enum()` method + options serialization fix
- `2026-03-24`: **BulkActionRegistry** — permission check, pagination, auto-discover
- `2026-03-23`: Search operator sync from Repository `$fieldSearchable`
- `2026-03-22`: Initial implementation
