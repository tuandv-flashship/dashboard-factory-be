# Department Container

> Porto SAP Container — Quản lý Department (bộ phận sản xuất).

## Domain

Department đại diện cho các bộ phận sản xuất (In ấn, Cắt, Ráp mẫu, Đóng gói & Giao, Pick...) thuộc các Production Line. Mỗi department có KPI riêng, đơn vị đo (file/shirt/print), thuộc factory (FLS/PD), và cờ `can_increase_productivity` (mặc định `true`) đánh dấu bộ phận có thể tăng năng suất.

## Structure

```
Department/
├── Models/Department.php          # Eloquent model, FK → production_lines
├── Enums/
│   ├── DepartmentUnit.php         # file | shirt | print
│   └── Factory.php                # FLS | PD
├── Data/
│   ├── Repositories/              # DepartmentRepository (searchable)
│   └── Migrations/                # create_departments, merge_pick, add_description
├── Actions/                       # CRUD + Reorder (6 actions)
├── Tasks/                         # CRUD + FindByLineId (6 tasks)
├── Configs/table-models.php       # Auto-discovered by Table container
└── UI/API/
    ├── Controllers/               # 6 invokable controllers
    ├── Requests/                   # 6 form requests w/ validation
    ├── Routes/                     # /v1/admin/departments/*
    └── Transformers/               # DepartmentTransformer
```

## Cross-Container Dependencies

| Direction | Container | Via |
|-----------|-----------|-----|
| ← uses    | Production | `ProductionLine` (FK), `HourlyRecord` (FK) |
| ← uses    | Shift      | `ShiftDetail`, `ShiftTemplateDetail` (FK) |
| → uses    | Production | `ProductionLine` model (belongsTo relation) |

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/v1/admin/departments` | List (paginated, searchable) |
| POST   | `/v1/admin/departments` | Create |
| GET    | `/v1/admin/departments/{id}` | Find |
| PATCH  | `/v1/admin/departments/{id}` | Update |
| DELETE | `/v1/admin/departments/{id}` | Delete |
| PATCH  | `/v1/admin/departments/reorder` | Reorder |

## Create Department Payload

FE gửi payload theo mockup "Thêm mới bộ phận". `code` được tự động sinh từ `Str::slug(name)`.

| Field | Type | Required | Mô tả |
|---|---|---|---|
| name | string(50) | ✅ | Tên bộ phận → stored as `label` |
| factory | enum | ✅ | FLS / PD (Xưởng) |
| group | int | ✅ | FK → production_lines.id (Nhóm) |
| description | string(255) | ❌ | Mô tả, nullable |
| kpi_per_hour | int | ❌ | Năng suất 1h, default 0 |
| unit | enum | ❌ | file / shirt / print (Đơn vị tính) |
| sort_order | int | ❌ | Thứ tự sắp xếp, default 0 |
| can_increase_productivity | bool | ❌ | Default true |

**Auto-defaults:** `code`=slug(name), `label_en`=name, `icon`=Layers, `is_active`=true
