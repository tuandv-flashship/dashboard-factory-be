# Order Container

Quản lý **tổng hợp đơn hàng** theo ca làm và theo từng production line.

## Mô tả

Container lưu trữ thống kê đơn hàng tổng hợp theo từng line (DTF, DTG). Sync job tự động lấy dữ liệu tồn đơn từ fplatform và cập nhật vào `order_summaries`.

> **Design:** Dùng 1 bảng duy nhất `order_summaries` với `line = 'dtf'|'dtg'` cho từng line. Sync job chỉ cập nhật per-line rows — FE tự tính tổng.

## Database Schema

### `order_summaries`
| Column | Type | Mô tả |
|---|---|---|
| date | date | Ngày sản xuất |
| shift_number | tinyint | Ca làm: 1, 2, 3 |
| line | varchar(20) | `NULL` = tổng, `dtf`/`dtg` = per-line |
| line_label | varchar(50) | DTF, DTG (NULL cho tổng) |
| total | int | Tổng đơn hàng (= tồn đầu, ton_dau) |
| completed | int | Đã hoàn thành (= ton_dau - ton_cuoi) |
| remaining | int | Còn lại (= tồn cuối, ton_cuoi) |
| estimated_done | varchar(10) | End time dept muộn nhất: "15:00" |
| rush_completed | int | Đơn gấp đã xong |
| rush_total | int | Tổng đơn gấp |
| progress | float | % tiến độ: 73.4 |

**Indexes:** `[date, shift_number]`, `line`

## estimated_done — Logic

`estimated_done` = **thời gian kết thúc ca của department muộn nhất**.

```sql
-- Tính trực tiếp trong SQL (1 query, 1 scalar):
MAX(
  DATE_FORMAT(
    ADDTIME(start_time, SEC_TO_TIME(work_hours * 3600 + COALESCE(meal_break_minutes, 0) * 60)),
    '%H:%i'
  )
) AS max_end_time
FROM shift_details WHERE shift_id = ?
```

**Fallback chain:**
1. `MAX(end_time)` từ shift_details → ưu tiên
2. `Shift.end_time` → nếu không có shift_details
3. `'--'` → nếu không có shift

## Scheduled Sync (Fplatform → order_summaries)

### `SyncOrderInventoryJob`

Job chạy định kỳ (mặc định mỗi **1 phút**, configurable) lấy tồn đơn hàng từ fplatform và cập nhật vào `order_summaries`.

| Config | Env | Default | Mô tả |
|---|---|---|---|
| `factory.order_inventory_sync_interval` | `ORDER_INVENTORY_SYNC_INTERVAL` | `1` | Interval (phút). `0` = disabled |

**Logic:**
1. Lấy `Shift` mới nhất hôm nay — nếu không có shift → skip
2. Tính `estimated_done` = MAX end_time từ shift_details (1 SQL query)
3. Gọi fplatform để lấy tồn đơn theo factory:
   - **FLS**: `GetOrderInventoryTask(FLS)` → line `dtf`
   - **PD**: `GetOrderInventoryTask(PD)` → line `dtf` + `GetDtgOrderInventoryTask` → line `dtg`
4. Tính hotshot: `GetHotshotOrderInventoryTask` → rush_total/rush_completed
5. Upsert per-line rows (updateOrCreate theo `date + shift_number + line`)

**Column mapping (fplatform → order_summaries):**
| Fplatform | order_summaries | Ghi chú |
|---|---|---|
| `ton_dau` | `total` | Tổng đơn đầu ngày |
| `ton_cuoi` | `remaining` | Còn lại |
| computed | `completed` | `ton_dau - ton_cuoi` |
| computed | `progress` | `(completed / total) × 100` |
| computed | `estimated_done` | MAX(ShiftDetail.end_time) |

## API Endpoints

| Method | Endpoint | Auth | Historical | Mô tả |
|---|---|---|---|---|
| GET | `/v1/orders/summary?date=&shift=` | Public ✅ | ✅ | Thống kê đơn hàng (per-line) |

### Shift Resolution

| Params truyền | Behavior |
|---|---|
| (không truyền) | Hôm nay + ca mới nhất |
| `?date=2026-04-14` | Ngày đó + ca mới nhất |
| `?date=2026-04-14&shift=1` | Exact match |

**Query params (optional):**

| Param | Type | Validation | Mặc định |
|---|---|---|---|
| `date` | string | `YYYY-MM-DD`, không tương lai | Hôm nay |
| `shift` | int | `1`, `2`, `3` | Ca mới nhất (latest) |

> Validation qua `ShiftFilterRequest` (app/Ship/Requests/) — error messages tiếng Việt.

### Caching

| Loại | TTL | Ghi chú |
|---|---|---|
| Today | 5 phút | Dữ liệu đang cập nhật |
| Historical | 1 giờ | Không thay đổi |

Sử dụng `Cache::remember` (atomic, 1 Redis call).

### Rate Limiting

`throttle:60,1` (60 req/phút/IP)

### FE Integration
```typescript
import { useOrderSummary } from "@/hooks/useApi";
const { data } = useOrderSummary();                                  // hôm nay, ca mới nhất
const { data } = useOrderSummary({ date: "2026-04-14" });            // ngày cụ thể, ca mới nhất
const { data } = useOrderSummary({ date: "2026-03-11", shift: 1 }); // lịch sử exact
```

### Response
```json
{
  "data": {
    "date": "2026-04-14",
    "shift_number": 2,
    "total": null,
    "per_line": [
      {
        "id": "hashed_id",
        "line": "dtf",
        "line_label": "DTF",
        "total": 320,
        "completed": 235,
        "remaining": 85,
        "estimated_done": "15:00",
        "rush_completed": 12,
        "rush_total": 15,
        "progress": 73.4
      },
      {
        "id": "hashed_id2",
        "line": "dtg",
        "line_label": "DTG",
        "total": 180,
        "completed": 120,
        "remaining": 60,
        "estimated_done": "15:00",
        "rush_completed": 0,
        "rush_total": 0,
        "progress": 66.7
      }
    ]
  }
}
```

> **Note:** `total` (line=null) hiện trả `null` vì sync job chỉ tạo per-line rows. FE tự tính tổng từ `per_line[]`.

## Model Scopes

- `scopeTotal()` — lấy bản ghi tổng hợp (line = NULL)
- `scopePerLine()` — lấy bản ghi per-line, sắp xếp dtf → dtg
- `scopeForShift($date, $shiftNumber)` — filter theo ngày + ca

## Seeder Data

Chạy: `php artisan db:seed --class="App\Containers\AppSection\Order\Data\Seeders\OrderSeeder_1"`

**4 records** khớp FE `data.ts`:

| Line | Total | Completed | Remaining | Est. Done | Rush | Progress |
|---|---|---|---|---|---|---|
| **Tổng** | 1,850 | 1,056 | 794 | 16:30 | 42/58 | 57% |
| DTF 1 | 748 | 423 | 325 | 15:45 | 18/24 | 57% |
| DTF 2 | 620 | 362 | 258 | 16:00 | 14/20 | 58% |
| DTG | 482 | 271 | 211 | 16:30 | 10/14 | 56% |

## File Structure

```
Order/
├── Actions/GetOrderSummaryAction.php
├── Data/
│   ├── Migrations/..._create_order_summaries_table.php
│   └── Seeders/OrderSeeder_1.php
├── Jobs/
│   └── SyncOrderInventoryJob.php              ← Scheduled sync
├── Models/OrderSummary.php
├── Providers/
│   └── OrderServiceProvider.php               ← Schedule registration
├── Tasks/
│   ├── GetOrderSummaryTask.php                ← Shift resolution logic
│   └── SyncOrderInventoryTask.php             ← Fplatform → DB + estimated_done
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/OrderSummaryTest.php (4 methods)
│       └── Tasks/GetOrderSummaryTaskTest.php (2 methods)
└── UI/API/
    ├── Controllers/GetOrderSummaryController.php  ← Cache::remember + tiered TTL
    ├── Routes/
    │   ├── GetOrderSummary.v1.private.php
    │   └── GetOrderSummary.v1.public.php          ← TV Dashboard
    └── Transformers/OrderSummaryTransformer.php
```
