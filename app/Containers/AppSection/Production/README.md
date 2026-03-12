# Production Container

Container lớn nhất — quản lý **dữ liệu sản xuất theo giờ** cho toàn bộ dây chuyền PrintDash.

## Mô tả

Container bao gồm 6 bảng xử lý: production lines, departments, shifts, hourly production records (target vs actual), KPI miss issues, và pick hourly records. Đây là data source chính cho dashboard realtime và trang chi tiết department.

## Database Schema (6 tables)

### `production_lines`
| Column | Type | Mô tả |
|---|---|---|
| code | varchar(20) | Unique: `dtf1`, `dtf2`, `dtg` |
| label | varchar(50) | DTF 1, DTF 2, DTG |
| color | varchar(20) | Hex: #f59e0b, #14b8a6, #8b5cf6 |
| building | varchar | Building 1, Building 2 (nullable) |
| sort_order | smallint | Thứ tự sắp xếp |

### `departments`
| Column | Type | Mô tả |
|---|---|---|
| production_line_id | FK | → production_lines |
| code | varchar(30) | print, cut, mockup, pack_ship |
| label / label_en | varchar(50) | In ấn / Print, Cắt / Cut, ... |
| icon | varchar(30) | Lucide icon: Printer, Scissors, Layers, Package |
| unit | varchar(20) | Đơn vị đo: `files`, `áo`, `prints` |
| | | **Unique constraint:** production_line_id + code |

### `shifts`
| Column | Type | Mô tả |
|---|---|---|
| date | date | Ngày sản xuất |
| shift_number | tinyint | Ca làm: 1, 2, 3 |
| start_time / end_time | time | 06:00 → 14:00 |
| supervisor | varchar(100) | Quản đốc: Nguyễn Văn Minh |
| is_active | boolean | Ca đang hoạt động |
| | | **Unique constraint:** date + shift_number |

### `hourly_records` ⭐ Bảng chính
| Column | Type | Mô tả |
|---|---|---|
| shift_id | FK | → shifts |
| department_id | FK | → departments |
| hour_slot | varchar(10) | "6h-7h", "7h-8h", ... |
| hour_index | tinyint | 0-7 (index trong ca) |
| target | int | KPI mục tiêu giờ này |
| actual | int (nullable) | Thực đạt (null = giờ tương lai) |
| staff | smallint | Số nhân viên |
| efficiency | float | % hiệu suất: 94.2 |
| error_rate | float | % lỗi: 2.1 |
| | | **Unique constraint:** shift_id + department_id + hour_index |

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

### `pick_hourly_records`
| Column | Type | Mô tả |
|---|---|---|
| shift_id | FK | → shifts |
| production_line_id | FK | → production_lines (không qua departments) |
| hour_slot / hour_index | | Tương tự hourly_records |
| target / actual | | Tương tự hourly_records |
| staff / efficiency / error_rate | | Tương tự hourly_records |
| total_picked | int | Tổng số đã pick |
| | | **Unique constraint:** shift_id + production_line_id + hour_index |

> **Tại sao Pick tách riêng?** FE thiết kế Pick là bộ phận chia sẻ giữa 3 lines, tracking theo line chứ không theo department. Nên dùng bảng riêng với FK → production_lines thay vì departments.

## API Endpoints

| Method | Endpoint | Auth | Historical | Mô tả |
|---|---|---|---|---|
| GET | `/v1/production/lines` | Public ✅ | ❌ | Tất cả production lines + departments |
| GET | `/v1/production/lines/{line}?date=&shift=` | Public ✅ | ✅ | Summary toàn bộ line (hourly + pick) |
| GET | `/v1/production/lines/{line}/departments/{dept}?date=&shift=` | Public ✅ | ✅ | Chi tiết 1 dept (hourly + issues) |

**Path params:**
- `line`: `dtf1`, `dtf2`, `dtg`
- `dept`: `print`, `cut`, `mockup`, `pack_ship`, `pick`, `dtg_print`

**Query params (optional):**

| Param | Type | Validation | Mặc định |
|---|---|---|---|
| `date` | string | `YYYY-MM-DD`, không tương lai | Hôm nay |
| `shift` | int | `1`, `2`, `3` | Ca hiện tại |

> Validation qua `ShiftFilterRequest` (app/Ship/Requests/) — error messages tiếng Việt.

### Optimizations
- **Caching:** Dữ liệu lịch sử cache 1 giờ (`Cache::put`), ca hiện tại không cache
- **Rate Limiting:** `throttle:60,1` (60 req/phút/IP) trên tất cả public routes
- **Eager Loading:** `->with('issues')` trong `GetDeptDetailTask`

### FE Integration
```typescript
import { useProductionLines, useLineSummary, useDeptDetail } from "@/hooks/useApi";
const { data: lines } = useProductionLines();
const { data: dtf1 } = useLineSummary("dtf1");                           // ca hiện tại, auto-refresh 30s
const { data: dtf1 } = useLineSummary("dtf1", { date: "2026-03-11", shift: 1 }); // lịch sử, no refresh
const { data: detail } = useDeptDetail("dtf1", "print", { date: "2026-03-11", shift: 1 });
```

### Line Summary Response
```json
{
  "data": {
    "shift": { "date": "2026-03-11", "shift_number": 1, ... },
    "line": { "code": "dtf1", "label": "DTF 1", "color": "#f59e0b" },
    "departments": [
      {
        "department": { "code": "print", "label": "In ấn", ... },
        "staff": 12, "efficiency": 94.2, "error_rate": 2.1,
        "hourly": [{ "target": 94, "actual": 95 }, ...]
      }
    ],
    "pick": { "staff": 3, "efficiency": 92.0, "hourly": [...] }
  }
}
```

### Department Detail Response
```json
{
  "data": {
    "shift": { ... },
    "type": "department",
    "hours": [
      {
        "hour_slot": "6h-7h", "target": 94, "actual": 95, "missed": false,
        "issues": []
      },
      {
        "hour_slot": "9h-10h", "target": 92, "actual": 87, "missed": true,
        "issues": [
          { "category": "machine", "sub_item": "Máy chính", "error": "Chạy chậm", ... }
        ]
      }
    ],
    "summary": { "total_target": 754, "completed": 502, "remaining": 252, ... }
  }
}
```

## Seeder Data

Chạy: `php artisan db:seed --class="App\Containers\AppSection\Production\Data\Seeders\ProductionSeeder_1"`

- **3 production lines**: DTF1 (#f59e0b), DTF2 (#14b8a6), DTG (#8b5cf6)
- **9 departments**: DTF1×4 + DTF2×4 + DTG×1
- **1 shift**: Hôm nay, Ca 1 (06:00-14:00), Quản đốc: Nguyễn Văn Minh
- **72 hourly records**: 9 depts × 8 giờ — giá trị khớp chính xác FE `data.ts`
- **24 pick hourly records**: 3 lines × 8 giờ
- **Auto-generated issues**: tự tạo cho giờ missed > 10% KPI

> **Performance:** Seeder dùng batch `insert()` (4 queries) thay vì ~100 `create()` calls.

## File Structure

```
Production/
├── Actions/
│   ├── GetAllProductionLinesAction.php
│   ├── GetDeptDetailAction.php
│   └── GetLineSummaryAction.php
├── Data/
│   ├── Migrations/ (6 files)
│   └── Seeders/ProductionSeeder_1.php
├── Models/
│   ├── ProductionLine.php
│   ├── Department.php
│   ├── Shift.php (static resolve(), current(), forDate())
│   ├── HourlyRecord.php
│   ├── HourlyIssue.php
│   └── PickHourlyRecord.php
├── Tasks/
│   ├── GetAllProductionLinesTask.php
│   ├── GetDeptDetailTask.php
│   └── GetLineSummaryTask.php
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/ProductionModelsTest.php (9 methods)
│       └── Tasks/GetAllProductionLinesTaskTest.php
└── UI/API/
    ├── Controllers/ (3 controllers)
    ├── Routes/
    │   ├── GetAllProductionLines.v1.private.php
    │   ├── GetAllProductionLines.v1.public.php   ← TV Dashboard
    │   ├── GetLineSummary.v1.private.php
    │   ├── GetLineSummary.v1.public.php          ← TV Dashboard
    │   ├── GetDeptDetail.v1.private.php
    │   └── GetDeptDetail.v1.public.php           ← TV Dashboard
    └── Transformers/
        ├── ProductionLineTransformer.php (includes departments)
        ├── DepartmentTransformer.php
        ├── ShiftTransformer.php
        ├── HourlyRecordTransformer.php (includes issues, computed 'missed')
        ├── HourlyIssueTransformer.php
        └── PickHourlyRecordTransformer.php
```
