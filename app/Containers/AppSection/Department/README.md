# Department Container

> Porto SAP Container — Quản lý Department (bộ phận sản xuất).

## Domain

Department đại diện cho các bộ phận sản xuất (In ấn, Cắt, Ráp mẫu, Đóng gói & Giao, Pick...) thuộc các Production Line. Mỗi department có KPI riêng, đơn vị đo (file/shirt/print), và thuộc factory (FLS/PD).

## Structure

```
Department/
├── Models/Department.php          # Eloquent model, FK → production_lines
├── Enums/
│   ├── DepartmentUnit.php         # file | shirt | print
│   └── Factory.php                # FLS | PD
├── Data/
│   ├── Repositories/              # DepartmentRepository (searchable)
│   └── Migrations/                # create_departments, merge_pick
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
