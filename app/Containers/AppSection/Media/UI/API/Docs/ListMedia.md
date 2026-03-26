# List Media

## Mục đích

Liệt kê file media theo folder và chế độ view, hỗ trợ phân trang, tìm kiếm, lọc theo loại file và sắp xếp.

## Endpoint

`GET /v1/media/list`

## Auth & quyền

- **Bearer token** (`auth:api`)
- Permission: `media.index`

---

## Query Parameters

### `folder_id` — ID thư mục

| | |
|---|---|
| **Type** | `String` (hashed ID) |
| **Required** | Không |
| **Default** | `0` (thư mục gốc / root) |
| **Mô tả** | ID folder đã hash (ví dụ: `v9jEX5zd5kDdnW2O`). Truyền `0` hoặc bỏ trống để lấy file ở thư mục gốc. |

### `view_in` — Chế độ xem

| | |
|---|---|
| **Type** | `String` |
| **Required** | Không |
| **Default** | `all_media` |
| **Giá trị** | `all_media` · `trash` · `recent` · `favorites` |

| Giá trị | Mô tả |
|---------|-------|
| `all_media` | Hiện tất cả file/folder bình thường (chưa xóa) |
| `trash` | Hiện các file/folder đã bị xóa mềm (soft-deleted) |
| `recent` | Hiện các file/folder mà user đã truy cập gần đây |
| `favorites` | Hiện các file/folder mà user đã đánh dấu yêu thích |

### `search` — Tìm kiếm theo tên

| | |
|---|---|
| **Type** | `String` |
| **Required** | Không |
| **Default** | — (không lọc) |
| **Max length** | 255 ký tự |
| **Mô tả** | Tìm kiếm theo tên file và folder (LIKE match). Ví dụ: `search=banner` sẽ khớp `banner-homepage.jpg`, `my_banner.png`, ... |

### `sort_by` — Sắp xếp

| | |
|---|---|
| **Type** | `String` |
| **Required** | Không |
| **Default** | `name-asc` |
| **Format** | `{column}-{direction}` |
| **Giá trị** | `name-asc` · `name-desc` · `created_at-asc` · `created_at-desc` · `size-asc` · `size-desc` |

| Giá trị | Mô tả |
|---------|-------|
| `name-asc` | Tên A → Z |
| `name-desc` | Tên Z → A |
| `created_at-asc` | Cũ nhất trước |
| `created_at-desc` | Mới nhất trước |
| `size-asc` | Nhỏ nhất trước |
| `size-desc` | Lớn nhất trước |

> **Lưu ý:** `sort_by` chỉ áp dụng cho **files**. Folders luôn sắp xếp theo `name ASC`.

### `filter` — Lọc theo loại file

| | |
|---|---|
| **Type** | `String` |
| **Required** | Không |
| **Default** | `everything` |
| **Giá trị** | `everything` · `image` · `video` · `document` · `zip` · `audio` |

| Giá trị | MIME types được bao gồm |
|---------|------------------------|
| `everything` | Tất cả loại file |
| `image` | `image/png`, `image/jpeg`, `image/gif`, `image/bmp`, `image/svg+xml`, `image/webp`, `image/avif` |
| `video` | `video/mp4`, `video/m4v`, `video/mov`, `video/quicktime` |
| `document` | `application/pdf`, `application/vnd.ms-excel`, `application/excel`, `text/plain`, `application/msword`, `text/csv`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `application/vnd.ms-powerpoint`, `application/vnd.openxmlformats-officedocument.presentationml.presentation` |
| `zip` | `application/zip`, `application/x-zip-compressed`, `application/x-compressed`, `multipart/x-zip`, `multipart/x-rar`, `application/x-rar-compressed`, `application/vnd.rar` |
| `audio` | `audio/mpeg`, `audio/mp3`, `audio/wav` |

> **Lưu ý:** `filter` chỉ áp dụng cho **files**, không ảnh hưởng đến danh sách folders.

### `limit` — Số file mỗi trang

| | |
|---|---|
| **Type** | `Integer` |
| **Required** | Không |
| **Default** | `10` |
| **Range** | `1` – `200` |
| **Mô tả** | Số file tối đa trả về mỗi trang. Chỉ áp dụng cho **files** (folders luôn trả về tất cả). |

### `page` — Trang hiện tại

| | |
|---|---|
| **Type** | `Integer` |
| **Required** | Không |
| **Default** | `1` |
| **Min** | `1` |
| **Mô tả** | Số trang cần lấy. Kết hợp với `limit` để phân trang. |

### `selected_file_id` — ID file đang chọn

| | |
|---|---|
| **Type** | `String` (hashed ID) |
| **Required** | Không |
| **Default** | — (null) |
| **Mô tả** | Hashed ID của file đang được chọn trên giao diện. Giá trị này sẽ được trả về nguyên vẹn trong response (`data.selected_file_id`) để FE có thể duy trì trạng thái chọn file sau khi load lại danh sách. |

### `include_signed_url` — Trả kèm signed URL

| | |
|---|---|
| **Type** | `Boolean` |
| **Required** | Không |
| **Default** | `false` |
| **Giá trị** | `true` hoặc `false` (hoặc `1` / `0`) |
| **Mô tả** | Khi `true`, mỗi file trong response sẽ có thêm field `signed_url`. Signed URL chỉ được tạo cho file có `access_mode = "signed"` (file private). Dùng khi cần preview/download file private ngay trên giao diện. |

---

## Response thành công (`200 OK`)

```json
{
  "data": {
    "files": [ ... ],
    "folders": [ ... ],
    "breadcrumbs": [ ... ],
    "pagination": { ... },
    "selected_file_id": "abc123"
  }
}
```

### `data.files[]` — Danh sách files

| Field | Type | Mô tả |
|-------|------|-------|
| `id` | `String` | Hashed ID của file |
| `name` | `String` | Tên file (bao gồm extension) |
| `basename` | `String` | Tên file gốc khi upload |
| `url` | `String` | Đường dẫn relative của file trên storage |
| `full_url` | `String\|null` | URL đầy đủ (chỉ có khi file là `public`) |
| `type` | `String` | Loại file: `image`, `video`, `document`, `zip`, `audio` |
| `thumb` | `String\|null` | URL thumbnail (chỉ có với image public) |
| `size` | `String` | Kích thước dạng human-readable (ví dụ: `"2.5 MB"`) |
| `mime_type` | `String` | MIME type (ví dụ: `"image/jpeg"`) |
| `created_at` | `String` | ISO 8601 datetime |
| `updated_at` | `String` | ISO 8601 datetime |
| `options` | `Object\|null` | Metadata bổ sung (width, height cho ảnh...) |
| `folder_id` | `String\|null` | Hashed ID folder chứa file |
| `preview_url` | `String\|null` | URL preview (cho document) |
| `preview_type` | `String\|null` | Loại preview: `iframe`, ... |
| `indirect_url` | `String\|null` | URL gián tiếp qua endpoint `/v1/media/files/{hash}/{id}` |
| `alt` | `String\|null` | Alt text cho ảnh |
| `visibility` | `String` | `"public"` hoặc `"private"` |
| `access_mode` | `String` | `"public"`, `"auth"` hoặc `"signed"` |
| `signed_url` | `String\|null` | *(Chỉ khi `include_signed_url=true`)* URL có chữ ký, hết hạn sau N phút |

### `data.folders[]` — Danh sách folders

| Field | Type | Mô tả |
|-------|------|-------|
| `id` | `String` | Hashed ID của folder |
| `name` | `String` | Tên folder |
| `color` | `String\|null` | Mã màu hex (ví dụ: `"#3498db"`) |
| `created_at` | `String` | ISO 8601 datetime |
| `updated_at` | `String` | ISO 8601 datetime |

### `data.breadcrumbs[]` — Đường dẫn breadcrumb

| Field | Type | Mô tả |
|-------|------|-------|
| `id` | `String\|Integer` | Hashed ID folder (hoặc `0` cho root) |
| `name` | `String` | Tên hiển thị: `"All Media"`, `"Trash"`, `"Recent"`, `"Favorites"`, hoặc tên folder |

### `data.pagination` — Thông tin phân trang (chỉ cho files)

| Field | Type | Mô tả |
|-------|------|-------|
| `total` | `Integer` | Tổng số files khớp điều kiện |
| `per_page` | `Integer` | Số file mỗi trang |
| `current_page` | `Integer` | Trang hiện tại |
| `last_page` | `Integer` | Trang cuối cùng |

### `data.selected_file_id`

| Type | Mô tả |
|------|-------|
| `String\|null` | Hashed ID file đang chọn (echo lại từ request) |

---

## Lưu ý FE

1. **Folder navigation**: Giữ `folder_id` theo selected node trên tree. Khi user click vào folder, truyền `folder_id` tương ứng.
2. **Private files**: Với file `visibility = "private"`, `full_url` sẽ là `null`. Dùng `indirect_url` hoặc bật `include_signed_url=true` để lấy `signed_url` preview.
3. **Pagination**: Chỉ **files** được phân trang. **Folders** luôn trả về tất cả trong folder hiện tại.
4. **Trash**: Khi `view_in=trash`, cả files và folders đã xóa mềm đều được trả về.
5. **Recent / Favorites**: Danh sách dựa trên hoạt động của **user hiện tại**, không phải toàn hệ thống.
