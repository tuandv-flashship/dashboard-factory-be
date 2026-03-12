# Quality Container

Quản lý **dữ liệu kiểm tra chất lượng (QC)** theo ca sản xuất.

## Mô tả

Container lưu trữ kết quả kiểm tra chất lượng tổng hợp cho mỗi ca: tỷ lệ đạt, số lượng đã kiểm, đạt, không đạt, và tỷ lệ lỗi trung bình. Dữ liệu hiển thị trên dashboard overview và có thể mở rộng để tracking chi tiết per-line hoặc per-department.

## Database Schema

### `quality_records`
| Column | Type | Mô tả |
|---|---|---|
| date | date | Ngày sản xuất |
| shift_number | tinyint | Ca làm: 1, 2, 3 |
| pass_rate | float | Tỷ lệ đạt: 98.1 (%) |
| inspected | int | Tổng đã kiểm: 1056 |
| passed | int | Đạt: 1036 |
| failed | int | Không đạt: 20 |
| avg_error_rate | float | Tỷ lệ lỗi TB: 1.9 (%) |
| | | **Unique constraint:** date + shift_number |

## API Endpoints

| Method | Endpoint | Auth | Historical | Mô tả |
|---|---|---|---|---|
| GET | `/v1/quality?date=&shift=` | Public ✅ | ✅ | QC data (404 nếu chưa có) |

**Query params (optional):**

| Param | Type | Validation | Mặc định |
|---|---|---|---|
| `date` | string | `YYYY-MM-DD`, không tương lai | Hôm nay |
| `shift` | int | `1`, `2`, `3` | Ca mới nhất |

> Validation qua `ShiftFilterRequest` (app/Ship/Requests/) — error messages tiếng Việt.

### Optimizations
- **Caching:** Dữ liệu lịch sử cache 1 giờ (`Cache::put`), ca hiện tại không cache
- **Rate Limiting:** `throttle:60,1` (60 req/phút/IP)

### FE Integration
```typescript
import { useQualityData } from "@/hooks/useApi";
const { data: quality } = useQualityData();                              // ca hiện tại, auto-refresh 60s
const { data: quality } = useQualityData({ date: "2026-03-11", shift: 1 }); // lịch sử, no refresh
```

> **Lưu ý:** API trả `snake_case` (pass_rate), FE nhận `camelCase` (passRate) — API client tự động transform.

**Response:**
```json
{
  "data": {
    "pass_rate": 98.1,
    "inspected": 1056,
    "passed": 1036,
    "failed": 20,
    "avg_error_rate": 1.9
  }
}
```

**404** nếu không có dữ liệu cho ca hiện tại.

## Model Methods

- `QualityRecord::resolve($date, $shiftNumber)` — static helper: date+shift → exact match; chỉ date → latest shift; null → record mới nhất hôm nay
- `QualityRecord::current()` — shortcut cho `resolve(null, null)`

## Seeder Data

Chạy: `php artisan db:seed --class="App\Containers\AppSection\Quality\Data\Seeders\QualitySeeder_1"`

**1 record** khớp FE `data.ts` QUALITY_DATA:

| Metric | Value |
|---|---|
| Pass Rate | 98.1% |
| Inspected | 1,056 |
| Passed | 1,036 |
| Failed | 20 |
| Avg Error Rate | 1.9% |

## Mở rộng tương lai

Container này có thể mở rộng để:
- Thêm `line` column → tracking QC per production line
- Thêm bảng `quality_defects` → chi tiết từng lỗi (loại lỗi, department, machine gây lỗi)
- Thêm bảng `quality_inspections` → log từng lần kiểm tra

## File Structure

```
Quality/
├── Actions/GetQualityDataAction.php
├── Data/
│   ├── Migrations/..._create_quality_records_table.php
│   └── Seeders/QualitySeeder_1.php
├── Models/QualityRecord.php
├── Tasks/GetQualityDataTask.php
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/QualityRecordTest.php (5 methods)
│       └── Tasks/GetQualityDataTaskTest.php (2 methods)
└── UI/API/
    ├── Controllers/GetQualityDataController.php
    ├── Routes/
    │   ├── GetQualityData.v1.private.php
    │   └── GetQualityData.v1.public.php ← TV Dashboard
    └── Transformers/QualityRecordTransformer.php
```
