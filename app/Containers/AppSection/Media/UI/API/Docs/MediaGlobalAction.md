# Media Global Actions

## Mục đích

Thực hiện các thao tác hàng loạt trên file/folder media: xóa mềm, khôi phục, di chuyển, sao chép, đổi tên, crop ảnh, quản lý yêu thích, lịch sử truy cập gần đây, v.v.

Tất cả các thao tác đều gửi qua **một endpoint duy nhất**, phân biệt bằng field `action`.

## Endpoint

```
POST /v1/media/actions
```

## Auth & Headers

| Header | Giá trị | Bắt buộc |
|--------|---------|----------|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

> **Lưu ý:** Endpoint yêu cầu xác thực (`auth:api`). Mỗi action sẽ kiểm tra permission riêng (xem bảng phía dưới).

---

## Cấu trúc Request chung

```json
{
  "action": "<tên_action>",
  ...các field khác tuỳ theo action
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `action` | `String` | ✅ | Tên action cần thực hiện (xem danh sách bên dưới) |

---

## Danh sách Actions

| Action | Mô tả | Permission |
|--------|-------|------------|
| `trash` | Xóa mềm (chuyển vào Trash) | `files.trash` hoặc `folders.trash` |
| `restore` | Khôi phục từ Trash | `files.edit` hoặc `folders.edit` |
| `move` | Di chuyển sang folder khác | `files.edit` hoặc `folders.edit` |
| `make_copy` | Tạo bản sao | `files.create` hoặc `folders.create` |
| `delete` | Xóa vĩnh viễn (force delete) | `files.destroy` hoặc `folders.destroy` |
| `rename` | Đổi tên file/folder | `files.edit` hoặc `folders.edit` |
| `alt_text` | Cập nhật alt text cho ảnh | `files.edit` |
| `crop` | Cắt ảnh theo tọa độ | `files.edit` |
| `favorite` | Thêm vào danh sách yêu thích | `media.index` |
| `remove_favorite` | Xóa khỏi danh sách yêu thích | `media.index` |
| `add_recent` | Thêm vào lịch sử truy cập gần đây | `media.index` |
| `empty_trash` | Xóa vĩnh viễn toàn bộ Trash | `files.destroy` hoặc `folders.destroy` |
| `properties` | Cập nhật thuộc tính folder (màu sắc) | `files.edit` hoặc `folders.edit` |

---

## Lưu ý quan trọng về Hashed ID

Các field sau sử dụng **hashed ID** (chuỗi đã encode, ví dụ `"v9jEX5zd5kDdnW2O"`):
- `selected[].id`
- `destination`
- `item.id`
- `imageId`

> FE cần truyền hashed ID nhận được từ các API khác (ví dụ từ `GET /v1/media/list`). **Không truyền ID số nguyên thuần (raw integer).**

---

## Chi tiết từng Action

### 1. `trash` — Xóa mềm (Soft Delete)

Chuyển file/folder vào Trash. Có thể khôi phục sau đó bằng action `restore`.

**Request:**

```json
{
  "action": "trash",
  "selected": [
    { "id": "v9jEX5zd5kDdnW2O", "is_folder": false },
    { "id": "a3bKd2mN7pQrWx1Y", "is_folder": true }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `selected` | `Array` | ✅ | Danh sách items cần xóa mềm |
| `selected[].id` | `String` | ✅ | Hashed ID của file/folder |
| `selected[].is_folder` | `Boolean` | Không | `true` nếu là folder, `false` nếu là file (mặc định `false`) |

**Tùy chọn bổ sung:**

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `skip_trash` | `Boolean` | Không | Nếu `true`, sẽ **xóa vĩnh viễn** thay vì xóa mềm. Mặc định `false`. |

> ⚠️ Khi `skip_trash = true`, dữ liệu sẽ bị xóa vĩnh viễn và **không thể khôi phục**. Folder sẽ bị xóa **đệ quy** (bao gồm tất cả folder con và files bên trong).

**Response thành công:**

```json
{
  "data": {
    "message": "Moved to trash successfully."
  }
}
```

---

### 2. `restore` — Khôi phục từ Trash

Khôi phục file/folder đã bị xóa mềm.

**Request:**

```json
{
  "action": "restore",
  "selected": [
    { "id": "v9jEX5zd5kDdnW2O", "is_folder": false },
    { "id": "a3bKd2mN7pQrWx1Y", "is_folder": true }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `selected` | `Array` | ✅ | Danh sách items cần khôi phục |
| `selected[].id` | `String` | ✅ | Hashed ID |
| `selected[].is_folder` | `Boolean` | Không | `true` nếu là folder |

> Folder sẽ được khôi phục **đệ quy** (bao gồm tất cả folder con bên trong).

**Response thành công:**

```json
{
  "data": {
    "message": "Restored successfully."
  }
}
```

---

### 3. `move` — Di chuyển

Di chuyển file/folder sang thư mục đích.

**Request:**

```json
{
  "action": "move",
  "destination": "x7kLm3nP9qRsWt2U",
  "selected": [
    { "id": "v9jEX5zd5kDdnW2O", "is_folder": false },
    { "id": "a3bKd2mN7pQrWx1Y", "is_folder": true }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `destination` | `String` | ✅ | Hashed ID folder đích. Truyền `0` để di chuyển về thư mục gốc (root). |
| `selected` | `Array` | Không | Danh sách items cần di chuyển |
| `selected[].id` | `String` | ✅ | Hashed ID |
| `selected[].is_folder` | `Boolean` | Không | `true` nếu là folder |

**Validation:** `destination` phải là integer ≥ 0 (sau khi decode hashed ID).

**Response thành công:**

```json
{
  "data": {
    "message": "Moved successfully."
  }
}
```

---

### 4. `make_copy` — Tạo bản sao

Sao chép file/folder. Bản sao sẽ có tên gốc kèm hậu tố `-(copy)`.

**Request:**

```json
{
  "action": "make_copy",
  "selected": [
    { "id": "v9jEX5zd5kDdnW2O", "is_folder": false },
    { "id": "a3bKd2mN7pQrWx1Y", "is_folder": true }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `selected` | `Array` | ✅ | Danh sách items cần sao chép |
| `selected[].id` | `String` | ✅ | Hashed ID |
| `selected[].is_folder` | `Boolean` | Không | `true` nếu là folder |

> Khi copy folder, tất cả file và folder con bên trong sẽ được sao chép **đệ quy**.

**Response thành công:**

```json
{
  "data": {
    "message": "Copied successfully."
  }
}
```

---

### 5. `delete` — Xóa vĩnh viễn (Force Delete)

Xóa vĩnh viễn file/folder (kể cả đã ở trong Trash hay chưa).

**Request:**

```json
{
  "action": "delete",
  "selected": [
    { "id": "v9jEX5zd5kDdnW2O", "is_folder": false }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `selected` | `Array` | ✅ | Danh sách items cần xóa vĩnh viễn |
| `selected[].id` | `String` | ✅ | Hashed ID |
| `selected[].is_folder` | `Boolean` | Không | `true` nếu là folder |

> ⚠️ **Không thể khôi phục** sau khi xóa vĩnh viễn. Folder sẽ bị xóa **đệ quy**.

**Response thành công:**

```json
{
  "data": {
    "message": "Deleted successfully."
  }
}
```

---

### 6. `rename` — Đổi tên

Đổi tên file hoặc folder.

**Request:**

```json
{
  "action": "rename",
  "selected": [
    {
      "id": "v9jEX5zd5kDdnW2O",
      "is_folder": false,
      "name": "new-file-name",
      "rename_physical_file": true
    }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `selected` | `Array` | ✅ | Danh sách items cần đổi tên |
| `selected[].id` | `String` | ✅ | Hashed ID |
| `selected[].is_folder` | `Boolean` | Không | `true` nếu là folder |
| `selected[].name` | `String` | ✅ | Tên mới (tối đa 120 ký tự). Không cần extension — hệ thống tự xử lý. |
| `selected[].rename_physical_file` | `Boolean` | Không | Nếu `true`, file/folder vật lý trên storage cũng sẽ được đổi tên. Mặc định `false` (chỉ đổi tên trong database). |

**Response thành công:**

```json
{
  "data": {
    "message": "Renamed successfully."
  }
}
```

---

### 7. `alt_text` — Cập nhật Alt Text

Cập nhật alt text cho file ảnh (dùng cho SEO và accessibility).

**Request:**

```json
{
  "action": "alt_text",
  "selected": [
    { "id": "v9jEX5zd5kDdnW2O", "alt": "Banner trang chủ mùa hè 2025" },
    { "id": "k8mNp3qR7sWx2yZ1", "alt": null }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `selected` | `Array` | ✅ | Danh sách files cần cập nhật alt |
| `selected[].id` | `String` | ✅ | Hashed ID file |
| `selected[].alt` | `String\|null` | Không | Alt text mới (tối đa 220 ký tự). Truyền `null` để xóa alt text. |

**Response thành công:**

```json
{
  "data": {
    "message": "Alt text updated."
  }
}
```

---

### 8. `crop` — Cắt ảnh

Cắt ảnh theo tọa độ và kích thước chỉ định.

**Request:**

```json
{
  "action": "crop",
  "imageId": "v9jEX5zd5kDdnW2O",
  "cropData": {
    "x": 100,
    "y": 50,
    "width": 800,
    "height": 600
  }
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `imageId` | `String` | ✅ | Hashed ID của file ảnh cần crop |
| `cropData` | `Object` | ✅ | Dữ liệu vùng cắt |
| `cropData.x` | `Integer` | ✅ | Tọa độ X (pixel) của góc trên-trái vùng cắt |
| `cropData.y` | `Integer` | ✅ | Tọa độ Y (pixel) của góc trên-trái vùng cắt |
| `cropData.width` | `Integer` | ✅ | Chiều rộng vùng cắt (pixel, phải > 0) |
| `cropData.height` | `Integer` | ✅ | Chiều cao vùng cắt (pixel, phải > 0) |

> `cropData` có thể truyền dưới dạng object hoặc JSON string — server tự parse.

**Response thành công:**

```json
{
  "data": {
    "message": "Cropped successfully."
  }
}
```

**Response thất bại (ảnh không tồn tại):**

```json
{
  "data": {
    "message": "Image not found."
  }
}
```

---

### 9. `favorite` — Thêm vào Yêu thích

Đánh dấu file/folder vào danh sách yêu thích của user hiện tại.

**Request:**

```json
{
  "action": "favorite",
  "selected": [
    { "id": "v9jEX5zd5kDdnW2O", "is_folder": false },
    { "id": "a3bKd2mN7pQrWx1Y", "is_folder": true }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `selected` | `Array` | ✅ | Danh sách items cần thêm vào favorites |
| `selected[].id` | `String` | ✅ | Hashed ID |
| `selected[].is_folder` | `Boolean` | Không | `true` nếu là folder |

> Danh sách favorites là **riêng từng user**, không ảnh hưởng đến user khác.

**Response thành công:**

```json
{
  "data": {
    "message": "Added to favorites."
  }
}
```

---

### 10. `remove_favorite` — Xóa khỏi Yêu thích

Xóa file/folder khỏi danh sách yêu thích.

**Request:**

```json
{
  "action": "remove_favorite",
  "selected": [
    { "id": "v9jEX5zd5kDdnW2O", "is_folder": false }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `selected` | `Array` | ✅ | Danh sách items cần xóa khỏi favorites |
| `selected[].id` | `String` | ✅ | Hashed ID |
| `selected[].is_folder` | `Boolean` | Không | `true` nếu là folder |

> So khớp bằng **cặp `(id, is_folder)`** — cần truyền đúng `is_folder` để xóa chính xác.

**Response thành công:**

```json
{
  "data": {
    "message": "Removed from favorites."
  }
}
```

---

### 11. `add_recent` — Thêm vào Lịch sử gần đây

Ghi nhận file/folder vào lịch sử truy cập gần đây. Thường gọi khi user **click vào/xem** một file.

**Request:**

```json
{
  "action": "add_recent",
  "item": {
    "id": "v9jEX5zd5kDdnW2O",
    "is_folder": false
  }
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `item` | `Object` | ✅ | Item cần thêm vào recent (**chỉ một item**, không phải mảng) |
| `item.id` | `String` | ✅ | Hashed ID |
| `item.is_folder` | `Boolean` | Không | `true` nếu là folder |

> **Lưu ý:**
> - Khác với các action khác, `add_recent` dùng field `item` (object đơn) thay vì `selected` (array).
> - Hệ thống tự động deduplicate: nếu item đã có trong recent, nó sẽ được **đưa lên đầu** danh sách.
> - Danh sách recent giới hạn tối đa **20 items**.

**Response thành công:**

```json
{
  "data": {
    "message": "Added to recent."
  }
}
```

---

### 12. `empty_trash` — Xóa toàn bộ Trash

Xóa vĩnh viễn **tất cả** file và folder đang ở trong Trash.

**Request:**

```json
{
  "action": "empty_trash"
}
```

> Không cần truyền thêm field nào.

> ⚠️ **Hành động này không thể hoàn tác.** Nên hiển thị dialog xác nhận cho user trước khi gọi.

**Response thành công:**

```json
{
  "data": {
    "message": "Trash emptied."
  }
}
```

---

### 13. `properties` — Cập nhật thuộc tính Folder

Cập nhật màu sắc cho folder (dùng để phân biệt trực quan trên giao diện).

**Request:**

```json
{
  "action": "properties",
  "color": "#3498db",
  "selected": [
    { "id": "a3bKd2mN7pQrWx1Y" },
    { "id": "b4cLe3nO8pRsXt2V" }
  ]
}
```

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `color` | `String` | ✅ | Mã màu (tối đa 20 ký tự, ví dụ `"#3498db"`, `"red"`) |
| `selected` | `Array` | ✅ | Danh sách **folders** cần cập nhật |
| `selected[].id` | `String` | ✅ | Hashed ID folder |

> Chỉ áp dụng cho **folders**, không áp dụng cho files.

**Response thành công:**

```json
{
  "data": {
    "message": "Properties updated."
  }
}
```

---

## Response lỗi

### Validation Error (`422 Unprocessable Entity`)

Khi request không hợp lệ (thiếu field bắt buộc, sai kiểu dữ liệu, action không hợp lệ...).

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "action": ["The selected action is invalid."],
    "selected": ["The selected field is required."]
  }
}
```

### Unauthorized (`401 Unauthorized`)

Khi không có hoặc token hết hạn.

```json
{
  "message": "Unauthenticated."
}
```

### Forbidden (`403 Forbidden`)

Khi user không có permission cho action yêu cầu.

```json
{
  "message": "This action is unauthorized."
}
```

### Invalid Action (trong `data`)

Khi truyền action không nằm trong danh sách hỗ trợ (nhưng qua được validation — trường hợp hiếm).

```json
{
  "data": {
    "message": "Invalid action."
  }
}
```

---

## Ví dụ tích hợp (JavaScript / Axios)

### Setup cơ bản

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://your-api-domain.com/v1',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Thêm token vào mỗi request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

### Xóa mềm nhiều items

```javascript
await api.post('/media/actions', {
  action: 'trash',
  selected: [
    { id: 'v9jEX5zd5kDdnW2O', is_folder: false },
    { id: 'a3bKd2mN7pQrWx1Y', is_folder: true },
  ],
});
```

### Di chuyển files sang folder khác

```javascript
await api.post('/media/actions', {
  action: 'move',
  destination: 'x7kLm3nP9qRsWt2U', // hashed ID folder đích
  selected: [
    { id: 'v9jEX5zd5kDdnW2O', is_folder: false },
  ],
});
```

### Đổi tên file (kèm đổi tên file vật lý)

```javascript
await api.post('/media/actions', {
  action: 'rename',
  selected: [
    {
      id: 'v9jEX5zd5kDdnW2O',
      is_folder: false,
      name: 'banner-homepage-2025',
      rename_physical_file: true,
    },
  ],
});
```

### Crop ảnh

```javascript
await api.post('/media/actions', {
  action: 'crop',
  imageId: 'v9jEX5zd5kDdnW2O',
  cropData: {
    x: 100,
    y: 50,
    width: 800,
    height: 600,
  },
});
```

### Toggle favorite

```javascript
// Thêm vào favorites
await api.post('/media/actions', {
  action: 'favorite',
  selected: [{ id: 'v9jEX5zd5kDdnW2O', is_folder: false }],
});

// Xóa khỏi favorites
await api.post('/media/actions', {
  action: 'remove_favorite',
  selected: [{ id: 'v9jEX5zd5kDdnW2O', is_folder: false }],
});
```

### Ghi nhận truy cập gần đây

```javascript
await api.post('/media/actions', {
  action: 'add_recent',
  item: { id: 'v9jEX5zd5kDdnW2O', is_folder: false },
});
```

---

## Lưu ý FE khi tích hợp

1. **Hashed ID**: Luôn dùng hashed ID nhận từ API `GET /v1/media/list`. Không tự tạo hoặc truyền raw integer ID.

2. **`is_folder` flag**: Phải truyền chính xác `true`/`false` để server xử lý đúng loại item. Mặc định là `false` (file) nếu không truyền.

3. **Batch operations**: Hầu hết actions hỗ trợ xử lý hàng loạt qua mảng `selected`. Tận dụng tính năng này thay vì gọi API nhiều lần.

4. **Sau khi thao tác**: Gọi lại `GET /v1/media/list` để refresh danh sách hiển thị (server không trả về danh sách mới trong response của action).

5. **Xác nhận trước khi xóa**: Với các action `delete`, `empty_trash`, và `trash` với `skip_trash=true`, nên hiển thị dialog xác nhận vì dữ liệu không thể khôi phục.

6. **Error handling**: Kiểm tra HTTP status code:
   - `200` — Thành công
   - `401` — Chưa đăng nhập / token hết hạn → redirect login
   - `403` — Không đủ quyền → hiển thị thông báo
   - `422` — Dữ liệu không hợp lệ → hiển thị lỗi validation

7. **`add_recent` khác biệt**: Dùng `item` (object đơn) thay vì `selected` (array). Nên gọi tự động khi user click xem file, không cần user thao tác thủ công.

8. **Folder operations đệ quy**: Khi `trash`, `restore`, `delete`, `make_copy` một folder, tất cả nội dung bên trong (files + sub-folders) đều bị ảnh hưởng.
