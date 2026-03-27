# KpiRatingLevel Container

Quản lý **mức đánh giá KPI** (KPI Rating Levels) cho hệ thống, cho phép admin cấu hình các mức đánh giá hiệu suất (Xuất sắc, Đạt, Trung bình, Yếu, Chưa đạt) với ngưỡng điểm, màu sắc, và khoảng thời gian hiệu lực.

## Mô tả

Container quản lý **mức đánh giá KPI** theo cấu trúc parent-child:

```
KpiRatingLevel (parent — mức đánh giá)
├── name: "Mức đánh giá 2026"
├── effective_from / effective_until: khoảng thời gian hiệu lực
├── status: computed (pending / active / expired)
└── KpiRatingLevelDetail[] (child — các cấp độ)
    ├── Xuất sắc  (≥ 100%, nền xanh đậm)
    ├── Đạt       (≥ 95%,  nền xanh lá)
    ├── Trung bình (≥ 90%, nền vàng)
    ├── Yếu       (≥ 85%,  nền nâu)
    └── Chưa đạt  (< 85%,  nền đỏ)
```

### Business Rules

1. **Status computed** — không lưu DB, tính tự động:
   - `effective_from > today` → **Chưa áp dụng** (`pending`)
   - `effective_from <= today && (effective_until IS NULL || effective_until >= today)` → **Đang áp dụng** (`active`)
   - `effective_until < today` → **Hết hiệu lực** (`expired`)

2. **Ngày hết hiệu lực = null** → Có hiệu lực vô thời hạn

3. **Mặc định 5 cấp độ** khi tạo mới: Xuất sắc, Đạt, Trung bình, Yếu, Chưa đạt

4. **Mức "Chưa đạt"** (mức cuối) chỉ cho sửa text, **không được xóa** (validate ở FE)

5. **Validate unique** trong cùng 1 rating level: `level_name`, `min_score`

6. **Sắp xếp** danh sách theo `effective_from` giảm dần (mới nhất trước)

---

## Database Schema

### `kpi_rating_levels`

| Column | Type | Nullable | Mô tả |
|---|---|---|---|
| id | bigint PK | | Auto increment |
| name | varchar(255) | ❌ | Tên mức đánh giá. VD: "Mức đánh giá 2026" |
| effective_from | date | ❌ | Ngày bắt đầu áp dụng |
| effective_until | date | ✅ | Ngày hết hiệu lực. `NULL` = vô thời hạn |
| description | text | ✅ | Mô tả thêm |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** `effective_from`, `effective_until`

### `kpi_rating_level_details`

| Column | Type | Nullable | Mô tả |
|---|---|---|---|
| id | bigint PK | | Auto increment |
| rating_level_id | FK → kpi_rating_levels | ❌ | Cascade on delete |
| level_name | varchar(255) | ❌ | Tên cấp độ: "Xuất sắc", "Đạt", "Trung bình", "Yếu", "Chưa đạt" |
| bg_color | varchar(20) | ❌ | Màu nền HEX: `#006400`, `#228B22`, `#DAA520`, `#8B4513`, `#8B0000` |
| text_color | varchar(20) | ❌ | Màu chữ HEX: `#FFFFFF` |
| min_score | decimal(5,2) | ❌ | Ngưỡng điểm đạt: `100.00`, `95.00`, `90.00`, `85.00` |
| operator | varchar(5) | ❌ | Toán tử so sánh: `>=` hoặc `<`. Default: `>=` |
| requires_reason | boolean | ❌ | Yêu cầu nhập lý do khi KPI ở mức này. Default: `false` |
| warn_staff_shortage | boolean | ❌ | Cảnh báo thiếu nhân sự làm việc. Default: `false` |
| sort_order | smallint | ❌ | Thứ tự hiển thị. Default: `0` |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Unique constraints:**
- `(rating_level_id, level_name)` — không trùng tên cấp độ trong cùng 1 mức
- `(rating_level_id, min_score)` — không trùng điểm đạt trong cùng 1 mức

---

## Status Logic

Status là **computed attribute** trên Model, không lưu DB:

```php
// App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel

protected function status(): Attribute
{
    return Attribute::get(function (): KpiRatingLevelStatus {
        $today = Carbon::today();

        if ($this->effective_from > $today) {
            return KpiRatingLevelStatus::PENDING;    // Chưa áp dụng
        }

        if ($this->effective_until !== null && $this->effective_until < $today) {
            return KpiRatingLevelStatus::EXPIRED;    // Hết hiệu lực
        }

        return KpiRatingLevelStatus::ACTIVE;         // Đang áp dụng
    });
}
```

### Ví dụ minh họa (giả sử hôm nay = 2026-03-25)

| Name | effective_from | effective_until | → Status |
|---|---|---|---|
| Mức đánh giá 2026 | 2026-04-01 | `NULL` | **pending** (chưa tới ngày áp dụng) |
| Mức đánh giá 2025 | 2025-01-01 | `NULL` | **active** (đang áp dụng, vô thời hạn) |
| Mức đánh giá 2024 | 2024-01-01 | 2025-03-01 | **expired** (đã hết hiệu lực) |

---

## API Endpoints

### Public API

| Method | Endpoint | Auth | Mô tả |
|---|---|---|---|
| GET | `/v1/kpi-rating-levels/active` | 🔓 Public (throttle: 60/min) | Lấy mức đánh giá đang active |

**Logic:**
1. Tìm record có `effective_from <= today AND (effective_until IS NULL OR effective_until >= today)`, ưu tiên `effective_from` mới nhất
2. Nếu không có → trả về **mức mặc định** từ config `appSection-kpiRatingLevel.default`

**Response — khi có record active:**
```json
{
  "data": {
    "id": "HASHED_ID",
    "name": "Mức đánh giá 2026",
    "effective_from": "2026-04-01",
    "effective_until": null,
    "status": "active",
    "description": null,
    "created_at": "2026-03-25T03:00:00.000000Z",
    "updated_at": "2026-03-25T03:00:00.000000Z",
    "details": {
      "data": [
        {
          "id": "HASHED_ID",
          "level_name": "Xuất sắc",
          "bg_color": "#006400",
          "text_color": "#FFFFFF",
          "min_score": 100.0,
          "operator": ">=",
          "requires_reason": false,
          "warn_staff_shortage": false,
          "sort_order": 1
        },
        {
          "id": "HASHED_ID",
          "level_name": "Đạt",
          "bg_color": "#228B22",
          "text_color": "#FFFFFF",
          "min_score": 95.0,
          "operator": ">=",
          "requires_reason": false,
          "warn_staff_shortage": false,
          "sort_order": 2
        }
      ]
    }
  }
}
```

**Response — khi không có record active (fallback default):**
```json
{
  "data": {
    "name": "Mặc định",
    "details": [
      { "level_name": "Xuất sắc",   "bg_color": "#006400", "text_color": "#FFFFFF", "min_score": 100, "operator": ">=", "requires_reason": false, "warn_staff_shortage": false, "sort_order": 1 },
      { "level_name": "Đạt",        "bg_color": "#228B22", "text_color": "#FFFFFF", "min_score": 95,  "operator": ">=", "requires_reason": false, "warn_staff_shortage": false, "sort_order": 2 },
      { "level_name": "Trung bình", "bg_color": "#DAA520", "text_color": "#FFFFFF", "min_score": 90,  "operator": ">=", "requires_reason": true,  "warn_staff_shortage": true,  "sort_order": 3 },
      { "level_name": "Yếu",        "bg_color": "#8B4513", "text_color": "#FFFFFF", "min_score": 85,  "operator": ">=", "requires_reason": true,  "warn_staff_shortage": true,  "sort_order": 4 },
      { "level_name": "Chưa đạt",   "bg_color": "#8B0000", "text_color": "#FFFFFF", "min_score": 85,  "operator": "<",  "requires_reason": true,  "warn_staff_shortage": true,  "sort_order": 5 }
    ]
  }
}
```

---

### Admin API — Query Parameters (Apiato RequestCriteria)

Tất cả admin List endpoints hỗ trợ các query params sau (powered by `addRequestCriteria()`):

| Param | Mô tả | Ví dụ |
|---|---|---|
| `search` | Tìm kiếm theo `$fieldSearchable` | `?search=name:2026` |
| `searchFields` | Override operator cho từng field | `?searchFields=name:like` |
| `searchJoin` | Kết hợp điều kiện `and` / `or` | `?searchJoin=and` (default: `and`) |
| `orderBy` | Sắp xếp theo field | `?orderBy=effective_from` |
| `sortedBy` | Chiều sắp xếp | `?sortedBy=desc` (default: `desc`) |
| `page` | Trang hiện tại | `?page=2` |
| `limit` | Số item/trang | `?limit=25` (default: 15) |
| `filter` | Chỉ trả về các fields cụ thể | `?filter=id;name;status` |

#### `$fieldSearchable` — KpiRatingLevel

| Field | Operator | Ví dụ |
|---|---|---|
| `name` | `like` | `?search=name:2026` |

#### Ví dụ kết hợp

```bash
# List tất cả, mới nhất trước
GET /v1/admin/kpi-rating-levels

# Tìm theo tên
GET /v1/admin/kpi-rating-levels?search=name:2026

# Phân trang
GET /v1/admin/kpi-rating-levels?limit=10&page=2

# Chi tiết 1 record
GET /v1/admin/kpi-rating-levels/{id}
```

---

### Admin API — KpiRatingLevel CRUD

Tất cả yêu cầu `auth:api` + permission `kpi-rating-levels.*`.

| Method | Endpoint | Permission | Mô tả |
|---|---|---|---|
| GET | `/v1/admin/kpi-rating-levels` | `kpi-rating-levels.index` | Danh sách (paginated, searchable, default sort by `effective_from desc`) |
| GET | `/v1/admin/kpi-rating-levels/{id}` | `kpi-rating-levels.index` | Chi tiết 1 record (kèm details) |
| POST | `/v1/admin/kpi-rating-levels` | `kpi-rating-levels.create` | Tạo mới (parent + details) |
| PATCH | `/v1/admin/kpi-rating-levels/{id}` | `kpi-rating-levels.edit` | Cập nhật (parent + sync details) |
| DELETE | `/v1/admin/kpi-rating-levels/{id}` | `kpi-rating-levels.destroy` | Xóa (cascade xóa details) |

#### POST /v1/admin/kpi-rating-levels — Tạo mới

```json
{
  "name": "Mức đánh giá 2026",
  "effective_from": "2026-04-01",
  "effective_until": null,
  "description": "Áp dụng từ Q2/2026",
  "details": [
    {
      "level_name": "Xuất sắc",
      "bg_color": "#006400",
      "text_color": "#FFFFFF",
      "min_score": 100,
      "operator": ">=",
      "requires_reason": false,
      "warn_staff_shortage": false,
      "sort_order": 1
    },
    {
      "level_name": "Đạt",
      "bg_color": "#228B22",
      "text_color": "#FFFFFF",
      "min_score": 95,
      "operator": ">=",
      "requires_reason": false,
      "warn_staff_shortage": false,
      "sort_order": 2
    },
    {
      "level_name": "Trung bình",
      "bg_color": "#DAA520",
      "text_color": "#FFFFFF",
      "min_score": 90,
      "operator": ">=",
      "requires_reason": true,
      "warn_staff_shortage": true,
      "sort_order": 3
    },
    {
      "level_name": "Yếu",
      "bg_color": "#8B4513",
      "text_color": "#FFFFFF",
      "min_score": 85,
      "operator": ">=",
      "requires_reason": true,
      "warn_staff_shortage": true,
      "sort_order": 4
    },
    {
      "level_name": "Chưa đạt",
      "bg_color": "#8B0000",
      "text_color": "#FFFFFF",
      "min_score": 85,
      "operator": "<",
      "requires_reason": true,
      "warn_staff_shortage": true,
      "sort_order": 5
    }
  ]
}
```

**Validation rules:**

| Field | Rules |
|---|---|
| `name` | required, string, max:255 |
| `effective_from` | required, date |
| `effective_until` | nullable, date, after:effective_from |
| `description` | nullable, string |
| `details` | required, array, min:1 |
| `details.*.level_name` | required, string, max:255, **distinct** (unique trong array) |
| `details.*.bg_color` | required, string, max:20 |
| `details.*.text_color` | required, string, max:20 |
| `details.*.min_score` | required, numeric, min:0, max:100 |
| `details.*.operator` | sometimes, string, in: `>=`, `<` |
| `details.*.requires_reason` | sometimes, boolean |
| `details.*.warn_staff_shortage` | sometimes, boolean |
| `details.*.sort_order` | sometimes, integer, min:0 |

#### PATCH /v1/admin/kpi-rating-levels/{id} — Cập nhật

Partial update — chỉ gửi fields cần thay đổi. Nếu gửi `details`, toàn bộ details cũ sẽ bị xóa và tạo lại (sync strategy).

```json
{
  "name": "Mức đánh giá 2026 (Updated)",
  "details": [
    { "level_name": "Xuất sắc", "bg_color": "#006400", "text_color": "#FFFFFF", "min_score": 100, "operator": ">=", "requires_reason": false, "warn_staff_shortage": false, "sort_order": 1 },
    { "level_name": "Đạt",      "bg_color": "#228B22", "text_color": "#FFFFFF", "min_score": 90,  "operator": ">=", "requires_reason": false, "warn_staff_shortage": false, "sort_order": 2 },
    { "level_name": "Chưa đạt", "bg_color": "#8B0000", "text_color": "#FFFFFF", "min_score": 90,  "operator": "<",  "requires_reason": true,  "warn_staff_shortage": true,  "sort_order": 3 }
  ]
}
```

> ⚠️ **Lưu ý sync strategy:** Khi gửi `details`, **tất cả details cũ sẽ bị xóa** và tạo lại từ array mới. FE cần gửi **toàn bộ** details (không phải chỉ phần thay đổi).

#### DELETE /v1/admin/kpi-rating-levels/{id} — Xóa

Cascade xóa tất cả `kpi_rating_level_details` liên quan (via FK constraint). Response: `204 No Content`.

---

## Permissions

Khai báo trong `Configs/permissions.php`, sync bằng: `php artisan apiato:permissions-sync`

```
kpi-rating-levels.index   → parent: core.system      (List/Find)
├── kpi-rating-levels.create  → parent: kpi-rating-levels.index
├── kpi-rating-levels.edit    → parent: kpi-rating-levels.index
└── kpi-rating-levels.destroy → parent: kpi-rating-levels.index
```

---

## Config

### `Configs/appSection-kpiRatingLevel.php`

Truy cập: `config('appSection-kpiRatingLevel.default')`

```php
return [
    'default' => [
        'name' => 'Mặc định',
        'details' => [
            ['level_name' => 'Xuất sắc',   'bg_color' => '#006400', ...],
            ['level_name' => 'Đạt',        'bg_color' => '#228B22', ...],
            ['level_name' => 'Trung bình', 'bg_color' => '#DAA520', ...],
            ['level_name' => 'Yếu',        'bg_color' => '#8B4513', ...],
            ['level_name' => 'Chưa đạt',   'bg_color' => '#8B0000', ...],
        ],
    ],
    // Có thể thêm config khác sau này...
];
```

---

## Architecture

```
Route → Request ($decode, rules(), authorize() with can())
  → Controller (app(Action)->run($request), Response::create())
    → Action (app(Task)->run(...), DB::transaction())
      → Task ($this->repository->create/find/update/delete)
        → Repository ($fieldSearchable, model())
          → Model (computed status, details relationship)
```

### Flow tạo mới (Create)

```
CreateKpiRatingLevelRequest (validate name, dates, details[])
  → CreateKpiRatingLevelController
    → CreateKpiRatingLevelAction (DB::transaction)
      ├── CreateKpiRatingLevelTask (create parent)
      └── SyncKpiRatingLevelDetailsTask (create details[])
    → KpiRatingLevelTransformer (transform + defaultIncludes details)
```

### Flow cập nhật (Update)

```
UpdateKpiRatingLevelRequest (validate, fields optional)
  → UpdateKpiRatingLevelController
    → UpdateKpiRatingLevelAction (DB::transaction)
      ├── UpdateKpiRatingLevelTask (update parent fields)
      └── SyncKpiRatingLevelDetailsTask (delete old + create new details)
    → KpiRatingLevelTransformer
```

### Flow lấy mức active (Public)

```
GetActiveKpiRatingLevelRequest (no auth, always true)
  → GetActiveKpiRatingLevelController
    → GetActiveKpiRatingLevelAction
      → GetActiveKpiRatingLevelTask
        ├── Query: effective_from <= today AND (effective_until IS NULL OR >= today)
        │   → found: return KpiRatingLevel model → Transformer
        └── not found: return config('appSection-kpiRatingLevel.default') → raw JSON
```

---

## File Structure

```
KpiRatingLevel/
├── Actions/
│   ├── CreateKpiRatingLevelAction.php      ← DB transaction: create parent + sync details
│   ├── DeleteKpiRatingLevelAction.php      ← Find then delete (cascade)
│   ├── FindKpiRatingLevelAction.php
│   ├── GetActiveKpiRatingLevelAction.php   ← Public API
│   ├── ListKpiRatingLevelsAction.php
│   └── UpdateKpiRatingLevelAction.php      ← DB transaction: update parent + sync details
├── Configs/
│   ├── appSection-kpiRatingLevel.php       ← Default fallback + extensible config
│   └── permissions.php                     ← Permission flags
├── Data/
│   ├── Migrations/
│   │   ├── 2026_03_25_000001_create_kpi_rating_levels_table.php
│   │   ├── 2026_03_25_000002_create_kpi_rating_level_details_table.php
│   │   └── 2026_03_27_100000_add_warn_staff_shortage_to_kpi_rating_level_details.php
│   └── Repositories/
│       ├── KpiRatingLevelDetailRepository.php
│       └── KpiRatingLevelRepository.php    ← fieldSearchable: name(like)
├── Enums/
│   └── KpiRatingLevelStatus.php            ← pending | active | expired
├── Models/
│   ├── KpiRatingLevel.php                  ← Computed status accessor, details() HasMany
│   └── KpiRatingLevelDetail.php            ← ratingLevel() BelongsTo
├── Tasks/
│   ├── CreateKpiRatingLevelTask.php
│   ├── DeleteKpiRatingLevelTask.php
│   ├── FindKpiRatingLevelByIdTask.php      ← eager load details
│   ├── GetActiveKpiRatingLevelTask.php     ← Query + config fallback
│   ├── ListKpiRatingLevelsTask.php         ← orderBy effective_from desc, eager load
│   ├── SyncKpiRatingLevelDetailsTask.php   ← Delete old + create new (sync strategy)
│   └── UpdateKpiRatingLevelTask.php
└── UI/API/
    ├── Controllers/
    │   ├── CreateKpiRatingLevelController.php
    │   ├── DeleteKpiRatingLevelController.php
    │   ├── FindKpiRatingLevelController.php
    │   ├── GetActiveKpiRatingLevelController.php   ← Public endpoint
    │   ├── ListKpiRatingLevelsController.php
    │   └── UpdateKpiRatingLevelController.php
    ├── Requests/
    │   ├── CreateKpiRatingLevelRequest.php          ← Nested details validation
    │   ├── DeleteKpiRatingLevelRequest.php
    │   ├── FindKpiRatingLevelRequest.php
    │   ├── GetActiveKpiRatingLevelRequest.php       ← No auth (public)
    │   ├── ListKpiRatingLevelsRequest.php
    │   └── UpdateKpiRatingLevelRequest.php          ← Partial update support
    ├── Routes/
    │   ├── CreateKpiRatingLevel.v1.private.php
    │   ├── DeleteKpiRatingLevel.v1.private.php
    │   ├── FindKpiRatingLevel.v1.private.php
    │   ├── GetActiveKpiRatingLevel.v1.public.php    ← Public route
    │   ├── ListKpiRatingLevels.v1.private.php
    │   └── UpdateKpiRatingLevel.v1.private.php
    └── Transformers/
        ├── KpiRatingLevelDetailTransformer.php
        └── KpiRatingLevelTransformer.php            ← defaultIncludes details
```
