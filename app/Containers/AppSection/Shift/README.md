# Shift Container — Shifts & Shift Templates (Ca làm việc & Ca chuẩn)

Quản lý **ca làm việc** (Shifts) và **ca chuẩn** (Shift Templates) — dữ liệu ca sản xuất thực tế và các thông tin config mặc định liên quan đến khung giờ làm việc của từng department.

## Mô tả

Container bao gồm 2 phần chính:

### 1. Shifts (Ca làm việc)

Bảng `shifts` lưu thông tin ca sản xuất thực tế (ngày, số ca, giờ bắt đầu/kết thúc, quản đốc). Model `Shift` cung cấp các static methods: `resolve()`, `current()`, `forDate()`.

> **Cross-container usage:** Production container import `App\Containers\AppSection\Shift\Models\Shift` để resolve ca làm việc hiện tại.

### 2. Shift Templates (Ca chuẩn)

Quản lý **ca chuẩn** theo cấu trúc parent-child:

```
ShiftTemplate (parent — ca chuẩn)
├── name: "Ca chuẩn - bình thường"
├── color: "#0000FF" (màu hiển thị)
├── status: "active" / "inactive"
├── applies_to_shift_1: true (Ca 1)
├── applies_to_shift_2: false (Ca 2)
└── ShiftTemplateDetail[] (child — config từng bộ phận)
    ├── Pick DTF1   — Ca 1: 06:00, 8h, 3 người
    ├── Pick DTF2   — Ca 1: 06:00, 8h, 3 người
    ├── Print DTF1  — Ca 1: 06:30, 8h, 8 người, chuẩn bị 23 phút
    ├── Cut DTF1    — Ca 1: 07:00, 8h, 5 người
    ├── ...
    └── (mỗi department 1 dòng config cho 1 ca)
```

### Business Rules

1. **Status lưu DB** — `active` (đang sử dụng) hoặc `inactive` (tạm dừng)
2. **`end_time` auto-computed** — tính từ `start_time + work_hours + meal_break_minutes`, **không lưu DB** (virtual accessor)
3. **2 boolean columns** cho ca áp dụng: `applies_to_shift_1`, `applies_to_shift_2`
4. **Mỗi department chỉ có 1 dòng** config cho 1 shift_number trong 1 template (unique constraint)
5. **Sắp xếp** danh sách theo `sort_order` — hỗ trợ drag & drop reorder
6. **Copy** template — clone template header + toàn bộ details
7. **Sync strategy** — khi update details, xóa toàn bộ details cũ và tạo lại từ array mới

---

## Database Schema

### `shifts`

| Column | Type | Nullable | Default | Mô tả |
|---|---|---|---|---|
| id | bigint PK | | | Auto increment |
| date | date | ❌ | | Ngày sản xuất |
| shift_number | tinyint | ❌ | | Ca làm: 1, 2, 3 |
| start_time | time | ❌ | | Giờ bắt đầu: `06:00` |
| end_time | time | ❌ | | Giờ kết thúc: `14:00` |
| supervisor | varchar(100) | ✅ | | Quản đốc: Nguyễn Văn Minh |
| is_active | boolean | ❌ | `true` | Ca đang hoạt động |
| shift_template_id | FK → shift_templates | ✅ | `null` | Template gốc đã dùng (null on delete) |
| created_at | timestamp | | | |
| updated_at | timestamp | | | |

**Unique constraint:** `(date, shift_number)`

**Static methods (Model):**
- `Shift::current()` — Lấy ca đang active
- `Shift::resolve(?date, ?shiftNumber)` — Resolve theo date + shift, fallback về current
- `Shift::forDate(date)` — Tất cả ca của 1 ngày

**Relationships:** `template()` → BelongsTo ShiftTemplate, `details()` → HasMany ShiftDetail, `hourlyRecords()` → HasMany HourlyRecord

---

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
| work_hours | decimal(4,1) | ❌ | | Số giờ làm (net, không tính nghỉ ăn). VD: `8` |
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
| `end_time` | `start_time + work_hours + meal_break_minutes` | `06:30 + 8h + 30min = 15:00` |

**Unique constraint:** `(shift_template_id, department_id, shift_number)`

### `shift_details` — Config từng department cho 1 ca cụ thể

Cùng structure với `shift_template_details` nhưng FK vào `shifts`, bổ sung thêm 2 trường snapshot.

| Column | Type | Nullable | Default | Mô tả |
|---|---|---|---|---|
| id | bigint PK | | | Auto increment |
| shift_id | FK → shifts | ❌ | | Cascade on delete |
| department_id | FK → departments | ❌ | | Cascade on delete |
| shift_number | tinyint | ❌ | | 1 = Ca 1, 2 = Ca 2 |
| headcount | smallint | ❌ | `0` | Số nhân sự |
| **kpi_per_hour** | **unsignedInt** | ✅ | `null` | **Snapshot năng suất 1h từ `departments.kpi_per_hour` tại thời điểm tạo ca** |
| **day_start_inventory** | **unsignedInt** | ❌ | `0` | **Tồn đầu ngày — FE gửi kèm khi tạo ca, mặc định 0** |
| start_time | time | ❌ | | Giờ bắt đầu |
| work_hours | decimal(4,1) | ❌ | | Số giờ làm |
| prep_minutes | smallint | ❌ | `0` | Phút chuẩn bị |
| break1_start..break3_minutes | | | | (giống shift_template_details) |

> 💡 **Snapshot logic:** `kpi_per_hour` được copy từ `departments.kpi_per_hour` lúc `CreateShiftFromTemplateTask` chạy — giữ nguyên lịch sử dù department KPI thay đổi sau.

**Virtual field:** `end_time` = `start_time + work_hours + meal_break_minutes`

**Unique constraint:** `(shift_id, department_id, shift_number)`

---

## API Endpoints

### Shift Templates API

Tất cả yêu cầu `auth:api` + permission `shift-templates.*`.

| Method | Endpoint | Permission | Mô tả |
|---|---|---|---|
| GET | `/v1/admin/shift-templates` | `shift-templates.index` | Danh sách (paginated, searchable) |
| GET | `/v1/admin/shift-templates/defaults` | `shifts.create` | Giá trị mặc định cho form tạo mới |
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

> 💡 **Optimized:** List response **không** include `details` mặc định. Thay vào đó, trả về `shift_1_time` / `shift_2_time` summary fields cho hiển thị nhanh. Nếu cần full details, dùng `?include=details`.

```json
{
  "data": [
    {
      "id": "HASHED_ID",
      "name": "Ca chuẩn - bình thường",
      "color": "#0000FF",
      "description": "Dành cho ngày làm việc bình thường",
      "sort_order": 1,
      "status": "active",
      "applies_to_shift_1": true,
      "applies_to_shift_2": false,
      "shift_1_time": "06:00 - 18:00",
      "shift_2_time": null,
      "created_at": "2026-03-25T07:00:00.000000Z",
      "updated_at": "2026-03-25T07:00:00.000000Z"
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

> 💡 **FE note:** `end_time` được server tự tính từ `start_time + work_hours + meal_break_minutes`, không cần gửi khi create/update. `shift_1_time` / `shift_2_time` dùng cho hiển thị nhanh trên danh sách.

---

### GET /v1/admin/shift-templates/defaults — Giá trị mặc định

Trả về default schedule values cho **tất cả departments**, dùng cho form "Thêm mới ca chuẩn".

Response dùng **cùng format** với Find Shift Template details — FE render cùng 1 table component cho cả Create và Edit.

| Thuộc tính | Defaults | Find (existing) |
|---|---|---|
| `id` | `null` | hashed ID |
| `headcount` | `0` | giá trị thực |
| `kpi_per_hour` | từ `departments` | từ `departments` |
| Schedule | Từ config mặc định | Từ DB |

**Response:**
```json
{
  "data": [
    {
      "id": null,
      "department_id": 5,
      "department_code": "dtf1",
      "department_label": "Pick DTF1",
      "production_line": "Pick",
      "production_line_code": "pick",
      "kpi_per_hour": 48,
      "shift_number": 1,
      "headcount": 0,
      "start_time": "06:00",
      "work_hours": 8,
      "prep_minutes": 0,
      "end_time": "14:30",
      "break1_start": "08:30",
      "break1_minutes": 15,
      "meal_break_start": "11:00",
      "meal_break_minutes": 30,
      "break2_start": "13:30",
      "break2_minutes": 15,
      "break3_start": "16:00",
      "break3_minutes": 15
    }
  ]
}
```

> 💡 **FE:** Dùng `production_line_code` + `shift_number` để group thành bảng theo mockup. Details sorted sẵn theo `production_line → shift_number → department`. `kpi_per_hour` dùng để hiển thị năng suất tham chiếu.

---

### GET /v1/admin/shift-templates/{id} — Chi tiết

Response tương tự item trong danh sách, nhưng **luôn include details** (không cần `?include=details`). Details có thêm `production_line_code` để FE group.

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
      "work_hours": 8,
      "prep_minutes": 23,
      "break1_start": "09:00",
      "break1_minutes": 15,
      "meal_break_start": "11:30",
      "meal_break_minutes": 30,
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
      "work_hours": 8,
      "prep_minutes": 0,
      "break1_start": "09:30",
      "break1_minutes": 15,
      "meal_break_start": "12:00",
      "meal_break_minutes": 30,
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

### Shift Calendar API (Ca làm việc)

Tất cả yêu cầu `auth:api` + permission `shifts.*`.

| # | Method | Endpoint | Permission | Mô tả |
|---|---|---|---|---|
| 1 | GET | `/v1/admin/shifts/calendar` | `shifts.index` | Calendar tháng `?year=2026&month=3` |
| 2 | GET | `/v1/admin/shifts/{id}` | `shifts.index` | Chi tiết shift + details + hourly_records |
| 3 | POST | `/v1/admin/shifts` | `shifts.create` | Tạo shift từ template (auto-gen details + hourly) |
| 4 | PATCH | `/v1/admin/shifts/{id}` | `shifts.edit` | Update shift header + sync details |
| 5 | DELETE | `/v1/admin/shifts/{id}` | `shifts.destroy` | Xóa shift (chặn ngày cũ) |
| 6 | POST | `/v1/admin/shifts/copy` | `shifts.create` | Copy nhiều ca sang nhiều ngày |
| 7 | PATCH | `/v1/admin/shifts/{id}/hourly` | `shifts.edit` | Điều chỉnh nhân sự (target auto-recalc) |
| 8 | GET | `/v1/admin/shifts/{id}/hourly` | `shifts.index` | Bảng điều chỉnh nhân sự (pivot) |

---

#### GET /v1/admin/shifts/calendar — Lịch ca tháng

**Query params:** `?year=2026&month=3` (default: tháng hiện tại)

**Response:**
```json
{
  "data": {
    "year": 2026,
    "month": 3,
    "days": {
      "2026-03-01": [
        {
          "id": "HASHED_ID",
          "shift_number": 1,
          "start_time": "06:00",
          "end_time": "14:00",
          "template_name": "Ca chuẩn - bình thường",
          "template_color": "#0000FF"
        }
      ],
      "2026-03-06": []
    }
  }
}
```

---

#### GET /v1/admin/shifts/{id} — Chi tiết ca

Response bao gồm shift header + details (with department info) + hourly_records:

```json
{
  "data": {
    "id": "HASHED_ID",
    "date": "2026-04-01",
    "shift_number": 1,
    "start_time": "06:00",
    "end_time": "14:00",
    "supervisor": "Nguyễn Văn Minh",
    "is_active": true,
    "template_id": "HASHED_ID",
    "template_name": "Ca chuẩn - bình thường",
    "template_color": "#0000FF",
    "details": {
      "data": [
        {
          "id": "HASHED_ID",
          "department_code": "dtf1-print",
          "department_label": "In DTF1",
          "line_code": "dtf1",
          "line_label": "DTF 1",
          "shift_number": 1,
          "headcount": 8,
          "kpi_per_hour": 48,
          "day_start_inventory": 150,
          "start_time": "06:30",
          "end_time": "15:00",
          "work_hours": 8,
          "prep_minutes": 23
        }
      ]
    },
    "hourlyRecords": {
      "data": [
        {
          "id": "HASHED_ID",
          "department_code": "dtf1-print",
          "hour_slot": "6h-7h",
          "hour_index": 0,
          "staff": 8,
          "hour_start_inventory": 0,
          "target": 384,
          "actual": null,
          "remaining": null,
          "efficiency": 0,
          "error_rate": 0,
          "status": "pending"
        }
      ]
    }
  }
}
```

> 💡 **Fields mới:** `details[].kpi_per_hour` (snapshot lúc tạo ca), `details[].day_start_inventory` (tồn đầu ngày), `hourlyRecords[].hour_start_inventory` (tồn đầu giờ), `hourlyRecords[].status` (lifecycle — xem bảng bên dưới).

---

#### POST /v1/admin/shifts — Tạo ca từ template

Server tự động:
1. Tạo bản ghi `shifts`
2. Copy `shift_template_details` → `shift_details` (**chỉ đúng `shift_number`**, filter theo ca được chọn)
3. Snapshot `kpi_per_hour` từ department vào từng shift_detail
4. Nếu FE gửi kèm `details[]` override → **merge** trước khi lưu (bao gồm `day_start_inventory`)
5. Generate `hourly_records` (`target = kpi_per_hour × headcount`, `hour_start_inventory = 0`)

**Request body — Tối giản (không override):**
```json
{
  "date": "2026-04-01",
  "shift_template_id": "tpl_hashed_001",
  "shift_numbers": [1],
  "supervisor": "Nguyễn Văn Minh"
}
```

**Request body — Có override details (kèm tồn đầu ngày):**
```json
{
  "date": "2026-04-01",
  "shift_template_id": "tpl_hashed_001",
  "shift_numbers": [1],
  "supervisor": "Nguyễn Văn Minh",
  "details": [
    {
      "department_id": 5,
      "shift_number": 1,
      "day_start_inventory": 150,
      "start_time": "06:00",
      "work_hours": 8,
      "prep_minutes": 0,
      "break1_start": "08:30",
      "break1_minutes": 15,
      "meal_break_start": "11:00",
      "meal_break_minutes": 30,
      "break2_start": "13:30",
      "break2_minutes": 15,
      "break3_start": "16:00",
      "break3_minutes": 15
    },
    {
      "department_id": 6,
      "shift_number": 1,
      "day_start_inventory": 80,
      "start_time": "06:30",
      "work_hours": 8,
      "prep_minutes": 23,
      "break1_start": "09:00",
      "break1_minutes": 15,
      "meal_break_start": "11:30",
      "meal_break_minutes": 30,
      "break2_start": "14:00",
      "break2_minutes": 15,
      "break3_start": "16:30",
      "break3_minutes": 15
    }
  ]
}
```

**Validation rules:**

| Field | Rules |
|---|---|
| `date` | required, date |
| `shift_template_id` | required, hashed ID, exists:shift_templates |
| `shift_numbers` | required, array, min:1 |
| `shift_numbers.*` | required, integer, in:1,2 |
| `supervisor` | nullable, string, max:100 |
| `details` | **sometimes**, array |
| `details[].department_id` | required\_with:details, integer, exists:departments,id |
| `details[].shift_number` | required\_with:details, integer, in:1,2 |
| ~~`details[].headcount`~~ | **Read-only** — luôn copy từ template, FE không gửi |
| **`details[].day_start_inventory`** | **sometimes, integer, min:0** — **Tồn đầu ngày. Mặc định `0`** |
| `details[].start_time` | required\_with:details, **date\_format:H:i** VD: `"06:30"` |
| `details[].work_hours` | required\_with:details, numeric, min:0, max:24 |
| `details[].prep_minutes` | sometimes, integer, min:0 |
| `details[].break1_start` | nullable, date\_format:H:i |
| `details[].break1_minutes` | sometimes, integer, min:0 |
| `details[].meal_break_start` | nullable, date\_format:H:i |
| `details[].meal_break_minutes` | sometimes, integer, min:0 |
| `details[].break2_start` | nullable, date\_format:H:i |
| `details[].break2_minutes` | sometimes, integer, min:0 |
| `details[].break3_start` | nullable, date\_format:H:i |
| `details[].break3_minutes` | sometimes, integer, min:0 |

**Logic `details[]` override:**

| Tình huống | Hành vi |
|---|---|
| FE **không gửi** `details` | Copy nguyên 100% từ template (headcount, giờ giấc...) |
| FE **gửi kèm** `details` | Merge: field nào có trong `details` → dùng giá trị FE; field nào thiếu → lấy từ template |
| `details[]` item không khớp department nào trong template | Bỏ qua (continue) |

> ⚠️ **Lưu ý:** `shift_template_id` là hashed ID. `end_time` server tự tính = `start_time + work_hours + meal_break_minutes`. `shift_numbers: [1, 2]` tạo 2 Shift record riêng biệt.

**Response:** `201 Created`

---

#### PATCH /v1/admin/shifts/{id} — Cập nhật ca

```json
{
  "supervisor": "Trần Văn B",
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

> ✅ **Smart Sync (v2):** Khi gửi `details`, hourly_records được **smart sync** — dữ liệu thực tế đã nhập (`actual`, `efficiency`, `error_rate`) được **giữ nguyên**. Chỉ `target`, `staff`, `hour_slot` được cập nhật.
>
> - Records thừa (do giảm `work_hours` hoặc gỡ department) → **soft-delete** (lưu lịch sử)
> - Records mới (do tăng `work_hours` hoặc thêm department) → insert với `status=pending`
> - Records đã soft-delete trước đó → **restore** nếu quay lại scope
>
> FE gửi **toàn bộ** details (không phải chỉ phần thay đổi).

---

#### DELETE /v1/admin/shifts/{id} — Xóa ca

> ⚠️ **Không cho xóa ca ngày cũ** (`date < today`). Hourly records được **soft-delete** (giữ lịch sử), shift_details bị hard-delete (via FK cascade).

**Response:** `204 No Content`

---

#### POST /v1/admin/shifts/copy — Sao chép ca sang ngày khác

Hỗ trợ copy **nhiều ca** (VD: Ca 1 + Ca 2) sang **nhiều ngày** cùng lúc.

```json
{
  "shift_ids": [1, 2],
  "target_dates": ["2026-03-21", "2026-03-22", "2026-03-23"]
}
```

**Validation:**

| Field | Rules |
|---|---|
| `shift_ids` | required, array, min:1 |
| `shift_ids.*` | required, integer, exists:shifts |
| `target_dates` | required, array, min:1 |
| `target_dates.*` | required, date, **after_or_equal:today** |

**Business rules:**
- Chỉ copy được vào ngày **>= hôm nay**
- Skip nếu ngày đích đã có ca cùng `shift_number`
- Không ghi đè ca cũ

**Response:** `201 Created` (nếu có ít nhất 1 ca được tạo) / `200 OK` (nếu tất cả bị skip)

```json
{
  "data": {
    "created": [
      { "date": "2026-03-21", "shift_number": 1 },
      { "date": "2026-03-21", "shift_number": 2 }
    ],
    "skipped": [
      { "date": "2026-03-22", "shift_number": 1, "reason": "already_exists" },
      { "date": "2026-03-19", "shift_number": 1, "reason": "past_date" }
    ]
  },
  "message": "Đã sao chép 2 ca, bỏ qua 2 ngày."
}
```

> 💡 **Reason codes:** `past_date` = ngày < today, `already_exists` = đã có ca cùng shift_number

---

#### PATCH /v1/admin/shifts/{id}/hourly — Điều chỉnh nhân sự

> 💡 **Chỉ gửi `staff`** — server tự tính `target = department.kpi_per_hour × staff`

```json
{
  "records": [
    { "id": 101, "staff": 3 },
    { "id": 102, "staff": 2.5 }
  ]
}
```

**Validation:**

| Field | Rules |
|---|---|
| `records` | required, array, min:1 |
| `records.*.id` | required, integer, exists:hourly_records,id |
| `records.*.staff` | required, numeric, min:0 |

**Response:** `200 OK` — shift + updated hourly_records.

---

#### GET /v1/admin/shifts/{id}/hourly — Bảng điều chỉnh nhân sự

Response: danh sách hourly_records grouped theo department × hour:

```json
{
  "data": [
    {
      "id": "HASHED_ID",
      "department_code": "dtf1-print",
      "department_label": "In DTF1",
      "hour_slot": "6h-7h",
      "hour_index": 0,
      "staff": 8,
      "hour_start_inventory": 0,
      "target": 384,
      "actual": 380,
      "remaining": 4,
      "efficiency": 98.9,
      "error_rate": 1.2,
      "status": "completed"
    }
  ]
}
```

> 💡 **`hour_start_inventory`** — Tồn đầu giờ của khung giờ đó. Khởi tạo = `0` khi tạo ca, có thể cập nhật sau.

#### `status` Lifecycle — Hourly Records

Mỗi `hourly_record` có trường `status` theo lifecycle:

| Status | Ý nghĩa | FE behavior |
|---|---|---|
| `pending` | Giờ này chưa đến — skeleton record | Cell **disabled**, background nhạt, không cho nhập `actual` |
| `active` | Giờ hiện tại — đang trong khung giờ làm việc | Cell **enabled**, highlight, cho phép nhập `actual` |
| `completed` | Giờ đã qua — khung giờ kết thúc | Cell **read-only**, show data |

**Lifecycle transitions (tự động bởi Job chạy mỗi giờ):**

```
pending → active     (khi giờ hiện tại >= start hour của slot)
active  → completed  (khi giờ hiện tại >= end hour của slot)
```

> ⚠️ **FE không cần gọi API** để chuyển status — Job `ActivateHourlyRecordsJob` chạy tự động tại `:00` mỗi giờ. FE chỉ cần poll/refresh data định kỳ.

> 💡 **Soft Delete:** Hourly records bị xóa khi update shift (giảm work_hours, gỡ department) sẽ **soft-delete** — vẫn truy vấn được lịch sử nếu cần.

---

## FE Integration Guide

### 1. Trang danh sách (List)

```
GET /v1/admin/shift-templates?search=status:active
```

Hiển thị bảng:
| Color | Tên ca chuẩn | Ca 1 | Ca 2 | Trạng thái | Thứ tự | Mô tả | Actions |
|---|---|---|---|---|---|---|---|
| 🔵 | Ca chuẩn - bình thường | 06:00 - 18:00 | — | Sử dụng | 1 | ... | Copy, Edit, Delete |

**Thời gian Ca 1/Ca 2:** Server đã tính sẵn trong `shift_1_time` / `shift_2_time`. Nếu `null` = template không áp dụng cho ca đó.

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

**Bảng chi tiết (Create/Edit shared component):**
- **Thêm mới:** Gọi `GET /v1/admin/shift-templates/defaults` → pre-fill bảng
- **Chỉnh sửa:** Gọi `GET /v1/admin/shift-templates/{id}` → fill từ DB data
- Cả 2 response cùng format details → FE dùng chung 1 component
- Group theo `production_line_code` (Pick, DTF1, DTF2, DTG...)
- Sub-group theo `shift_number` (Ca 1, Ca 2)
- `headcount` mặc định = 0 khi tạo mới

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
shift-templates.index   → parent: core.system
├── shift-templates.create  (Create/Copy)
├── shift-templates.edit    (Update/Reorder)
└── shift-templates.destroy (Delete)

shifts.index   → parent: core.system
├── shifts.create  (Create/Copy)
├── shifts.edit    (Update/Hourly Staff)
└── shifts.destroy (Delete)
```

---

## Seeder

### ShiftSeeder_1 (Ca làm việc)

```bash
php artisan db:seed --class="App\Containers\AppSection\Shift\Data\Seeders\ShiftSeeder_1"
```

Seeds 1 shift: Hôm nay, Ca 1 (06:00-14:00), Quản đốc: Nguyễn Văn Minh.

> ⚠️ **Phải chạy trước `ProductionSeeder_1`** vì production seeder cần shift data.

### ShiftTemplateSeeder_1 (Ca chuẩn)

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

### Flow tạo mới Shift Template (Create)

```
CreateShiftTemplateRequest (validate header + details[])
  → CreateShiftTemplateController
    → CreateShiftTemplateAction (DB::transaction)
      ├── CreateShiftTemplateTask (create header)
      └── SyncShiftTemplateDetailsTask (create details[])
    → ShiftTemplateTransformer (defaultIncludes details)
```

### Flow tạo mới Shift (Create Shift from Template)

```
CreateShiftRequest (validate header + optional details[] override)
  → CreateShiftController
    → CreateShiftAction (DB::transaction)
      ├── Foreach shift_numbers
      │   ├── Tính start_time/end_time header từ template (có merge override)
      │   ├── Shift::create() → shifts record
      │   ├── CreateShiftFromTemplateTask.run($shift, $templateId, $overrides)
      │   │     ├── Lọc template_details theo shift_number
      │   │     └── Merge override (department_id|shift_number key) trước khi ShiftDetail::create()
      │   └── GenerateHourlyRecordsTask.run($shift)
      └── Return $lastShift->load([details, template, hourlyRecords])
    → ShiftTransformer (defaultIncludes: details; available: hourlyRecords)
```

### Flow cập nhật Shift Template (Update)

```
UpdateShiftTemplateRequest (partial + optional details)
  → UpdateShiftTemplateController
    → UpdateShiftTemplateAction (DB::transaction)
      ├── FindShiftTemplateByIdTask (find existing)
      ├── UpdateShiftTemplateTask (update header fields)
      └── SyncShiftTemplateDetailsTask (delete old + create new)
    → ShiftTemplateTransformer
```

### Flow cập nhật Shift (Update — Smart Sync)

```
UpdateShiftRequest (partial + optional details)
  → UpdateShiftController
    → UpdateShiftAction (DB::transaction)
      ├── Update header fields (supervisor, is_active)
      └── If details provided:
          ├── SyncShiftDetailsTask (upsert on unique key, prune stale)
          └── SyncHourlyRecordsTask
              ├── Snapshot existing records (incl. soft-deleted)
              ├── Compute new record set from updated shift_details
              ├── Soft-delete stale records (preserve actual data)
              └── Upsert (insert new, update existing, restore soft-deleted)
    → ShiftTransformer
```

### Flow xóa Shift (Delete — Soft Delete hourly)

```
DeleteShiftRequest (validate date >= today)
  → DeleteShiftController
    → DeleteShiftAction (DB::transaction)
      ├── HourlyRecord::where(shift_id)->delete()  ← Soft-delete (preserve history)
      └── $shift->delete()  ← Hard-delete (FK cascade → shift_details)
```

### Scheduled Job — Hourly Record Activation

```
ActivateHourlyRecordsJob (runs every hour at :00)
  ├── Get active shifts for today
  ├── Complete: active → completed (slot end hour <= current hour)
  └── Activate: pending → active (slot start hour <= current hour)
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
│   ├── CopyShiftAction.php                 ← Clone shift → target dates
│   ├── CopyShiftTemplateAction.php
│   ├── CreateShiftAction.php               ← Template → shift + details + hourly
│   ├── CreateShiftTemplateAction.php
│   ├── DeleteShiftAction.php               ← Validate date ≥ today
│   ├── DeleteShiftTemplateAction.php
│   ├── FindShiftTemplateAction.php
│   ├── FindShiftWithDetailsAction.php      ← Eager load template+details+hourly
│   ├── GetShiftCalendarAction.php          ← Month calendar grouped by date
│   ├── ListShiftTemplatesAction.php
│   ├── ReorderShiftTemplatesAction.php
│   ├── UpdateHourlyStaffAction.php         ← Batch update staff → recalc target
│   ├── UpdateShiftAction.php
│   └── UpdateShiftTemplateAction.php
├── Configs/
│   ├── appSection-shift.php
│   ├── permissions.php                     ← shift-templates.* + shifts.*
│   └── table-models.php
├── Data/
│   ├── Migrations/
│   │   ├── 2026_03_11_200003_create_shifts_table.php
│   │   ├── 2026_03_25_100001_create_shift_templates_table.php
│   │   ├── 2026_03_25_100002_create_shift_template_details_table.php
│   │   ├── 2026_03_25_200001_create_shift_details_table.php
│   │   ├── 2026_03_25_200002_add_shift_template_id_to_shifts.php
│   │   ├── 2026_04_01_131820_add_kpi_per_hour_to_shift_details.php
│   │   ├── 2026_04_01_132241_add_day_start_inventory_to_shift_details.php
│   │   └── (hourly_records: see Production container migrations)
│   ├── Repositories/
│   │   ├── ShiftDetailRepository.php
│   │   ├── ShiftRepository.php             ← date(=), shift_number(=), is_active(=)
│   │   ├── ShiftTemplateDetailRepository.php
│   │   └── ShiftTemplateRepository.php
│   └── Seeders/
│       ├── ShiftSeeder_1.php
│       └── ShiftTemplateSeeder_1.php
├── Enums/
│   └── ShiftTemplateStatus.php
├── Models/
│   ├── Shift.php                           ← template(), details(), hourlyRecords()
│   ├── ShiftDetail.php                     ← endTime accessor, per-day config
│   ├── ShiftTemplate.php
│   └── ShiftTemplateDetail.php
├── Jobs/
│   └── ActivateHourlyRecordsJob.php       ← [NEW] Hourly cron: pending→active→completed
├── Providers/
│   └── ShiftServiceProvider.php           ← [NEW] Register hourly scheduler
├── Tasks/
│   ├── CopyShiftToDatesTask.php            ← Clone shift+details+hourly (bulk insert)
│   ├── CopyShiftTemplateTask.php
│   ├── CreateShiftFromTemplateTask.php     ← Copy template → shift_details (bulk insert)
│   ├── CreateShiftTemplateTask.php
│   ├── DeleteShiftTemplateTask.php
│   ├── FindShiftTemplateByIdTask.php
│   ├── GenerateHourlyRecordsTask.php       ← Auto-gen hourly (target=kpi×staff, status=pending)
│   ├── ListShiftTemplatesTask.php
│   ├── ListShiftsForMonthTask.php
│   ├── SyncHourlyRecordsTask.php           ← [NEW] Smart sync: preserve actual, soft-delete stale
│   ├── SyncShiftDetailsTask.php            ← Upsert on unique key (not drop+recreate)
│   ├── SyncShiftTemplateDetailsTask.php
│   ├── UpdateHourlyStaffTask.php           ← Recalc target = kpi_per_hour × staff (optimized)
│   └── UpdateShiftTemplateTask.php
└── UI/API/
    ├── Controllers/
    │   ├── CopyShiftController.php
    │   ├── CopyShiftTemplateController.php
    │   ├── CreateShiftController.php
    │   ├── CreateShiftTemplateController.php
    │   ├── DeleteShiftController.php
    │   ├── DeleteShiftTemplateController.php
    │   ├── FindShiftController.php
    │   ├── FindShiftTemplateController.php
    │   ├── GetShiftTemplateDefaultsController.php  ← Default schedule values
    │   ├── GetHourlyRecordsController.php
    │   ├── GetShiftCalendarController.php
    │   ├── ListShiftTemplatesController.php
    │   ├── ReorderShiftTemplatesController.php
    │   ├── UpdateHourlyStaffController.php
    │   ├── UpdateShiftController.php
    │   └── UpdateShiftTemplateController.php
    ├── Requests/
    │   ├── CopyShiftRequest.php
    │   ├── CopyShiftTemplateRequest.php
    │   ├── CreateShiftRequest.php
    │   ├── CreateShiftTemplateRequest.php
    │   ├── DeleteShiftRequest.php
    │   ├── DeleteShiftTemplateRequest.php
    │   ├── FindShiftRequest.php
    │   ├── FindShiftTemplateRequest.php
    │   ├── GetShiftTemplateDefaultsRequest.php
    │   ├── GetHourlyRecordsRequest.php
    │   ├── GetShiftCalendarRequest.php
    │   ├── ListShiftTemplatesRequest.php
    │   ├── ReorderShiftTemplatesRequest.php
    │   ├── UpdateHourlyStaffRequest.php
    │   ├── UpdateShiftRequest.php
    │   └── UpdateShiftTemplateRequest.php
    ├── Routes/
    │   ├── CopyShift.v1.private.php
    │   ├── CopyShiftTemplate.v1.private.php
    │   ├── CreateShift.v1.private.php
    │   ├── CreateShiftTemplate.v1.private.php
    │   ├── DeleteShift.v1.private.php
    │   ├── DeleteShiftTemplate.v1.private.php
    │   ├── FindShift.v1.private.php
    │   ├── FindShiftTemplate.v1.private.php
    │   ├── GetHourlyRecords.v1.private.php
    │   ├── GetShiftTemplateDefaults.v1.private.php
    │   ├── GetShiftCalendar.v1.private.php
    │   ├── ListShiftTemplates.v1.private.php
    │   ├── ReorderShiftTemplates.v1.private.php
    │   ├── UpdateHourlyStaff.v1.private.php
    │   ├── UpdateShift.v1.private.php
    │   └── UpdateShiftTemplate.v1.private.php
    └── Transformers/
        ├── HourlyRecordTransformer.php      ← remaining computed
        ├── ShiftDetailTransformer.php       ← per-day department config
        ├── ShiftTemplateDetailTransformer.php
        ├── ShiftTemplateTransformer.php
        └── ShiftTransformer.php            ← includes: details, hourlyRecords
```
