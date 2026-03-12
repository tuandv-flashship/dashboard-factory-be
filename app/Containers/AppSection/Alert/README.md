# Alert Container

Quản lý **cảnh báo sản xuất** realtime cho toàn bộ xưởng PrintDash.

## Mô tả

Container lưu trữ và phục vụ các cảnh báo sản xuất theo 3 mức độ nghiêm trọng (critical/warning/info). Alerts có thể gắn với 1 production line cụ thể hoặc áp dụng cho toàn bộ xưởng (`line = "all"`). Hỗ trợ đánh dấu đã xử lý (resolved).

## Database Schema

### `alerts`
| Column | Type | Mô tả |
|---|---|---|
| severity | varchar(20) | `critical` \| `warning` \| `info` |
| department | varchar(50) | Tên dept hiển thị: Print, Pack & Ship, Mock Up |
| time | time | Thời điểm phát sinh: 10:42 |
| message | text | Nội dung: "Máy in DTF-03 ngừng hoạt động — cần bảo trì" |
| line | varchar(20) | `dtf1`, `dtf2`, `dtg`, `all` |
| is_resolved | boolean | Đã xử lý chưa (default: false) |
| resolved_at | timestamp | Thời điểm xử lý (nullable) |

**Indexes:** `[line, severity]`, `is_resolved`

## API Endpoints

| Method | Endpoint | Auth | Mô tả |
|---|---|---|---|
| GET | `/v1/alerts` | Public ✅ | Cảnh báo chưa xử lý, mới nhất trước |

**Query params:** `line` (optional): `dtf1`, `dtf2`, `dtg` — bao gồm alerts `line=all`

### FE Integration
```typescript
import { useAlerts } from "@/hooks/useApi";
const { data: alerts } = useAlerts();        // all alerts, auto-refresh 10s
const { data: dtf1 } = useAlerts("dtf1");    // dtf1 + all
```

## Model Scopes

- `scopeUnresolved()` — chỉ alerts chưa xử lý
- `scopeForLine($line)` — filter theo line (bao gồm `line='all'`)
- `scopeBySeverity($severity)` — filter theo mức độ

## Seeder Data

Chạy: `php artisan db:seed --class="App\Containers\AppSection\Alert\Data\Seeders\AlertSeeder_1"`

**5 alerts** khớp FE `data.ts`:

| Severity | Dept | Time | Line | Message |
|---|---|---|---|---|
| 🔴 critical | Print | 10:42 | dtf1 | Máy in DTF-03 ngừng hoạt động — cần bảo trì |
| 🟡 warning | Pack & Ship | 10:15 | dtf1 | Máy dán nhãn LBL-02 đang bảo trì — dự kiến 30 phút |
| 🟡 warning | Mock Up | 09:45 | dtf2 | Máy SEW-03 ngừng — cần thay kim |
| 🟡 warning | Print | 08:50 | dtg | Mực trắng DTG Apollo còn 15% — đã đặt hàng bổ sung |
| 🔵 info | Pack & Ship | 11:05 | all | Nhãn vận chuyển sắp hết — còn 200 cái |

## File Structure

```
Alert/
├── Actions/GetAlertsAction.php
├── Data/
│   ├── Migrations/..._create_alerts_table.php
│   └── Seeders/AlertSeeder_1.php
├── Models/Alert.php
├── Tasks/GetAlertsTask.php
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/AlertTest.php (5 methods)
│       └── Tasks/GetAlertsTaskTest.php (3 methods)
└── UI/API/
    ├── Controllers/GetAlertsController.php
    ├── Routes/
    │   ├── GetAlerts.v1.private.php
    │   └── GetAlerts.v1.public.php ← TV Dashboard
    └── Transformers/AlertTransformer.php
```
