# Production Container

Container quản lý **dữ liệu sản xuất theo giờ** cho toàn bộ dây chuyền PrintDash.

## Mô tả

Container bao gồm 3 bảng: production lines (4 lines gồm Pick), hourly production records (target vs actual), và hourly issues (lý do miss KPI).

> **Lưu ý:**
> - Bảng `shifts` và model `Shift.php` đã được chuyển sang **Shift Container**.
> - Bảng `departments` và toàn bộ Department logic đã được chuyển sang **[Department Container](../Department/README.md)**.
> - Production container import cross-container qua `App\Containers\AppSection\Department\Models\Department` và `App\Containers\AppSection\Shift\Models\Shift` khi cần.

## Database Schema (3 tables)

### `production_lines`
| Column | Type | Mô tả |
|---|---|---|
| code | varchar(20) | Unique: `dtf1`, `dtf2`, `dtg`, `pick` |
| label | varchar(50) | DTF 1, DTF 2, DTG, Pick |
| color | varchar(20) | Hex: #f59e0b, #14b8a6, #8b5cf6, #ec4899 |
| subtitle | varchar(255) | Building 1, Apollo + 2× Atlas, Lấy hàng — Chung... (nullable) |
| is_shared | boolean | `false` (default), `true` cho Pick |
| sort_order | smallint | Thứ tự sắp xếp |

### `departments` ➡️ **Moved to Department Container**

> Xem chi tiết tại [Department README](../Department/README.md).

### `shifts` ➡️ **Moved to Shift Container**

> Xem chi tiết tại [Shift README](../Shift/README.md#shifts).

### `hourly_records` ⭐ Bảng chính
| Column | Type | Mô tả |
|---|---|---|
| shift_id | FK | → shifts |
| department_id | FK | → departments (bao gồm pick departments) |
| hour_slot | varchar(10) | "6h-7h", "7h-8h", ... |
| hour_index | tinyint | 0-7 (index trong ca) |
| target | int | KPI mục tiêu giờ này |
| actual | int (nullable) | Thực đạt (null = giờ tương lai) |
| staff | smallint | Số nhân viên |
| staff_required | smallint (nullable) | Số nhân viên yêu cầu (override) |
| **machine_count** | **smallint (nullable)** | **Số máy in hoạt động per slot (DTF: manual, DTG: auto-computed)** |
| hour_start_inventory | int | Tồn đầu giờ (ton_cuoi từ FPlatform, set 1 lần) |
| efficiency | float | % hiệu suất: 94.2 |
| error_rate | float | % lỗi: 2.1 |
| status | enum | pending, active, completed |
| | | **Unique constraint:** shift_id + department_id + hour_index |

> 💡 **`machine_count` fallback logic (tính target):**
> - **DTF:** `hourly_record.machine_count` → `shift_detail.machine_count` (nếu null)
> - **DTG:** Auto-computed = `count(hourly_record_machines)` khi gửi `active_machine_ids`
> - **Per-person:** Không sử dụng `machine_count`

### `hourly_record_machines` — Máy DTG override per khung giờ

Bảng pivot lưu máy DTG nào hoạt động cho từng khung giờ cụ thể. Chỉ được tạo khi user chủ động gửi `active_machine_ids` — nếu không có thì fallback về `shift_detail_machines`.

| Column | Type | Mô tả |
|---|---|---|
| id | bigint PK | Auto increment |
| hourly_record_id | FK → hourly_records | Cascade on delete |
| machine_id | FK → machines | Cascade on delete |
| kpi_per_hour | unsignedInt | Snapshot KPI máy tại thời điểm gán |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** `(hourly_record_id, machine_id)`

### `hourly_issues`
| Column | Type | Mô tả |
|---|---|---|
| hourly_record_id | FK | → hourly_records |
| category | varchar(20) | machine, human, material, process |
| sub_item | varchar | Tên máy/lý do: DTF-01, Nhân viên mới |
| error | varchar | Lỗi cụ thể: Chạy chậm, Chưa được đào tạo |
| note | text | Ghi chú: "Giảm 12 so với KPI" |
| resolved_at | timestamp | Thời điểm khắc phục (nullable) |
| resolution | text | Cách khắc phục (nullable) |

## Scheduled Sync (FPlatform → hourly_records)

### `SyncHourlyRecordsJob`

Job chạy định kỳ (mặc định **5 phút**, configurable) lấy dữ liệu từ FPlatform và cập nhật `hourly_records` cho khung giờ hiện tại.

| Config | Env | Default | Mô tả |
|---|---|---|---|
| `factory.hourly_records_sync_interval` | `HOURLY_RECORDS_SYNC_INTERVAL` | `5` | Interval (phút). `0` = disabled |

**Logic:**
1. Lấy active shift + shift_details (7 departments)
2. Xác định `hour_slot` hiện tại cho mỗi department
3. Query FPlatform: `productivity` → `actual`, `staff_count` → `staff`
4. `hour_start_inventory` = `ton_cuoi` từ FPlatform (chỉ set lần đầu)
5. `efficiency` = `actual / target × 100`
6. `status`: `pending` → `active`

## API Endpoints

| Method | Endpoint | Auth | Historical | Mô tả |
|---|---|---|---|---|
| GET | `/v1/production/hourly?date=&shift=` | Public ✅ | ✅ | **Tất cả lines → depts → hourly** |
| GET | `/v1/production/lines` | Public ✅ | ❌ | 4 production lines + departments |
| GET | `/v1/production/lines/{line}?date=&shift=` | Public ✅ | ✅ | Summary toàn bộ line (hourly records) |
| GET | `/v1/production/lines/{line}/departments/{dept}?date=&shift=` | Public ✅ | ✅ | Chi tiết 1 dept (hourly + issues) |

### Admin Endpoints (🔒 Bearer Token)

| Method | Endpoint | Permission | Mô tả |
|---|---|---|---|
| GET | `/v1/admin/production-lines` | `production-lines.index` | List paginated + search/filter |
| GET | `/v1/admin/production-lines/:id` | `production-lines.index` | Find by ID |
| POST | `/v1/admin/production-lines` | `production-lines.create` | Create new |
| PATCH | `/v1/admin/production-lines/:id` | `production-lines.edit` | Partial update |
| DELETE | `/v1/admin/production-lines/:id` | `production-lines.destroy` | Delete (CASCADE) |
| PATCH | `/v1/admin/production-lines/reorder` | `production-lines.edit` | Batch reorder |

#### Hourly Issues Endpoints (🔒 Bearer Token)

| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/v1/admin/production/issues` | List tất cả issues (filter: date, shift, department_id, category, resolved, date_from, date_to) |
| GET | `/v1/admin/production/issues/summary` | Thống kê tổng hợp: total, pending, resolved, resolved_percent |
| GET | `/v1/admin/production/pending-issues` | Issues chưa khắc phục cho 1 ca (filter: date, shift, department_id) |
| POST | `/v1/admin/hourly-records/:id/issues` | Tạo issue mới cho 1 khung giờ |
| PATCH | `/v1/admin/hourly-issues/:id` | Sửa issue |
| DELETE | `/v1/admin/hourly-issues/:id` | Xóa issue |
| PATCH | `/v1/admin/hourly-issues/:id/resolve` | Đánh dấu đã khắc phục |
| PATCH | `/v1/admin/hourly-issues/:id/unresolve` | Bỏ đánh dấu khắc phục |

#### Department Filter (List Production Lines)

Khi dùng `?include=departments`, FE có thể filter departments theo factory và trạng thái:

| Param | Type | Values | Mô tả |
|---|---|---|---|
| `dept_factory` | string | `FLS`, `PD` | Lọc theo xưởng |
| `dept_active` | boolean | `1` / `0` | Lọc theo trạng thái hoạt động |

**Ví dụ:**
```bash
# Tất cả departments
GET /v1/admin/production-lines?include=departments

# Chỉ departments xưởng FLS
GET /v1/admin/production-lines?include=departments&dept_factory=FLS

# Chỉ departments đang hoạt động
GET /v1/admin/production-lines?include=departments&dept_active=1

# FLS + đang hoạt động
GET /v1/admin/production-lines?include=departments&dept_factory=FLS&dept_active=1

# PD + đang hoạt động
GET /v1/admin/production-lines?include=departments&dept_factory=PD&dept_active=1
```

> **Department Admin Endpoints** đã chuyển sang [Department Container](../Department/README.md#api-endpoints).

### Table Meta API (🔒 Bearer Token)

| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/v1/table-meta?model={key}` | Column metadata, actions, search fields |
| POST | `/v1/bulk-actions` | Batch delete |
| POST | `/v1/bulk-changes` | Batch field update |
| PUT | `/v1/table-columns-visibility` | Save column prefs per user |

**Path params:**
- `line`: `dtf1`, `dtf2`, `dtg`, **`pick`**
- `dept`: `print`, `cut`, `mockup`, `pack_ship`, `dtg_print` (cho dtf/dtg lines) hoặc `dtf1`, `dtf2`, `dtg` (cho pick line)

**Query params (optional):**

| Param | Type | Validation | Mặc định |
|---|---|---|---|
| `date` | string | `YYYY-MM-DD`, không tương lai | Hôm nay |
| `shift` | int | `1`, `2`, `3` | Ca hiện tại |

> Validation qua `ShiftFilterRequest` (app/Ship/Requests/) — error messages tiếng Việt.

### All Lines Hourly Response
```json
{
  "data": {
    "shift": { "date": "2026-04-14", "shift_number": 1 },
    "lines": [
      {
        "code": "dtf",
        "label": "DTF",
        "color": "#3B82F6",
        "departments": [
          {
            "department": { "code": "print", "label": "In" },
            "hourly": [
              { "hour_slot": "6h-7h", "hour_index": 0, "target": 120, "actual": 105, "staff": 4, "hour_start_inventory": 320, "efficiency": 87.5, "status": "active" },
              { "hour_slot": "7h-8h", "hour_index": 1, "target": 120, "actual": null, "staff": 0, "status": "pending" }
            ]
          }
        ]
      }
    ]
  }
}
```

### Optimizations
- **Caching:** `/hourly` → 2 phút (today), 1 giờ (historical). `/lines/{line}` → 1 giờ (historical only)
- **Rate Limiting:** `throttle:60,1` (60 req/phút/IP) trên tất cả public routes
- **Eager Loading:** `->with('issues')` trong `GetDeptDetailTask`
- **Single Query:** `/hourly` chỉ 1 SQL query cho tất cả hourly records

### FE Integration
```typescript
import { useProductionLines, useLineSummary, useDeptDetail } from "@/hooks/useApi";
const { data: lines } = useProductionLines();
const { data: dtf1 } = useLineSummary("dtf1");                           // ca hiện tại, auto-refresh 30s
const { data: dtf1 } = useLineSummary("dtf1", { date: "2026-03-11", shift: 1 }); // lịch sử, no refresh
const { data: pick } = useLineSummary("pick");                           // pick line summary
const { data: detail } = useDeptDetail("pick", "dtf1");                  // pick DTF 1 detail
```

### Line Summary Response
```json
{
  "data": {
    "shift": { "date": "2026-03-23", "shift_number": 1, "..." : "..." },
    "line": { "code": "dtf1", "label": "DTF 1", "color": "#f59e0b", "subtitle": "Building 1", "is_shared": false },
    "departments": [
      {
        "department": { "code": "print", "label": "In ấn", "unit": "file", "kpi_per_hour": 130, "factory": "FLS" },
        "staff": 12, "efficiency": 94.2, "error_rate": 2.1,
        "hourly": [{ "target": 94, "actual": 95 }]
      }
    ]
  }
}
```

### Department Detail Response
```json
{
  "data": {
    "shift": { "..." : "..." },
    "type": "department",
    "department": { "code": "print", "label": "In ấn", "unit": "file" },
    "hours": [
      { "hour_slot": "6h-7h", "target": 94, "actual": 95, "missed": false, "issues": [] },
      { "hour_slot": "9h-10h", "target": 92, "actual": 87, "missed": true,
        "issues": [{ "category": "machine", "sub_item": "Máy chính", "error": "Chạy chậm" }]
      }
    ],
    "summary": { "total_target": 754, "completed": 502, "remaining": 252 }
  }
}
```

## Seeder Data

Chạy: `php artisan db:seed --class="App\Containers\AppSection\Production\Data\Seeders\ProductionSeeder_1"`

> ⚠️ **Dependencies:**
> 1. Cần chạy `ShiftSeeder_1` trước để tạo shift data.
> 2. `DepartmentSeeder_1` được gọi tự động qua `$this->call()`.

- **4 production lines**: DTF1, DTF2, DTG, **Pick** (is_shared)
- **12 departments**: Delegated → `DepartmentSeeder_1` (Department Container)
- **96 hourly records**: 12 depts × 8 giờ (bao gồm pick departments)
- **Auto-generated issues**: tự tạo cho giờ missed > 10% KPI (bao gồm pick)

> **Performance:** Seeder dùng batch `insert()` (5 queries) thay vì ~120 `create()` calls.

## Cross-Container Dependencies

| Direction | Container | Via |
|-----------|-----------|-----|
| → uses | Department | `Department` model (relation), `FindDepartmentsByLineIdTask`, `DepartmentTransformer`, `DepartmentSeeder_1` |
| → uses | Shift | `Shift` model (resolve), `ShiftSeeder_1` dependency |

## File Structure

```
Production/
├── Actions/
│   ├── CreateHourlyIssueAction.php
│   ├── DeleteHourlyIssueAction.php
│   ├── GetAllProductionLinesAction.php
│   ├── GetDeptDetailAction.php
│   ├── GetLineSummaryAction.php
│   ├── GetPendingIssuesAction.php
│   ├── ListHourlyIssuesAction.php
│   ├── ResolveHourlyIssueAction.php
│   ├── UnresolveHourlyIssueAction.php
│   └── UpdateHourlyIssueAction.php
├── Data/
│   ├── Migrations/ (7 files)
│   ├── Repositories/ProductionLineRepository.php
│   └── Seeders/ProductionSeeder_1.php
├── Enums/
│   └── HourlyRecordStatus.php             ← pending, active, completed
├── Jobs/
│   └── SyncHourlyRecordsJob.php           ← Scheduled sync (5 min)
├── Models/
│   ├── ProductionLine.php
│   ├── HourlyRecord.php                   ← +machine_count, +hourlyMachines()
│   ├── HourlyRecordMachine.php            ← [NEW] Pivot: DTG per-slot machine override
│   └── HourlyIssue.php
├── Providers/
│   └── ProductionServiceProvider.php      ← Schedule registration
├── Tasks/
│   ├── CreateHourlyIssueTask.php
│   ├── DeleteHourlyIssueTask.php
│   ├── FindProductionLineByIdTask.php
│   ├── GetAllLinesHourlyTask.php          ← All lines → depts → hourly
│   ├── GetAllProductionLinesTask.php
│   ├── GetDeptDetailTask.php
│   ├── GetLineSummaryTask.php
│   ├── GetPendingIssuesTask.php
│   ├── ListAllProductionLinesTask.php
│   ├── ListHourlyIssuesTask.php           ← NEW: list all issues with filters
│   ├── ResolveHourlyIssueTask.php
│   ├── SyncHourlyRecordsTask.php          ← FPlatform → hourly_records
│   ├── UnresolveHourlyIssueTask.php
│   └── UpdateHourlyIssueTask.php
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/ProductionModelsTest.php
│       └── Tasks/GetAllProductionLinesTaskTest.php
├── Traits/
│   └── InvalidatesHourlyIssueCache.php    ← Shared cache invalidation
└── UI/API/
    ├── Controllers/
    │   ├── CreateHourlyIssueController.php
    │   ├── DeleteHourlyIssueController.php
    │   ├── GetAllLinesHourlyController.php
    │   ├── GetDeptDetailController.php
    │   ├── GetLineSummaryController.php
    │   ├── GetPendingIssuesController.php
    │   ├── ListHourlyIssuesController.php  ← NEW
    │   ├── ResolveHourlyIssueController.php
    │   ├── UnresolveHourlyIssueController.php
    │   ├── UpdateHourlyIssueController.php
    │   └── ... (admin CRUD controllers)
    ├── Requests/
    │   ├── GetPendingIssuesRequest.php
    │   ├── ListHourlyIssuesRequest.php     ← NEW
    │   └── ... (other requests)
    ├── Routes/
    │   ├── ListHourlyIssues.v1.private.php ← NEW
    │   ├── GetPendingIssues.v1.private.php
    │   └── ... (existing routes)
    └── Transformers/
        ├── ProductionLineTransformer.php
        ├── HourlyRecordTransformer.php        ← +machine_count, +productivity_type, +hourlyMachines
        ├── HourlyRecordMachineTransformer.php  ← [NEW] Pivot transformer
        ├── HourlyIssueTransformer.php
        ├── PendingIssueTransformer.php
        └── ShiftTransformer.php
```
