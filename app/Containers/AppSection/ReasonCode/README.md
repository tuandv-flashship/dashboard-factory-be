# ReasonCode Container

Quản lý động **mã lý do không đạt KPI** cho hệ thống giám sát sản xuất PrintDash.

## Mô tả

Container này thay thế các hàm hardcoded trong FE (`departmentData.ts`) bằng hệ thống CRUD động, cho phép admin thêm/sửa/xóa lý do mà không cần dev can thiệp. Cấu trúc phân cấp 3 lớp:

```
ReasonCategory (4 loại)
├── ReasonSubItem (scoped theo line/dept)
└── ReasonError (scoped theo dept)
```

## Database Schema

### `reason_categories`
| Column | Type | Mô tả |
|---|---|---|
| code | varchar(50) | Unique: `machine`, `human`, `material`, `process` |
| label | varchar | Tên hiển thị: Máy móc, Con người, Nguyên vật liệu, Qui trình |
| label_en | varchar | English label |
| icon | varchar(50) | Lucide icon name: Cog, Users, Package, GitBranch |
| color | varchar(20) | Hex color: #ef4444, #f59e0b, #8b5cf6, #14b8a6 |
| sort_order | smallint | Thứ tự sắp xếp |
| is_active | boolean | Trạng thái hoạt động |

### `reason_sub_items`
| Column | Type | Mô tả |
|---|---|---|
| category_id | FK | → reason_categories |
| code | varchar(100) | VD: `machine-dtf1-print-dtf-01`, `human-absent`, `mat-ink-white` |
| label | varchar | Tên hiển thị: DTF-01, Vắng mặt / Nghỉ phép, Mực trắng |
| scope_type | varchar(20) | `global` \| `per_department` \| `per_line_department` |
| scope_line | varchar(20) | Nullable: dtf1, dtf2, dtg |
| scope_dept | varchar(20) | Nullable: print, cut, mockup, pack_ship, pick, dtg_print |
| sort_order | smallint | Thứ tự sắp xếp |
| is_active | boolean | Trạng thái hoạt động |

**Scope logic:**
- `global`: Hiện cho tất cả line/dept (VD: "Vắng mặt / Nghỉ phép")
- `per_department`: Filter theo dept (VD: "Mực trắng" chỉ cho dept `print`)
- `per_line_department`: Filter theo cả line + dept (VD: "DTF-01" chỉ cho `dtf1/print`)

### `reason_errors`
| Column | Type | Mô tả |
|---|---|---|
| category_id | FK | → reason_categories |
| code | varchar(100) | VD: `err-breakdown`, `herr-late`, `merr-outstock` |
| label | varchar | Tên lỗi cụ thể: Hỏng máy, Đi trễ, Hết hàng |
| scope_dept | varchar(20) | Nullable: null = áp dụng tất cả dept |
| sort_order | smallint | Thứ tự sắp xếp |
| is_active | boolean | Trạng thái hoạt động |

---

## API Endpoints

### Public API (Dashboard FE)

| Method | Endpoint | Auth | Mô tả |
|---|---|---|---|
| GET | `/v1/reason-codes` | Public ✅ | Reason codes filter theo context |

**Query params:**
- `line` (optional): `dtf1`, `dtf2`, `dtg`
- `dept` (optional): `print`, `cut`, `mockup`, `pack_ship`, `pick`, `dtg_print`

**Response:** Tree gồm categories → sub_items + errors đã filter theo context.

---

### Admin API — Query Parameters (Apiato RequestCriteria)

Tất cả admin List endpoints hỗ trợ các query params sau (powered by `addRequestCriteria()`):

| Param | Mô tả | Ví dụ |
|---|---|---|
| `search` | Tìm kiếm theo `$fieldSearchable` | `?search=label:Máy` |
| `searchFields` | Override operator cho từng field | `?searchFields=label:like;code:=` |
| `searchJoin` | Kết hợp điều kiện `and` / `or` | `?searchJoin=and` (default: `and`) |
| `orderBy` | Sắp xếp theo field | `?orderBy=created_at` |
| `sortedBy` | Chiều sắp xếp | `?sortedBy=desc` (default: `asc`) |
| `page` | Trang hiện tại | `?page=2` |
| `limit` | Số item/trang | `?limit=25` (default: 15) |
| `include` | Eager-load relations | `?include=sub_items,errors` |
| `filter` | Chỉ trả về các fields cụ thể | `?filter=id;code;label` |

#### Cú pháp `search`

```bash
# Tìm kiếm 1 field (exact match)
?search=code:machine

# Tìm kiếm 1 field (like — nếu field config là 'like')
?search=label:Máy

# Tìm kiếm nhiều fields
?search=code:machine;is_active:1

# Tìm kiếm toàn bộ fields searchable cùng lúc
?search=Máy
```

#### `$fieldSearchable` theo model

**ReasonCategory:**
| Field | Operator | Ví dụ |
|---|---|---|
| `code` | `=` (exact) | `?search=code:machine` |
| `label` | `like` | `?search=label:Máy` |
| `label_en` | `like` | `?search=label_en:Machine` |
| `is_active` | `=` | `?search=is_active:1` |

**ReasonSubItem:**
| Field | Operator | Ví dụ |
|---|---|---|
| `code` | `=` | `?search=code:machine-dtf1-print-dtf-01` |
| `label` | `like` | `?search=label:DTF` |
| `category_id` | `=` | `?search=category_id:1` |
| `scope_type` | `=` | `?search=scope_type:global` |
| `scope_line` | `=` | `?search=scope_line:dtf1` |
| `scope_dept` | `=` | `?search=scope_dept:print` |
| `is_active` | `=` | `?search=is_active:1` |

**ReasonError:**
| Field | Operator | Ví dụ |
|---|---|---|
| `code` | `=` | `?search=code:err-breakdown` |
| `label` | `like` | `?search=label:Hỏng` |
| `category_id` | `=` | `?search=category_id:1` |
| `scope_dept` | `=` | `?search=scope_dept:print` |
| `is_active` | `=` | `?search=is_active:1` |

#### Ví dụ kết hợp

```bash
# List categories active, sorted by label
GET /v1/admin/reason-categories?search=is_active:1&orderBy=label&sortedBy=asc

# List sub-items của category_id=1, scope print, page 2
GET /v1/admin/reason-sub-items?search=category_id:1;scope_dept:print&page=2&limit=20

# List errors, include category relation, chỉ lấy id + label
GET /v1/admin/reason-errors?include=category&filter=id;code;label

# Find category kèm sub_items + errors
GET /v1/admin/reason-categories/{id}?include=sub_items,errors
```

---

### Admin API — ReasonCategory

Tất cả yêu cầu `auth:api` + permission `reason-codes.*`.

| Method | Endpoint | Permission | Mô tả |
|---|---|---|---|
| GET | `/v1/admin/reason-categories` | `reason-codes.index` | Danh sách categories (paginated, searchable) |
| GET | `/v1/admin/reason-categories/{id}` | `reason-codes.index` | Chi tiết 1 category (`?include=sub_items,errors`) |
| POST | `/v1/admin/reason-categories` | `reason-codes.create` | Tạo mới category |
| PATCH | `/v1/admin/reason-categories/{id}` | `reason-codes.edit` | Cập nhật category (partial update) |
| DELETE | `/v1/admin/reason-categories/{id}` | `reason-codes.destroy` | Xóa category (cascade xóa sub_items + errors) |
| PATCH | `/v1/admin/reason-categories/reorder` | `reason-codes.edit` | Sắp xếp lại thứ tự |

**POST /v1/admin/reason-categories** — Body:
```json
{
  "code": "machine",
  "label": "Máy móc",
  "label_en": "Machine",
  "icon": "Cog",
  "color": "#ef4444",
  "sort_order": 0,
  "is_active": true
}
```

**PATCH /v1/admin/reason-categories/reorder** — Body:
```json
{
  "items": [
    { "id": 1, "sort_order": 0 },
    { "id": 2, "sort_order": 1 }
  ]
}
```

---

### Admin API — ReasonSubItem

| Method | Endpoint | Permission | Mô tả |
|---|---|---|---|
| GET | `/v1/admin/reason-sub-items` | `reason-codes.index` | Danh sách sub-items (filterable by `category_id`) |
| POST | `/v1/admin/reason-sub-items` | `reason-codes.create` | Tạo mới sub-item |
| PATCH | `/v1/admin/reason-sub-items/{id}` | `reason-codes.edit` | Cập nhật sub-item |
| DELETE | `/v1/admin/reason-sub-items/{id}` | `reason-codes.destroy` | Xóa sub-item |
| PATCH | `/v1/admin/reason-sub-items/reorder` | `reason-codes.edit` | Sắp xếp lại thứ tự |

**Query params (GET):** `category_id` (optional) — filter theo category.

**POST /v1/admin/reason-sub-items** — Body:
```json
{
  "category_id": "HASHED_ID",
  "code": "machine-dtf1-print-dtf-01",
  "label": "DTF-01",
  "scope_type": "per_line_department",
  "scope_line": "dtf1",
  "scope_dept": "print",
  "sort_order": 0,
  "is_active": true
}
```

> `scope_type` phải là: `global`, `per_department`, hoặc `per_line_department`

---

### Admin API — ReasonError

| Method | Endpoint | Permission | Mô tả |
|---|---|---|---|
| GET | `/v1/admin/reason-errors` | `reason-codes.index` | Danh sách errors (filterable by `category_id`) |
| POST | `/v1/admin/reason-errors` | `reason-codes.create` | Tạo mới error |
| PATCH | `/v1/admin/reason-errors/{id}` | `reason-codes.edit` | Cập nhật error |
| DELETE | `/v1/admin/reason-errors/{id}` | `reason-codes.destroy` | Xóa error |
| PATCH | `/v1/admin/reason-errors/reorder` | `reason-codes.edit` | Sắp xếp lại thứ tự |

**POST /v1/admin/reason-errors** — Body:
```json
{
  "category_id": "HASHED_ID",
  "code": "err-breakdown",
  "label": "Hỏng máy",
  "scope_dept": "print",
  "sort_order": 0,
  "is_active": true
}
```

---

## Permissions

Khai báo trong `Configs/permissions.php`, sync bằng: `php artisan apiato:permissions-sync`

```
reason-codes.index   → parent: core.system      (List/Find)
├── reason-codes.create   → parent: reason-codes.index
├── reason-codes.edit     → parent: reason-codes.index
└── reason-codes.destroy  → parent: reason-codes.index
```

---

## FE Integration

### Dashboard (Read-only)
```typescript
import { useReasonCodes } from "@/hooks/useApi";
const { data: reasons } = useReasonCodes("dtf1", "print"); // staleTime 5min
// reasons = tree: categories[] → sub_items[] + errors[]
```

---

## Seeder Data

Chạy seeder: `php artisan db:seed --class="App\Containers\AppSection\ReasonCode\Data\Seeders\ReasonCodeSeeder_1"`

- **4 categories**: machine, human, material, process
- **~50 machine sub-items**: per line + dept (DTF1: 19, DTF2: 12, DTG: 5)
- **5 human sub-items**: global
- **~18 material sub-items**: per dept (print: 4, dtg_print: 3, cut: 2, mockup: 3, pack_ship: 4, pick: 2)
- **6 process sub-items**: global
- **~30 machine errors**: 5 common + dept-specific + "Khác"
- **6 human errors**, **6 material errors**, **7 process errors**: global

---

## Architecture

```
Route → Request ($decode, rules(), authorize() with can())
  → Controller (app(Action)->run($request), $this->transform())
    → Action (app(Task)->run(...))
      → Task ($this->repository->create/find/update/delete)
        → Repository ($fieldSearchable, model())
          → Model
```

### Repositories

| Repository | Model | Searchable Fields |
|---|---|---|
| `ReasonCategoryRepository` | `ReasonCategory` | code(=), label(like), label_en(like), is_active(=) |
| `ReasonSubItemRepository` | `ReasonSubItem` | code(=), label(like), category_id(=), scope_type(=), scope_line(=), scope_dept(=), is_active(=) |
| `ReasonErrorRepository` | `ReasonError` | code(=), label(like), category_id(=), scope_dept(=), is_active(=) |

---

## File Structure

```
ReasonCode/
├── Actions/
│   ├── GetReasonCodesAction.php           ← Public API
│   ├── ListReasonCategoriesAction.php     ← Admin CRUD
│   ├── FindReasonCategoryAction.php
│   ├── CreateReasonCategoryAction.php
│   ├── UpdateReasonCategoryAction.php
│   ├── DeleteReasonCategoryAction.php
│   ├── ReorderReasonCategoriesAction.php
│   ├── ListReasonSubItemsAction.php
│   ├── CreateReasonSubItemAction.php
│   ├── UpdateReasonSubItemAction.php
│   ├── DeleteReasonSubItemAction.php
│   ├── ReorderReasonSubItemsAction.php
│   ├── ListReasonErrorsAction.php
│   ├── CreateReasonErrorAction.php
│   ├── UpdateReasonErrorAction.php
│   ├── DeleteReasonErrorAction.php
│   └── ReorderReasonErrorsAction.php
├── Configs/
│   └── permissions.php                    ← Permission flags
├── Data/
│   ├── Migrations/
│   │   ├── ..._create_reason_categories_table.php
│   │   ├── ..._create_reason_sub_items_table.php
│   │   └── ..._create_reason_errors_table.php
│   ├── Repositories/
│   │   ├── ReasonCategoryRepository.php
│   │   ├── ReasonSubItemRepository.php
│   │   └── ReasonErrorRepository.php
│   └── Seeders/ReasonCodeSeeder_1.php
├── Enums/ScopeType.php
├── Models/
│   ├── ReasonCategory.php
│   ├── ReasonSubItem.php (scopeForContext)
│   └── ReasonError.php (scopeForDept)
├── Tasks/
│   ├── GetReasonCodesForContextTask.php   ← Public API
│   ├── ListReasonCategoriesTask.php       ← Admin CRUD
│   ├── FindReasonCategoryByIdTask.php
│   ├── CreateReasonCategoryTask.php
│   ├── UpdateReasonCategoryTask.php
│   ├── DeleteReasonCategoryTask.php
│   ├── ReorderReasonCategoriesTask.php
│   ├── ListReasonSubItemsTask.php
│   ├── CreateReasonSubItemTask.php
│   ├── UpdateReasonSubItemTask.php
│   ├── DeleteReasonSubItemTask.php
│   ├── ReorderReasonSubItemsTask.php
│   ├── ListReasonErrorsTask.php
│   ├── CreateReasonErrorTask.php
│   ├── UpdateReasonErrorTask.php
│   ├── DeleteReasonErrorTask.php
│   └── ReorderReasonErrorsTask.php
├── Tests/
│   ├── ContainerTestCase.php
│   ├── UnitTestCase.php
│   └── Unit/
│       ├── Models/ReasonCodeModelsTest.php (5 methods)
│       └── Tasks/GetReasonCodesForContextTaskTest.php (2 methods)
└── UI/API/
    ├── Controllers/
    │   ├── GetReasonCodesController.php          ← Public
    │   ├── ListReasonCategoriesController.php    ← Admin
    │   ├── FindReasonCategoryController.php
    │   ├── CreateReasonCategoryController.php
    │   ├── UpdateReasonCategoryController.php
    │   ├── DeleteReasonCategoryController.php
    │   ├── ReorderReasonCategoriesController.php
    │   ├── ListReasonSubItemsController.php
    │   ├── CreateReasonSubItemController.php
    │   ├── UpdateReasonSubItemController.php
    │   ├── DeleteReasonSubItemController.php
    │   ├── ReorderReasonSubItemsController.php
    │   ├── ListReasonErrorsController.php
    │   ├── CreateReasonErrorController.php
    │   ├── UpdateReasonErrorController.php
    │   ├── DeleteReasonErrorController.php
    │   └── ReorderReasonErrorsController.php
    ├── Requests/
    │   ├── GetReasonCodesRequest.php             ← Public
    │   ├── ListReasonCategoriesRequest.php       ← Admin
    │   ├── FindReasonCategoryRequest.php
    │   ├── CreateReasonCategoryRequest.php
    │   ├── UpdateReasonCategoryRequest.php
    │   ├── DeleteReasonCategoryRequest.php
    │   ├── ReorderReasonCategoriesRequest.php
    │   ├── ListReasonSubItemsRequest.php
    │   ├── CreateReasonSubItemRequest.php
    │   ├── UpdateReasonSubItemRequest.php
    │   ├── DeleteReasonSubItemRequest.php
    │   ├── ReorderReasonSubItemsRequest.php
    │   ├── ListReasonErrorsRequest.php
    │   ├── CreateReasonErrorRequest.php
    │   ├── UpdateReasonErrorRequest.php
    │   ├── DeleteReasonErrorRequest.php
    │   └── ReorderReasonErrorsRequest.php
    ├── Routes/
    │   ├── GetReasonCodes.v1.private.php         ← Public
    │   ├── GetReasonCodes.v1.public.php          ← TV Dashboard
    │   ├── ListReasonCategories.v1.private.php   ← Admin
    │   ├── FindReasonCategory.v1.private.php
    │   ├── CreateReasonCategory.v1.private.php
    │   ├── UpdateReasonCategory.v1.private.php
    │   ├── DeleteReasonCategory.v1.private.php
    │   ├── ReorderReasonCategories.v1.private.php
    │   ├── ListReasonSubItems.v1.private.php
    │   ├── CreateReasonSubItem.v1.private.php
    │   ├── UpdateReasonSubItem.v1.private.php
    │   ├── DeleteReasonSubItem.v1.private.php
    │   ├── ReorderReasonSubItems.v1.private.php
    │   ├── ListReasonErrors.v1.private.php
    │   ├── CreateReasonError.v1.private.php
    │   ├── UpdateReasonError.v1.private.php
    │   ├── DeleteReasonError.v1.private.php
    │   └── ReorderReasonErrors.v1.private.php
    └── Transformers/
        ├── ReasonCategoryTransformer.php
        ├── ReasonSubItemTransformer.php
        └── ReasonErrorTransformer.php
```
