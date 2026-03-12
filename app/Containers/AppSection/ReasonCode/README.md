# ReasonCode Container

Quản lý động **mã lý do không đạt KPI** cho hệ thống giám sát sản xuất PrintDash.

## Mô tả

Container này thay thế các hàm hardcoded trong FE (`departmentData.ts`) bằng hệ thống CRUD động, cho phép admin thêm/sửa/xóa lý do mà không cần dev can thiệp. Cấu trúc phân cấp 3 lớp:

```
ReasonCategory (4 loại)
├── ReasonSubItem (scoped theo line/dept)
└── ReasonError (scoped theo dept)
```

## Database Schema

### `reason_categories`
| Column | Type | Mô tả |
|---|---|---|
| code | varchar(50) | Unique: `machine`, `human`, `material`, `process` |
| label | varchar | Tên hiển thị: Máy móc, Con người, Nguyên vật liệu, Qui trình |
| label_en | varchar | English label |
| icon | varchar(50) | Lucide icon name: Cog, Users, Package, GitBranch |
| color | varchar(20) | Hex color: #ef4444, #f59e0b, #8b5cf6, #14b8a6 |
| sort_order | smallint | Thứ tự sắp xếp |
| is_active | boolean | Trạng thái hoạt động |

### `reason_sub_items`
| Column | Type | Mô tả |
|---|---|---|
| category_id | FK | → reason_categories |
| code | varchar(100) | VD: `machine-dtf1-print-dtf-01`, `human-absent`, `mat-ink-white` |
| label | varchar | Tên hiển thị: DTF-01, Vắng mặt / Nghỉ phép, Mực trắng |
| scope_type | varchar(20) | `global` \| `per_department` \| `per_line_department` |
| scope_line | varchar(20) | Nullable: dtf1, dtf2, dtg |
| scope_dept | varchar(20) | Nullable: print, cut, mockup, pack_ship, pick, dtg_print |
| sort_order | smallint | Thứ tự sắp xếp |
| is_active | boolean | Trạng thái hoạt động |

**Scope logic:**
- `global`: Hiện cho tất cả line/dept (VD: "Vắng mặt / Nghỉ phép")
- `per_department`: Filter theo dept (VD: "Mực trắng" chỉ cho dept `print`)
- `per_line_department`: Filter theo cả line + dept (VD: "DTF-01" chỉ cho `dtf1/print`)

### `reason_errors`
| Column | Type | Mô tả |
|---|---|---|
| category_id | FK | → reason_categories |
| code | varchar(100) | VD: `err-breakdown`, `herr-late`, `merr-outstock` |
| label | varchar | Tên lỗi cụ thể: Hỏng máy, Đi trễ, Hết hàng |
| scope_dept | varchar(20) | Nullable: null = áp dụng tất cả dept |
| sort_order | smallint | Thứ tự sắp xếp |
| is_active | boolean | Trạng thái hoạt động |

## API Endpoints

| Method | Endpoint | Auth | Mô tả |
|---|---|---|---|
| GET | `/v1/reason-codes` | Public ✅ | Reason codes filter theo context |

**Query params:**
- `line` (optional): `dtf1`, `dtf2`, `dtg`
- `dept` (optional): `print`, `cut`, `mockup`, `pack_ship`, `pick`, `dtg_print`

**Response:** Tree gồm categories → sub_items + errors đã filter theo context.

### FE Integration
```typescript
import { useReasonCodes } from "@/hooks/useApi";
const { data: reasons } = useReasonCodes("dtf1", "print"); // staleTime 5min
// reasons = tree: categories[] → sub_items[] + errors[]
```

## Seeder Data

Chạy seeder: `php artisan db:seed --class="App\Containers\AppSection\ReasonCode\Data\Seeders\ReasonCodeSeeder_1"`

- **4 categories**: machine, human, material, process
- **~50 machine sub-items**: per line + dept (DTF1: 19, DTF2: 12, DTG: 5)
- **5 human sub-items**: global
- **~18 material sub-items**: per dept (print: 4, dtg_print: 3, cut: 2, mockup: 3, pack_ship: 4, pick: 2)
- **6 process sub-items**: global
- **~30 machine errors**: 5 common + dept-specific + "Khác"
- **6 human errors**, **6 material errors**, **7 process errors**: global

## File Structure

```
ReasonCode/
├── Actions/GetReasonCodesAction.php
├── Data/
│   ├── Migrations/
│   │   ├── ..._create_reason_categories_table.php
│   │   ├── ..._create_reason_sub_items_table.php
│   │   └── ..._create_reason_errors_table.php
│   └── Seeders/ReasonCodeSeeder_1.php
├── Enums/ScopeType.php
├── Models/
│   ├── ReasonCategory.php
│   ├── ReasonSubItem.php (scopeForContext)
│   └── ReasonError.php (scopeForDept)
├── Tasks/GetReasonCodesForContextTask.php
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/ReasonCodeModelsTest.php (5 methods)
│       └── Tasks/GetReasonCodesForContextTaskTest.php (2 methods)
└── UI/API/
    ├── Controllers/GetReasonCodesController.php
    ├── Requests/GetReasonCodesRequest.php
    ├── Routes/
    │   ├── GetReasonCodes.v1.private.php
    │   └── GetReasonCodes.v1.public.php ← TV Dashboard
    └── Transformers/
        ├── ReasonCategoryTransformer.php
        ├── ReasonSubItemTransformer.php
        └── ReasonErrorTransformer.php
```
