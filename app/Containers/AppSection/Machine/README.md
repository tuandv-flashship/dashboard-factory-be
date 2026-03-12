# Machine Container

Quản lý **danh sách máy móc thiết bị** trên toàn bộ dây chuyền sản xuất PrintDash.

## Mô tả

Container quản lý tất cả máy móc/thiết bị trong xưởng in — từ máy in DTF, máy cắt, máy may, đến thiết bị đóng gói và scanner pick. Mỗi máy gắn với 1 `line` + `department` cụ thể và có trạng thái vận hành realtime.

## Database Schema

### `machines`
| Column | Type | Mô tả |
|---|---|---|
| code | varchar(50) | Unique ID: `dtf1-dtg01`, `dtf2-cut01`, `dtg-apollo01` |
| name | varchar(100) | Tên hiển thị: DTF-01, CUT-01, Apollo |
| status | varchar(20) | `online` \| `offline` \| `maintenance` |
| department | varchar(30) | print, cut, mockup, pack_ship, pick |
| line | varchar(20) | dtf1, dtf2, dtg |
| description | varchar | Mô tả bổ sung (nullable) |
| sort_order | smallint | Thứ tự sắp xếp |
| is_active | boolean | Trạng thái hoạt động |

## API Endpoints

| Method | Endpoint | Auth | Mô tả |
|---|---|---|---|
| GET | `/v1/machines` | Public ✅ | Tất cả máy active, sort by sort_order |
| GET | `/v1/machines/{line}` | Public ✅ | Máy theo line (`dtf1`, `dtf2`, `dtg`) |
| PATCH | `/v1/machines/{id}/status` | Private 🔒 | Cập nhật trạng thái máy |

**PATCH body:**
```json
{ "status": "online" | "offline" | "maintenance" }
```

### FE Integration
```typescript
import { useMachines, useMachinesByLine } from "@/hooks/useApi";
const { data: machines } = useMachines();        // auto-refresh 30s
const { data: dtf1 } = useMachinesByLine("dtf1"); // filter by line
```

## Seeder Data

Chạy: `php artisan db:seed --class="App\Containers\AppSection\Machine\Data\Seeders\MachineSeeder_1"`

**39 máy** khớp chính xác FE `data.ts` + `departmentData.ts`:

| Line | Dept | Machines | Số lượng |
|---|---|---|---|
| DTF1 | print | DTF-01..04, HP-01..02 | 6 |
| DTF1 | cut | CUT-01..03 | 3 |
| DTF1 | mockup | SEW-01..04 | 4 |
| DTF1 | pack_ship | PKG-01..02, LBL-01..02 | 4 |
| DTF1 | pick | SCAN-01..02, CART-01..02 | 4 |
| DTF2 | print | DTF-01..03, HP-01 | 4 |
| DTF2 | cut | CUT-01..02 | 2 |
| DTF2 | mockup | SEW-01..03 | 3 |
| DTF2 | pack_ship | PKG-01..02, LBL-01 | 3 |
| DTF2 | pick | SCAN-03, CART-03 | 2 |
| DTG | print | Apollo, Atlas-01..02 | 3 |
| DTG | pick | SCAN-04, CART-04 | 2 |

**Trạng thái mặc định:** `DTF-03 (dtf1/print)` = offline, `LBL-02 (dtf1/pack_ship)` = maintenance, `SEW-03 (dtf2/mockup)` = maintenance, còn lại = online.

## Model Scopes

- `scopeForLine($line)` — filter theo production line
- `scopeForDepartment($dept)` — filter theo department
- `scopeByStatus($status)` — filter theo trạng thái
- `scopeActive()` — chỉ máy đang active (is_active = true)

## File Structure

```
Machine/
├── Actions/
│   ├── GetAllMachinesAction.php
│   ├── GetMachinesByLineAction.php
│   └── UpdateMachineStatusAction.php
├── Data/
│   ├── Migrations/..._create_machines_table.php
│   └── Seeders/MachineSeeder_1.php
├── Enums/MachineStatus.php
├── Models/Machine.php
├── Tasks/
│   ├── GetAllMachinesTask.php
│   ├── GetMachinesByLineTask.php
│   └── UpdateMachineStatusTask.php
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/MachineTest.php (10 methods)
│       └── Tasks/MachineTasksTest.php
└── UI/API/
    ├── Controllers/
    │   ├── GetAllMachinesController.php
    │   ├── GetMachinesByLineController.php
    │   └── UpdateMachineStatusController.php
    ├── Requests/UpdateMachineStatusRequest.php
    ├── Routes/
    │   ├── GetAllMachines.v1.private.php
    │   ├── GetAllMachines.v1.public.php      ← TV Dashboard
    │   ├── GetMachinesByLine.v1.private.php
    │   ├── GetMachinesByLine.v1.public.php   ← TV Dashboard
    │   └── UpdateMachineStatus.v1.private.php
    └── Transformers/MachineTransformer.php
```
