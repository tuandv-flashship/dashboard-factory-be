# Order Container

Quản lý **tổng hợp đơn hàng** theo ca làm và theo từng production line.

## Mô tả

Container lưu trữ thống kê đơn hàng tổng hợp (TOTAL_ORDERS) và thống kê theo từng line (LINE_ORDERS). Bao gồm: tổng đơn, đã hoàn thành, còn lại, thời gian dự kiến hoàn thành, đơn gấp (rush orders), và % tiến độ.

> **Design:** Dùng 1 bảng duy nhất `order_summaries` với `line = NULL` cho tổng hợp, `line = 'dtf1'|'dtf2'|'dtg'` cho từng line. Thiết kế này đơn giản và hiệu quả cho dashboard overview.

## Database Schema

### `order_summaries`
| Column | Type | Mô tả |
|---|---|---|
| date | date | Ngày sản xuất |
| shift_number | tinyint | Ca làm: 1, 2, 3 |
| line | varchar(20) | `NULL` = tổng, `dtf1`/`dtf2`/`dtg` = per-line |
| line_label | varchar(50) | DTF 1, DTF 2, DTG (NULL cho tổng) |
| total | int | Tổng đơn hàng |
| completed | int | Đã hoàn thành |
| remaining | int | Còn lại |
| estimated_done | varchar(10) | Thời gian dự kiến: "16:30" |
| rush_completed | int | Đơn gấp đã xong |
| rush_total | int | Tổng đơn gấp |
| progress | float | % tiến độ: 57.0 |

**Indexes:** `[date, shift_number]`, `line`

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
      { "line": "dtf1", "line_label": "DTF 1", "total": 748, "completed": 423, ... },
      { "line": "dtf2", "line_label": "DTF 2", "total": 620, "completed": 362, ... },
      { "line": "dtg", "line_label": "DTG", "total": 482, "completed": 271, ... }
    ]
  }
}
```

## Model Scopes

- `scopeTotal()` — lấy bản ghi tổng hợp (line = NULL)
- `scopePerLine()` — lấy bản ghi per-line, sắp xếp dtf1 → dtf2 → dtg
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
├── Models/OrderSummary.php
├── Tasks/GetOrderSummaryTask.php
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
