# Shift Container — Shift Templates (Ca chuẩn)

Quản lý **ca chuẩn** (Shift Templates) — dữ liệu mặc định ghi nhận các thông tin config liên quan đến khung giờ làm việc của từng department.

## Mô tả

Container quản lý **ca chuẩn** theo cấu trúc parent-child:

```
ShiftTemplate (parent — ca chuẩn)
├── name: "Ca chuẩn - bình thường"
├── color: "#0000FF" (màu hiển thị)
├── status: "active" / "inactive"
├── applies_to_shift_1: true (Ca 1)
├── applies_to_shift_2: false (Ca 2)
└── ShiftTemplateDetail[] (child — config từng bộ phận)
    ├── Pick DTF1   — Ca 1: 06:00, 8.5h, 3 người
    ├── Pick DTF2   — Ca 1: 06:00, 8.5h, 3 người
    ├── Print DTF1  — Ca 1: 06:30, 8.5h, 8 người, chuẩn bị 23 phút
    ├── Cut DTF1    — Ca 1: 07:00, 8.5h, 5 người
    ├── ...
    └── (mỗi department 1 dòng config cho 1 ca)
```

### Business Rules

1. **Status lưu DB** — `active` (đang sử dụng) hoặc `inactive` (tạm dừng)
2. **`end_time` auto-computed** — tính từ `start_time + work_hours`, **không lưu DB** (virtual accessor)
3. **2 boolean columns** cho ca áp dụng: `applies_to_shift_1`, `applies_to_shift_2`
4. **Mỗi department chỉ có 1 dòng** config cho 1 shift_number trong 1 template (unique constraint)
5. **Sắp xếp** danh sách theo `sort_order` — hỗ trợ drag & drop reorder
6. **Copy** template — clone template header + toàn bộ details
7. **Sync strategy** — khi update details, xóa toàn bộ details cũ và tạo lại từ array mới

---

## Database Schema

### `shift_templates`

| Column | Type | Nullable | Default | Mô tả |
|---|---|---|---|---|
| id | bigint PK | | | Auto increment |
| name | varchar(255) | ❌ | | Tên ca chuẩn. VD: "Ca chuẩn - bình thường" |
| color | varchar(20) | ❌ | `#0000FF` | Mã màu hex hiển thị |
| description | text | ✅ | | Mô tả |
| sort_order | smallint | ❌ | `0` | Thứ tự hiển thị (drag & drop) |
| status | varchar(20) | ❌ | `active` | `active` = Sử dụng, `inactive` = Tạm dừng |
| applies_to_shift_1 | boolean | ❌ | `true` | Áp dụng cho Ca 1 |
| applies_to_shift_2 | boolean | ❌ | `false` | Áp dụng cho Ca 2 |
| created_at | timestamp | | | |
| updated_at | timestamp | | | |

**Indexes:** `status`, `sort_order`

### `shift_template_details`

| Column | Type | Nullable | Default | Mô tả |
|---|---|---|---|---|
| id | bigint PK | | | Auto increment |
| shift_template_id | FK → shift_templates | ❌ | | Cascade on delete |
| department_id | FK → departments | ❌ | | Cascade on delete |
| shift_number | tinyint | ❌ | | 1 = Ca 1, 2 = Ca 2 |
| headcount | smallint | ❌ | `0` | Số nhân sự làm việc |
| start_time | time | ❌ | | Giờ bắt đầu ca. VD: `06:30` |
| work_hours | decimal(4,1) | ❌ | | Số giờ làm. VD: `8.5` |
| prep_minutes | smallint | ❌ | `0` | Thời gian chuẩn bị đầu ca (phút) |
| break1_start | time | ✅ | | Nghỉ giải lao 1 — giờ bắt đầu |
| break1_minutes | smallint | ❌ | `0` | Nghỉ giải lao 1 — số phút |
| meal_break_start | time | ✅ | | Nghỉ ăn — giờ bắt đầu |
| meal_break_minutes | smallint | ❌ | `0` | Nghỉ ăn — số phút |
| break2_start | time | ✅ | | Nghỉ giải lao 2 — giờ bắt đầu |
| break2_minutes | smallint | ❌ | `0` | Nghỉ giải lao 2 — số phút |
| break3_start | time | ✅ | | Nghỉ giải lao 3 — giờ bắt đầu |
| break3_minutes | smallint | ❌ | `0` | Nghỉ giải lao 3 — số phút |
| created_at | timestamp | | | |
| updated_at | timestamp | | | |

**Virtual field (accessor, không lưu DB):**

| Field | Tính từ | Ví dụ |
|---|---|---|
| `end_time` | `start_time + work_hours` | `06:30 + 8.5h = 15:00` |

**Unique constraint:** `(shift_template_id, department_id, shift_number)`

---

## API Endpoints

Tất cả yêu cầu `auth:api` + permission `shift-templates.*`.

| Method | Endpoint | Permission | Mô tả |
|---|---|---|---|
| GET | `/v1/admin/shift-templates` | `shift-templates.index` | Danh sách (paginated, searchable) |
| GET | `/v1/admin/shift-templates/{id}` | `shift-templates.index` | Chi tiết 1 record + details |
| POST | `/v1/admin/shift-templates` | `shift-templates.create` | Tạo mới (header + details) |
| PATCH | `/v1/admin/shift-templates/{id}` | `shift-templates.edit` | Cập nhật (partial + sync details) |
| DELETE | `/v1/admin/shift-templates/{id}` | `shift-templates.destroy` | Xóa (cascade xóa details) |
| POST | `/v1/admin/shift-templates/{id}/copy` | `shift-templates.create` | Clone template + details |
| PATCH | `/v1/admin/shift-templates/reorder` | `shift-templates.edit` | Reorder sort_order (drag & drop) |

### Query Parameters (Apiato RequestCriteria)

| Param | Mô tả | Ví dụ |
|---|---|---|
| `search` | Tìm kiếm | `?search=name:bình thường` hoặc `?search=status:active` |
| `orderBy` | Sắp xếp | `?orderBy=sort_order` (default) |
| `sortedBy` | Chiều sắp xếp | `?sortedBy=asc` |
| `page` | Trang hiện tại | `?page=2` |
| `limit` | Số item/trang | `?limit=25` (default: 15) |

**`$fieldSearchable`:**

| Field | Operator | Ví dụ |
|---|---|---|
| `name` | `like` | `?search=name:tăng ca` |
| `status` | `=` | `?search=status:active` |

---

### GET /v1/admin/shift-templates — Danh sách

**Response:**
```json
{
  "data": [
    {
      "id": "HASHED_ID",
      "name": "Ca chuẩn - bình thường",
      "color": "#0000FF",
      "description": "Dành cho ngày làm việc bình thường, làm việc từ 6h",
      "sort_order": 1,
      "status": "active",
      "applies_to_shift_1": true,
      "applies_to_shift_2": false,
      "created_at": "2026-03-25T07:00:00.000000Z",
      "updated_at": "2026-03-25T07:00:00.000000Z",
      "details": {
        "data": [
          {
            "id": "HASHED_ID",
            "department_id": 1,
            "department_code": "print",
            "department_label": "In ấn",
            "production_line": "DTF 1",
            "shift_number": 1,
            "headcount": 8,
            "start_time": "06:30",
            "work_hours": 8.5,
            "prep_minutes": 23,
            "end_time": "15:00",
            "break1_start": "09:00",
            "break1_minutes": 30,
            "meal_break_start": "11:30",
            "meal_break_minutes": 15,
            "break2_start": "14:00",
            "break2_minutes": 15,
            "break3_start": "16:30",
            "break3_minutes": 15
          }
        ]
      }
    }
  ],
  "meta": {
    "pagination": {
      "total": 5,
      "count": 5,
      "per_page": 15,
      "current_page": 1,
      "total_pages": 1
    }
  }
}
```

> 💡 **FE note:** `end_time` được server tự tính từ `start_time + work_hours`, không cần gửi khi create/update. Chỉ đọc từ response.

---

### GET /v1/admin/shift-templates/{id} — Chi tiết

Response tương tự item trong danh sách, nhưng không wrap trong array. Details luôn được include.

---

### POST /v1/admin/shift-templates — Tạo mới

**Request body:**
```json
{
  "name": "Ca chuẩn - tăng ca",
  "color": "#FF0000",
  "description": "Dành cho các ngày sự kiện nhiều đơn",
  "sort_order": 2,
  "status": "active",
  "applies_to_shift_1": true,
  "applies_to_shift_2": false,
  "details": [
    {
      "department_id": 1,
      "shift_number": 1,
      "headcount": 8,
      "start_time": "06:30",
      "work_hours": 8.5,
      "prep_minutes": 23,
      "break1_start": "09:00",
      "break1_minutes": 30,
      "meal_break_start": "11:30",
      "meal_break_minutes": 15,
      "break2_start": "14:00",
      "break2_minutes": 15,
      "break3_start": "16:30",
      "break3_minutes": 15
    },
    {
      "department_id": 2,
      "shift_number": 1,
      "headcount": 5,
      "start_time": "07:00",
      "work_hours": 8.5,
      "prep_minutes": 0,
      "break1_start": "09:30",
      "break1_minutes": 30,
      "meal_break_start": "12:00",
      "meal_break_minutes": 15,
      "break2_start": null,
      "break2_minutes": 0,
      "break3_start": null,
      "break3_minutes": 0
    }
  ]
}
```

**Validation rules:**

| Field | Rules |
|---|---|
| `name` | required, string, max:255 |
| `color` | sometimes, string, max:20 |
| `description` | nullable, string |
| `sort_order` | sometimes, integer, min:0 |
| `status` | sometimes, string, in: `active`, `inactive` |
| `applies_to_shift_1` | sometimes, boolean |
| `applies_to_shift_2` | sometimes, boolean |
| `details` | sometimes, array |
| `details.*.department_id` | required, integer, **exists:departments,id** |
| `details.*.shift_number` | required, integer, in: `1`, `2` |
| `details.*.headcount` | sometimes, integer, min:0 |
| `details.*.start_time` | required, **date_format:H:i** (VD: `"06:30"`) |
| `details.*.work_hours` | required, numeric, min:0, max:24 |
| `details.*.prep_minutes` | sometimes, integer, min:0 |
| `details.*.break1_start` | nullable, date_format:H:i |
| `details.*.break1_minutes` | sometimes, integer, min:0 |
| `details.*.meal_break_start` | nullable, date_format:H:i |
| `details.*.meal_break_minutes` | sometimes, integer, min:0 |
| `details.*.break2_start` | nullable, date_format:H:i |
| `details.*.break2_minutes` | sometimes, integer, min:0 |
| `details.*.break3_start` | nullable, date_format:H:i |
| `details.*.break3_minutes` | sometimes, integer, min:0 |

> ⚠️ **Lưu ý format time:** FE gửi format `"HH:mm"` (VD: `"06:30"`, `"14:00"`), **KHÔNG** gửi `"HH:mm:ss"`.

**Response:** `201 Created` — trả về object shift template mới + details.

---

### PATCH /v1/admin/shift-templates/{id} — Cập nhật

**Partial update** — chỉ gửi fields cần thay đổi.

```json
{
  "name": "Ca chuẩn - bình thường (Updated)",
  "status": "inactive"
}
```

Nếu gửi `details`:

```json
{
  "details": [
    {
      "department_id": 1,
      "shift_number": 1,
      "headcount": 10,
      "start_time": "06:00",
      "work_hours": 8.0,
      "prep_minutes": 0
    }
  ]
}
```

> ⚠️ **Sync strategy:** Khi gửi `details`, **tất cả details cũ bị xóa** và tạo lại từ array mới. FE cần gửi **toàn bộ** details (không phải chỉ phần thay đổi).

**Response:** `200 OK` — trả về object updated + details.

---

### DELETE /v1/admin/shift-templates/{id} — Xóa

Cascade xóa tất cả `shift_template_details` liên quan (via FK constraint).

**Response:** `204 No Content`

---

### POST /v1/admin/shift-templates/{id}/copy — Clone

Clone template header + toàn bộ details. Tên tự thêm suffix `" (Copy)"`. Sort order = max + 1.

**Request body:** Không cần body (chỉ cần `{id}` trên URL).

**Response:** `201 Created` — trả về object mới.

```json
{
  "data": {
    "id": "NEW_HASHED_ID",
    "name": "Ca chuẩn - bình thường (Copy)",
    "sort_order": 6,
    "details": { "data": [...] }
  }
}
```

---

### PATCH /v1/admin/shift-templates/reorder — Sắp xếp lại

Batch update `sort_order` cho nhiều templates.

**Request body:**
```json
{
  "items": [
    { "id": 1, "sort_order": 1 },
    { "id": 2, "sort_order": 2 },
    { "id": 3, "sort_order": 3 }
  ]
}
```

> ⚠️ **Lưu ý:** `id` ở đây là **raw integer ID** (không phải hashed ID) vì validation dùng `exists:shift_templates,id`.

**Validation rules:**

| Field | Rules |
|---|---|
| `items` | required, array, min:1 |
| `items.*.id` | required, integer, exists:shift_templates,id |
| `items.*.sort_order` | required, integer, min:0 |

**Response:** `200 OK`

```json
{
  "message": "Reordered successfully"
}
```

---

## FE Integration Guide

### 1. Trang danh sách (List)

```
GET /v1/admin/shift-templates?search=status:active
```

Hiển thị bảng:
| Color | Tên ca chuẩn | Thời gian | Trạng thái | Thứ tự | Mô tả | Actions |
|---|---|---|---|---|---|---|
| 🔵 | Ca chuẩn - bình thường | (tính từ details) | Sử dụng | 1 | ... | Copy, Edit, Delete |

**Tính "Thời gian" hiển thị:** FE lấy từ details:
- Group details theo `shift_number`
- Mỗi group: `min(start_time) - max(end_time)`
- VD: Ca 1: `06:00 - 14:00`, Ca 2: `14:00 - 22:00`

**Lọc theo trạng thái:**
```
GET /v1/admin/shift-templates?search=status:active
GET /v1/admin/shift-templates?search=status:inactive
GET /v1/admin/shift-templates  (tất cả)
```

### 2. Form thêm/sửa

**Header form:**
- Color picker → `color`
- Text field → `name`
- Textarea → `description`
- Number → `sort_order`
- Select → `status` (active/inactive)
- Checkbox → `applies_to_shift_1`, `applies_to_shift_2`

**Bảng chi tiết:**
- Lấy danh sách departments: `GET /v1/admin/departments` (Production container)
- Group theo production_line (Pick, DTF1, DTF2, DTG...)
- Mỗi department có 1 hoặc 2 dòng tùy theo `applies_to_shift_1/2`
- auto-compute `end_time` = `start_time + work_hours` ở FE (server cũng compute, chỉ cần hiển thị)

### 3. Drag & Drop Reorder

Sau khi user kéo thả, gọi:
```
PATCH /v1/admin/shift-templates/reorder
{ "items": [{ "id": 1, "sort_order": 0 }, { "id": 3, "sort_order": 1 }, { "id": 2, "sort_order": 2 }] }
```

### 4. Copy

```
POST /v1/admin/shift-templates/{id}/copy
```
Không cần body. Response trả về bản copy mới.

---

## Permissions

Khai báo trong `Configs/permissions.php`:

```
shift-templates.index   → parent: core.system      (List/Find)
├── shift-templates.create  → parent: shift-templates.index  (Create/Copy)
├── shift-templates.edit    → parent: shift-templates.index  (Update/Reorder)
└── shift-templates.destroy → parent: shift-templates.index  (Delete)
```

---

## Seeder

```bash
php artisan db:seed --class="App\Containers\AppSection\Shift\Data\Seeders\ShiftTemplateSeeder_1"
```

Seeds 5 templates matching mockup:

| # | Tên | Màu | Trạng thái | Ca 1 | Ca 2 | Details |
|---|---|---|---|---|---|---|
| 1 | Ca chuẩn - bình thường | 🔵 #0000FF | active | ✅ | ❌ | 12 depts |
| 2 | Ca chuẩn - tăng ca | 🔴 #FF0000 | active | ✅ | ❌ | 12 depts |
| 3 | Ca chuẩn - ngày lễ | 🟠 #FFA500 | inactive | ✅ | ❌ | 12 depts |
| 4 | Ca chuẩn - 2 ca | 🟢 #008000 | inactive | ✅ | ✅ | 24 depts (12×2 ca) |
| 5 | Ca chuẩn - 2 ca - tăng ca | 🟣 #800080 | inactive | ✅ | ✅ | 24 depts (12×2 ca) |

---

## Architecture

```
Route → Request ($decode, rules(), authorize())
  → Controller (app(Action)->run($request), Response::create())
    → Action (app(Task)->run(...), DB::transaction())
      → Task (Model::create/find/update/delete)
        → Repository ($fieldSearchable, addRequestCriteria)
          → Model (endTime accessor, details() HasMany)
```

### Flow tạo mới (Create)

```
CreateShiftTemplateRequest (validate header + details[])
  → CreateShiftTemplateController
    → CreateShiftTemplateAction (DB::transaction)
      ├── CreateShiftTemplateTask (create header)
      └── SyncShiftTemplateDetailsTask (create details[])
    → ShiftTemplateTransformer (defaultIncludes details)
```

### Flow cập nhật (Update)

```
UpdateShiftTemplateRequest (partial + optional details)
  → UpdateShiftTemplateController
    → UpdateShiftTemplateAction (DB::transaction)
      ├── FindShiftTemplateByIdTask (find existing)
      ├── UpdateShiftTemplateTask (update header fields)
      └── SyncShiftTemplateDetailsTask (delete old + create new)
    → ShiftTemplateTransformer
```

### Flow copy

```
CopyShiftTemplateRequest (just id)
  → CopyShiftTemplateController
    → CopyShiftTemplateAction (DB::transaction)
      ├── FindShiftTemplateByIdTask (find source)
      └── CopyShiftTemplateTask (replicate header + details)
    → ShiftTemplateTransformer
```

---

## File Structure

```
Shift/
├── Actions/
│   ├── CopyShiftTemplateAction.php         ← DB transaction: clone template + details
│   ├── CreateShiftTemplateAction.php       ← DB transaction: create header + sync details
│   ├── DeleteShiftTemplateAction.php       ← Find then delete (cascade)
│   ├── FindShiftTemplateAction.php
│   ├── ListShiftTemplatesAction.php
│   ├── ReorderShiftTemplatesAction.php     ← DB transaction: batch update sort_order
│   └── UpdateShiftTemplateAction.php       ← DB transaction: update header + sync details
├── Configs/
│   ├── appSection-shift.php                ← Default colors config
│   ├── permissions.php                     ← Permission flags
│   └── table-models.php                    ← Table/form metadata for auto-discovery
├── Data/
│   ├── Migrations/
│   │   ├── 2026_03_25_100001_create_shift_templates_table.php
│   │   └── 2026_03_25_100002_create_shift_template_details_table.php
│   ├── Repositories/
│   │   ├── ShiftTemplateDetailRepository.php
│   │   └── ShiftTemplateRepository.php     ← fieldSearchable: name(like), status(=)
│   └── Seeders/
│       └── ShiftTemplateSeeder_1.php       ← 5 templates matching mockup
├── Enums/
│   └── ShiftTemplateStatus.php             ← active | inactive
├── Models/
│   ├── ShiftTemplate.php                   ← status enum cast, details() HasMany
│   └── ShiftTemplateDetail.php             ← endTime() accessor, department() BelongsTo
├── Tasks/
│   ├── CopyShiftTemplateTask.php           ← Replicate header + details
│   ├── CreateShiftTemplateTask.php
│   ├── DeleteShiftTemplateTask.php
│   ├── FindShiftTemplateByIdTask.php       ← Eager load details.department
│   ├── ListShiftTemplatesTask.php          ← OrderBy sort_order, eager load
│   ├── SyncShiftTemplateDetailsTask.php    ← Delete old + create new (sync strategy)
│   └── UpdateShiftTemplateTask.php
├── Tests/
│   ├── ContainerTestCase.php
│   ├── Functional/API/
│   │   ├── ApiTestCase.php
│   │   └── ShiftTemplateTest.php           ← 15+ test cases
│   ├── FunctionalTestCase.php
│   └── UnitTestCase.php
└── UI/API/
    ├── Controllers/
    │   ├── CopyShiftTemplateController.php
    │   ├── CreateShiftTemplateController.php
    │   ├── DeleteShiftTemplateController.php
    │   ├── FindShiftTemplateController.php
    │   ├── ListShiftTemplatesController.php
    │   ├── ReorderShiftTemplatesController.php
    │   └── UpdateShiftTemplateController.php
    ├── Requests/
    │   ├── CopyShiftTemplateRequest.php
    │   ├── CreateShiftTemplateRequest.php      ← Nested details validation
    │   ├── DeleteShiftTemplateRequest.php
    │   ├── FindShiftTemplateRequest.php
    │   ├── ListShiftTemplatesRequest.php
    │   ├── ReorderShiftTemplatesRequest.php    ← items[] validation
    │   └── UpdateShiftTemplateRequest.php      ← Partial update support
    ├── Routes/
    │   ├── CopyShiftTemplate.v1.private.php
    │   ├── CreateShiftTemplate.v1.private.php
    │   ├── DeleteShiftTemplate.v1.private.php
    │   ├── FindShiftTemplate.v1.private.php
    │   ├── ListShiftTemplates.v1.private.php
    │   ├── ReorderShiftTemplates.v1.private.php
    │   └── UpdateShiftTemplate.v1.private.php
    └── Transformers/
        ├── ShiftTemplateDetailTransformer.php  ← end_time, department info
        └── ShiftTemplateTransformer.php        ← defaultIncludes details
```
