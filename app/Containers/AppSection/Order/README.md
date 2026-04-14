# Order Container

Quản lý **tổng hợp đơn hàng** theo ca làm và theo từng production line.

## Mô tả

Container lưu trữ thống kê đơn hàng tổng hợp (TOTAL_ORDERS) và thống kê theo từng line (LINE_ORDERS). Bao gồm: tổng đơn, đã hoàn thành, còn lại, thời gian dự kiến hoàn thành, đơn gấp (rush orders), và % tiến độ.

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
| estimated_done | varchar(10) | Thời gian dự kiến: "16:30" |
| rush_completed | int | Đơn gấp đã xong |
| rush_total | int | Tổng đơn gấp |
| progress | float | % tiến độ: 57.0 |

**Indexes:** `[date, shift_number]`, `line`

## Scheduled Sync (Fplatform → order_summaries)

### `SyncOrderInventoryJob`

Job chạy định kỳ (mặc định mỗi **1 phút**, configurable) lấy tồn đơn hàng từ fplatform và cập nhật vào `order_summaries`.

| Config | Env | Default | Mô tả |
|---|---|---|---|
| `factory.order_inventory_sync_interval` | `ORDER_INVENTORY_SYNC_INTERVAL` | `1` | Interval (phút). `0` = disabled |

**Logic:**
1. Lấy `Shift::current()` — nếu không có shift active → skip
2. Gọi fplatform để lấy tồn đơn theo factory:
   - **FLS**: `GetOrderInventoryTask(FLS)` → line `dtf`
   - **PD**: `GetOrderInventoryTask(PD)` → line `dtf` + `GetDtgOrderInventoryTask` → line `dtg`
3. Upsert per-line rows (updateOrCreate theo `date + shift_number + line`)
4. Upsert per-line rows chỉ (line=null **không** được tạo bởi sync job)

**Column mapping (fplatform → order_summaries):**
| Fplatform | order_summaries | Ghi chú |
|---|---|---|
| `ton_dau` | `total` | Tổng đơn đầu ngày |
| `ton_cuoi` | `remaining` | Còn lại |
| computed | `completed` | `ton_dau - ton_cuoi` |
| computed | `progress` | `(completed / total) × 100` |

## API Endpoints

| Method | Endpoint | Auth | Historical | Mô tả |
|---|---|---|---|---|
| GET | `/v1/orders/summary?date=&shift=` | Public ✅ | ✅ | Thống kê đơn hàng (tổng + per-line) |

**Query params (optional):**

| Param | Type | Validation | Mặc định |
|---|---|---|---|
| `date` | string | `YYYY-MM-DD`, không tương lai | Hôm nay |
| `shift` | int | `1`, `2`, `3` | Ca 1 |

> Validation qua `ShiftFilterRequest` (app/Ship/Requests/) — error messages tiếng Việt.

### Optimizations
- **Caching:** Dữ liệu lịch sử cache 1 giờ (`Cache::put`), ca hiện tại không cache
- **Rate Limiting:** `throttle:60,1` (60 req/phút/IP)

### FE Integration
```typescript
import { useOrderSummary } from "@/hooks/useApi";
const { data } = useOrderSummary();                              // ca hiện tại, auto-refresh 60s
const { data } = useOrderSummary({ date: "2026-03-11", shift: 1 }); // lịch sử, no refresh
```

### Response
```json
{
  "data": {
    "total": {
      "total": 1850, "completed": 1056, "remaining": 794,
      "estimated_done": "16:30",
      "rush_orders": { "completed": 42, "total": 58 },
      "progress": 57
    },
    "per_line": [
      { "line": "dtf", "line_label": "DTF", "total": 748, "completed": 423, ... },
      { "line": "dtg", "line_label": "DTG", "total": 620, "completed": 362, ... }
    ]
  }
}
```

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
│   ├── GetOrderSummaryTask.php
│   └── SyncOrderInventoryTask.php             ← Fplatform → DB logic
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/OrderSummaryTest.php (4 methods)
│       └── Tasks/GetOrderSummaryTaskTest.php (2 methods)
└── UI/API/
    ├── Controllers/GetOrderSummaryController.php
    ├── Routes/
    │   ├── GetOrderSummary.v1.private.php
    │   └── GetOrderSummary.v1.public.php ← TV Dashboard
    └── Transformers/OrderSummaryTransformer.php
```
