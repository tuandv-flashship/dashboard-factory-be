# ReasonCode Container

## Kiến trúc 3 cấp

```
reason_categories     (Nhóm lỗi: Machine / Human / Material / Process)
    └── reason_sub_items  (Đối tượng: Máy in DTF, NV cắt, Mực in DTF...)
            └── reason_errors  (Lỗi cụ thể: Tắc đầu phun, Cắt rách film...)
```

**Schema:**

- `reason_categories.id` → nhiều `reason_sub_items.category_id`
- `reason_sub_items.id` → nhiều `reason_errors.sub_item_id`
- `reason_errors.category_id` — denormalized FK (truy vấn nhanh theo category không cần JOIN)

## Departments & Scope

| Dept            | scope_dept value |
| --------------- | ---------------- |
| In ấn           | `print`          |
| Cắt             | `cut`            |
| Mock Up         | `mockup`         |
| Đóng Gói & Ship | `pack_ship`      |
| Pick            | `pick`           |

**scope_type** trên `reason_sub_items`:

| Value                 | Ý nghĩa                                                      |
| --------------------- | ------------------------------------------------------------ |
| `global`              | Áp dụng cho tất cả departments                               |
| `per_department`      | Chỉ áp dụng cho dept cụ thể (`scope_dept`)                   |
| `per_line_department` | Áp dụng cho line + dept cụ thể (`scope_line` + `scope_dept`) |

## API Endpoints

### Public

#### `GET /v1/reason-codes`

Lấy toàn bộ cây lý do lỗi (3 cấp), hỗ trợ nhiều filter kết hợp.

**Query Parameters:**

| Param           | Type    | Default | Mô tả                                                                                               |
| --------------- | ------- | ------- | --------------------------------------------------------------------------------------------------- |
| `line`          | string  | –       | Code của production line (phải tồn tại trong `production_lines.code`). Lọc scope_line của sub_items |
| `dept`          | string  | –       | Code của department (phải tồn tại trong `departments.code`). Lọc scope_dept của sub_items           |
| `scope_type`    | string  | –       | Lọc sub_items theo loại phạm vi: `global` / `per_department` / `per_line_department`                |
| `is_active`     | boolean | `true`  | `true` = chỉ active, `false` = chỉ inactive, **bỏ qua param** = tất cả                              |
| `search`        | string  | –       | Tìm kiếm category theo `code`, `label`, hoặc `label_en` (max 100 ký tự)                             |
| `category_code` | string  | –       | Lọc đúng 1 category theo code (phải tồn tại trong `reason_categories.code`)                         |
| `include`       | string  | –       | Eager-load mối quan hệ: `sub_items`, `errors`, `sub_items.errors`                                   |

**Ví dụ:**

```bash
# Toàn bộ cây active (mặc định)
GET /v1/reason-codes

# Lọc theo context line + dept
GET /v1/reason-codes?line=dtf&dept=print

# Chỉ lấy sub_items global scope
GET /v1/reason-codes?scope_type=global

# Tìm kiếm theo từ khóa, kèm full hierarchy
GET /v1/reason-codes?search=machine&include=sub_items

# Lấy đúng 1 category với full tree
GET /v1/reason-codes?category_code=human&include=sub_items

# Xem cả inactive categories
GET /v1/reason-codes?is_active=false

# Kết hợp nhiều filter
GET /v1/reason-codes?dept=print&scope_type=per_department&include=sub_items
```

**Validation errors (422):**

- `scope_type` không thuộc `global|per_department|per_line_department`
- `line` / `dept` / `category_code` không tồn tại trong DB

### Admin — Categories

| Method | Endpoint                              | Mô tả                          |
| ------ | ------------------------------------- | ------------------------------ |
| GET    | `/v1/admin/reason-categories`         | Danh sách categories           |
| GET    | `/v1/admin/reason-categories/:id`     | Chi tiết category              |
| POST   | `/v1/admin/reason-categories`         | Tạo category mới               |
| PATCH  | `/v1/admin/reason-categories/:id`     | Cập nhật category              |
| DELETE | `/v1/admin/reason-categories/:id`     | Xóa category                   |
| PATCH  | `/v1/admin/reason-categories/reorder` | Đổi thứ tự hàng loạt (1 query) |

### Admin — Sub Items

| Method | Endpoint                             | Mô tả                |
| ------ | ------------------------------------ | -------------------- |
| GET    | `/v1/admin/reason-sub-items`         | Danh sách sub-items  |
| POST   | `/v1/admin/reason-sub-items`         | Tạo sub-item         |
| PATCH  | `/v1/admin/reason-sub-items/:id`     | Cập nhật sub-item    |
| DELETE | `/v1/admin/reason-sub-items/:id`     | Xóa sub-item         |
| PATCH  | `/v1/admin/reason-sub-items/reorder` | Đổi thứ tự hàng loạt |

### Admin — Errors

| Method | Endpoint                          | Mô tả                             |
| ------ | --------------------------------- | --------------------------------- |
| GET    | `/v1/admin/reason-errors`         | Danh sách errors                  |
| POST   | `/v1/admin/reason-errors`         | Tạo error (yêu cầu `sub_item_id`) |
| PATCH  | `/v1/admin/reason-errors/:id`     | Cập nhật error                    |
| DELETE | `/v1/admin/reason-errors/:id`     | Xóa error                         |
| PATCH  | `/v1/admin/reason-errors/reorder` | Đổi thứ tự hàng loạt              |

## Ví dụ Query Patterns

```php
// Full tree cho 1 dept (dùng GetReasonCodes API)
GET /v1/reason-codes?dept=print

// Full tree nested (Eloquent)
ReasonCategory::with('subItems.errors')->get();

// Errors của 1 sub_item
$subItem->errors;

// Errors theo category (denormalized - nhanh)
ReasonError::where('category_id', $catId)->get();
```

## Permissions

| Permission            | Áp dụng cho                |
| --------------------- | -------------------------- |
| `reason-codes.index`  | List endpoints             |
| `reason-codes.create` | Create endpoints           |
| `reason-codes.edit`   | Update + Reorder endpoints |
| `reason-codes.delete` | Delete endpoints           |

## Seeder

```bash
php artisan db:seed --class="App\Containers\AppSection\ReasonCode\Data\Seeders\ReasonCodeSeeder_2"
```

Seed kết quả: **49 sub_items**, **227 errors** (100% có sub_item_id)

## Postman Collection

File: `postman/collections/ReasonCode.postman_collection.json`

Import vào Postman → set variable `API_URL` và `access_token`.
