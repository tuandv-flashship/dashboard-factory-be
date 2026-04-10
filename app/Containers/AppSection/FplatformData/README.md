# FplatformData Container

Container lấy dữ liệu **tồn đầu/cuối ngày** từ database `fplatform` (external, read-only).

## Mô tả

Container cung cấp số liệu tồn kho theo ngày cho 8 team thuộc 2 dây chuyền DTF (FLS/PD) và DTG. Tất cả dữ liệu được query từ database `fplatform` thông qua connection riêng — không migration, không write.

> **Lưu ý:**
> - Database `fplatform` là **read-only** — chỉ SELECT, không INSERT/UPDATE/DELETE.
> - Connection config: `config/database.php` → `fplatform` (sử dụng `DB_*_FPLATFORM` env vars).
> - Có PDO timeout 10s để bảo vệ khi remote DB chậm/down.

## Database Tables (fplatform)

Tất cả table thuộc database `fplatform` (external, read-only). Dưới đây mô tả chi tiết các table và các cột chính được sử dụng.

### Bảng chung (dùng cho nhiều team)

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `printer_manage` | Quản lý danh sách máy in theo xưởng. Dùng để lọc folder theo factory line. | `name` (tên máy, format "Machine MayXXX"), `factory` (FLS/PD) |
| `folder_manage` | Quản lý folder sản xuất — đơn vị công việc chính. Mỗi folder gắn với 1 ngày estimate, 1 máy in, và chứa thông tin số lượng file/product. | `estimate_date`, `folder`, `folder_code`, `total_file`, `total_product`, `status_folder` (2=cancelled), `printer_share`, `printer_run`, `printer_default` |

### Bảng DTF — Team In / Cắt / Pick

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `user_group_scan` | Trạng thái xử lý folder theo nhóm công việc. Dùng để xác định folder đã hoàn thành hay chưa. | `folder_code`, `work_type` (0=In, 2=Cắt, 100=Pick), `work_status` (1=done), `copy_job` (0=original) |

### Bảng DTF — Team Mockup

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `order_check_file_dropbox` | Chi tiết file của đơn hàng trong Dropbox. Mỗi file gắn với 1 folder và có mã đơn hàng + index. | `folder`, `file_name_order_code`, `file_name_index_number`, `status` (2=cancelled) |
| `log_check_mockup` | Log kiểm tra mockup đã hoàn thành. Join với `order_check_file_dropbox` để xác định file nào đã check xong. | `barcode` (= file_name_order_code), `index_number` (= file_name_index_number), `created` (timestamp hoàn thành) |

### Bảng DTF — Team Pack & Ship

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `order_check_file_dropbox` | _(giống Mockup)_ Chi tiết file của đơn hàng trong Dropbox. | `folder`, `file_name_order_code`, `file_name_index_number`, `status` |
| `scan_label_history` | Lịch sử quét nhãn (scan label) khi đóng gói. Join với `order_check_file_dropbox` để xác định shirt nào đã scan xong. | `barcode` (= file_name_order_code), `index_num` (= file_name_index_number), `created_at` (timestamp quét) |

### Bảng DTG

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `dtg_folder_detail` | Chi tiết folder DTG. Mỗi record là 1 folder item. | `folder_key`, `estimate_folder_date`, `done_at` (null = chưa pick) |
| `dtg_item_detail` | Chi tiết item in DTG. Mỗi record là 1 file cần in. | `folder_key`, `order_code`, `index_num`, `distribute_id`, `estimate_folder_date`, `active` (1=active) |
| `dtg_printed_product` | Trạng thái in DTG. Join với `dtg_item_detail` để xác định file nào đã in. | `order_code`, `index_num`, `distribute_id`, `print_status` (0/null=chưa in, 1=đã in) |

## SQL Reference

Các file SQL tham chiếu nằm trong thư mục `docs/`. Mỗi file chứa câu query cho từng factory line (**DTF1 - FLS** và **DTF2 - PD**). Trong code PHP, factory được truyền qua parameter binding — 1 query dùng chung cho cả FLS và PD.

> **Ghi chú:**
> - File `ton_dau_ngay_update.sql` là phiên bản cũ (deprecated), đã được thay thế bởi 4 file riêng biệt dưới đây.
> - Parameter `:estimate_date` (date) — ngày estimate cần truy vấn.

---

### 1. Team In / Cắt (DTF) + In (DTG) — `ton_dau_ton_cuoi_in.sql`

**Tables:** `folder_manage`, `user_group_scan`, `printer_manage`, `dtg_item_detail`, `dtg_printed_product`

#### 1.1. Team In / Cắt — DTF (FLS / PD)

Join `folder_manage` ↔ `user_group_scan` theo `work_type` (0=In, 2=Cắt). Đếm theo `total_file`.

```sql
-- work_type = 0 (In) hoặc work_type = 2 (Cắt)
-- Thay factory = 'FLS' → 'PD' và MayHOTSHOT/MayREPRINT → MayHOTSHOTPD/MayREPRINTPD cho DTF2
WITH daily_stats AS (
    SELECT
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS not_done,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code AND s.work_type = 0 AND s.work_status = 1
    WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(NAME, 'Machine ', 'May') FROM fplatform.printer_manage WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
    GROUP BY f.estimate_date
)
SELECT estimate_date, ton_dau, ton_cuoi
FROM (
    SELECT
        estimate_date,
        total_file + COALESCE(SUM(not_done) OVER (
            ORDER BY estimate_date
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS ton_dau,
        SUM(not_done) OVER (ORDER BY estimate_date) AS ton_cuoi
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';
```

#### 1.2. Team In — DTG (tổng hợp)

Join `dtg_item_detail` ↔ `dtg_printed_product`. Không cần factory. Đếm file chưa in (`print_status = 0` hoặc `NULL`).

```sql
WITH daily_aggregated AS (
    SELECT
        d.estimate_folder_date,
        COUNT(*) AS total_file,
        SUM(IF(p.print_status = 0 OR p.print_status IS NULL, 1, 0)) AS unprint_file
    FROM fplatform.dtg_item_detail d
    LEFT JOIN fplatform.dtg_printed_product p
        ON d.order_code = p.order_code
        AND d.index_num = p.index_num
        AND d.distribute_id = p.distribute_id
    WHERE d.estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND d.active = 1
    GROUP BY d.estimate_folder_date
)
SELECT estimate_folder_date, ton_dau, ton_cuoi
FROM (
    SELECT
        estimate_folder_date,
        total_file + COALESCE(SUM(unprint_file) OVER (
            ORDER BY estimate_folder_date
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS ton_dau,
        SUM(unprint_file) OVER (ORDER BY estimate_folder_date) AS ton_cuoi
    FROM daily_aggregated
) c
WHERE estimate_folder_date = ':estimate_date';
```

#### 1.3. Team In — DTG Machine Split

Tương tự 1.2, chia theo tỷ lệ năng suất máy: Apollo 62.5%, ATLAS_1 18.75%, ATLAS_2 18.75%.

```sql
WITH daily_aggregated AS (
    SELECT
        d.estimate_folder_date,
        COUNT(*) AS total_file,
        SUM(IF(p.print_status = 0 OR p.print_status IS NULL, 1, 0)) AS unprint_file
    FROM fplatform.dtg_item_detail d
    LEFT JOIN fplatform.dtg_printed_product p
        ON d.order_code = p.order_code
        AND d.index_num = p.index_num
        AND d.distribute_id = p.distribute_id
    WHERE d.estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND d.active = 1
    GROUP BY d.estimate_folder_date
),
base_data AS (
    SELECT
        estimate_folder_date,
        total_file + COALESCE(SUM(unprint_file) OVER (
            ORDER BY estimate_folder_date
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS ton_dau,
        SUM(unprint_file) OVER (ORDER BY estimate_folder_date) AS ton_cuoi
    FROM daily_aggregated
),
split_logic AS (
    SELECT
        estimate_folder_date,
        ROUND(ton_dau * 0.625) AS td_apollo,
        ROUND(ton_cuoi * 0.625) AS tc_apollo,
        ROUND(ton_dau * 0.1875) AS td_atlas1,
        ROUND(ton_cuoi * 0.1875) AS tc_atlas1,
        ton_dau - ROUND(ton_dau * 0.625) - ROUND(ton_dau * 0.1875) AS td_atlas2,
        ton_cuoi - ROUND(ton_cuoi * 0.625) - ROUND(ton_cuoi * 0.1875) AS tc_atlas2
    FROM base_data
    WHERE estimate_folder_date = ':estimate_date'
)
SELECT
    estimate_folder_date AS estimate_date,
    td_apollo AS ton_dau_apollo,
    td_atlas1 AS ton_dau_atlas1,
    td_atlas2 AS ton_dau_atlas2,
    tc_apollo AS ton_cuoi_apollo,
    tc_atlas1 AS ton_cuoi_atlas1,
    tc_atlas2 AS ton_cuoi_atlas2
FROM split_logic;
```

---

### 2. Team Pick (DTF) + Pick (DTG) — `ton_dau_ton_cuoi_pick.sql`

**Tables:** `folder_manage`, `user_group_scan`, `printer_manage`, `dtg_folder_detail`, `dtg_item_detail`

#### 2.1. Team Pick — DTF (FLS / PD)

Join `folder_manage` ↔ `user_group_scan` với `work_type=100, copy_job=0`. Đếm theo `total_product`.

```sql
-- Thay factory = 'FLS' → 'PD' và MayHOTSHOT/MayREPRINT → MayHOTSHOTPD/MayREPRINTPD cho DTF2
WITH daily_stats AS (
    SELECT
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_product, 0)) AS chua_pick,
        SUM(f.total_product) AS total_product
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code AND s.work_type = 100 AND s.work_status = 1 AND copy_job = 0
    WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(NAME, 'Machine ', 'May') FROM fplatform.printer_manage WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
    GROUP BY f.estimate_date
)
SELECT estimate_date, ton_dau, ton_cuoi
FROM (
    SELECT
        estimate_date,
        total_product + COALESCE(SUM(chua_pick) OVER (
            ORDER BY estimate_date
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS ton_dau,
        SUM(chua_pick) OVER (ORDER BY estimate_date) AS ton_cuoi
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';
```

#### 2.2. Team Pick — DTG

Join `dtg_folder_detail` ↔ `dtg_item_detail`. Kiểm tra `done_at` IS NULL = chưa pick.

```sql
WITH daily_summary AS (
    SELECT
        f.estimate_folder_date,
        COUNT(d.folder_key) AS total_shirt,
        SUM(IF(f.done_at IS NULL, 1, 0)) AS chua_pick
    FROM fplatform.dtg_folder_detail f
    INNER JOIN fplatform.dtg_item_detail d ON d.folder_key = f.folder_key
    WHERE f.estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
    GROUP BY f.estimate_folder_date
)
SELECT estimate_folder_date AS estimate_date, ton_dau, ton_cuoi
FROM (
    SELECT
        estimate_folder_date,
        total_shirt + COALESCE(SUM(chua_pick) OVER (
            ORDER BY estimate_folder_date
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS ton_dau,
        SUM(chua_pick) OVER (ORDER BY estimate_folder_date) AS ton_cuoi
    FROM daily_summary
) result
WHERE estimate_folder_date = ':estimate_date';
```

---

### 3. Team Mockup (DTF) — `ton_dau_ton_cuoi_mockup.sql`

**Tables:** `printer_manage`, `folder_manage`, `order_check_file_dropbox`, `log_check_mockup`

CTE `target_printers` → `folder_printer` join printer trước → join `order_check_file_dropbox` → LEFT JOIN `log_check_mockup` theo `barcode` + `index_number` với filter 15 ngày. GROUP BY để dedup thay vì ROW_NUMBER.

```sql
-- Thay factory = 'FLS' → 'PD' và MayHOTSHOT/MayREPRINT → MayHOTSHOTPD/MayREPRINTPD cho DTF2
WITH
target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id
    FROM fplatform.printer_manage
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
folder_printer AS (
    SELECT fm.estimate_date, fm.folder
    FROM fplatform.folder_manage fm
    JOIN target_printers p ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
    AND fm.status_folder <> 2
),
a AS (
    SELECT
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        d.file_name_index_number,
        count(*) as num_file
    FROM folder_printer f
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
      AND d.status <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, file_name_index_number
)
, daily_aggregated AS (
    SELECT
        estimate_date,
        SUM(IF(created IS NULL, num_file, 0)) AS not_done,
        SUM(num_file) AS total_file
    FROM (
        SELECT a.*, l.created
        FROM a
        LEFT JOIN fplatform.log_check_mockup l
            ON a.file_name_order_code COLLATE utf8mb4_0900_ai_ci = l.barcode
                AND created >= ':estimate_date' - INTERVAL 15 DAY
                AND a.file_name_index_number = l.index_number
        GROUP BY 1,2,3,4
    ) b
    GROUP BY estimate_date
)
SELECT * FROM (
    SELECT
        estimate_date,
        total_file + COALESCE(SUM(not_done) OVER (
            ORDER BY estimate_date
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS ton_dau,
        SUM(not_done) OVER (ORDER BY estimate_date) AS ton_cuoi
    FROM daily_aggregated
) final_result
WHERE estimate_date = ':estimate_date';
```

---

### 4. Team Pack & Ship (DTF) — `ton_dau_ton_cuoi_pack_ship.sql`

**Tables:** `printer_manage`, `folder_manage`, `order_check_file_dropbox`, `scan_label_history`

CTE `target_printers` → `folder_printer` join printer trước → double sub-select đếm `num_shirt` → LEFT JOIN `scan_label_history` theo `barcode` + `index_num` với filter 15 ngày. GROUP BY để dedup.

```sql
-- Thay factory = 'FLS' → 'PD' và MayHOTSHOT/MayREPRINT → MayHOTSHOTPD/MayREPRINTPD cho DTF2
WITH
target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id
    FROM fplatform.printer_manage
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
folder_printer AS (
    SELECT fm.estimate_date, fm.folder
    FROM fplatform.folder_manage fm
    JOIN target_printers p
        ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
        AND fm.status_folder <> 2
)
, a AS (
SELECT *, COUNT(*) AS num_shirt FROM (
    SELECT
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        file_name_index_number
    FROM fplatform.order_check_file_dropbox d
    JOIN folder_printer f
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
    AND d.status <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, file_name_index_number
    ) c
GROUP BY estimate_date, folder, file_name_order_code, file_name_index_number )
, daily_aggregated AS (
    SELECT
        estimate_date,
        SUM(IF(created_at IS NULL, num_shirt, 0)) AS not_done,
        SUM(num_shirt) AS total_shirt
    FROM (
        SELECT a.*, l.created_at
        FROM a
        LEFT JOIN fplatform.scan_label_history l ON a.file_name_order_code COLLATE utf8mb4_0900_ai_ci = l.barcode
        AND l.created_at >= ':estimate_date'  - INTERVAL 15 DAY
        AND a.file_name_index_number = l.index_num
        GROUP BY 1,2,3,4
    ) b
    GROUP BY estimate_date
)
SELECT * FROM (
    SELECT
        estimate_date,
        total_shirt + COALESCE(SUM(not_done) OVER (
            ORDER BY estimate_date
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS ton_dau,
        SUM(not_done) OVER (ORDER BY estimate_date) AS ton_cuoi
    FROM daily_aggregated
) final_result
WHERE estimate_date = ':estimate_date';
```

## Teams & Data Sources

### DTF Teams (yêu cầu `factory`: FLS hoặc PD)

| Team | `team` param | Data Source | SQL Reference | Đơn vị |
|------|-------------|-------------|---------------|--------|
| In | `in` | `folder_manage` + `user_group_scan` (work_type=0) | `ton_dau_ton_cuoi_in.sql` | file |
| Cắt | `cat` | `folder_manage` + `user_group_scan` (work_type=2) | `ton_dau_ton_cuoi_in.sql` | file |
| Pick | `pick` | `folder_manage` + `user_group_scan` (work_type=100, copy_job=0) | `ton_dau_ton_cuoi_pick.sql` | product |
| Mockup | `mockup` | `folder_manage` + `order_check_file_dropbox` + `log_check_mockup` | `ton_dau_ton_cuoi_mockup.sql` | file |
| Pack & Ship | `pack_ship` | `folder_manage` + `order_check_file_dropbox` + `scan_label_history` | `ton_dau_ton_cuoi_pack_ship.sql` | shirt |

### DTG Teams (**không** cần `factory`)

| Team | `team` param | Data Source | SQL Reference | Đơn vị |
|------|-------------|-------------|---------------|--------|
| Pick DTG | `dtg_pick` | `dtg_folder_detail` + `dtg_item_detail` | `ton_dau_ton_cuoi_pick.sql` | shirt |
| In DTG | `dtg_print` | `dtg_item_detail` + `dtg_printed_product` | `ton_dau_ton_cuoi_in.sql` | file |
| In DTG (Machine Split) | `dtg_print_split` | Tương tự `dtg_print` + chia theo tỷ lệ máy | `ton_dau_ton_cuoi_in.sql` | file |

> **Machine Split Ratios (DTG Print):**
> - Apollo: 250 file/h → **62.5%**
> - ATLAS_1: 75 file/h → **18.75%**
> - ATLAS_2: 75 file/h → **18.75%**

## Thuật ngữ

| Thuật ngữ | Giải thích |
|-----------|-----------|
| `estimate_date` | Ngày dự kiến sản xuất (từ hệ thống lên lịch) |
| `ton_dau` | **Tồn đầu ngày** — Tổng file/product cần xử lý vào đầu ngày (tích lũy từ các ngày trước chưa xong + tổng trong ngày) |
| `ton_cuoi` | **Tồn cuối ngày** — Tổng file/product chưa xử lý xong tính đến cuối ngày |
| `factory` | Xưởng sản xuất: `FLS` (FlashShip) hoặc `PD` (PrintDash) |

## API Endpoint

| Method | Endpoint | Auth | Mô tả |
|--------|----------|------|-------|
| GET | `/v1/fplatform/daily-inventory` | Public ✅ | Tồn đầu/cuối ngày theo team |

### Parameters

| Param | Type | Required | Values | Default |
|-------|------|----------|--------|---------|
| `team` | string | ✅ | `in`, `cat`, `pick`, `mockup`, `pack_ship`, `dtg_pick`, `dtg_print`, `dtg_print_split` | — |
| `factory` | string | ✅ cho DTF | `FLS`, `PD` | — |
| `date` | string | ❌ | `YYYY-MM-DD` | Hôm nay |

> **Validation:**
> - `factory` bắt buộc cho teams DTF (`in`, `cat`, `pick`, `mockup`, `pack_ship`)
> - `factory` không cần cho teams DTG (`dtg_pick`, `dtg_print`, `dtg_print_split`)
> - `date` phải đúng format `Y-m-d`, mặc định = ngày hiện tại

### Example Requests

```bash
# Team In, xưởng FLS, hôm nay
GET /v1/fplatform/daily-inventory?team=in&factory=FLS

# Team Cắt, xưởng PD, ngày cụ thể
GET /v1/fplatform/daily-inventory?team=cat&factory=PD&date=2026-04-01

# Team Pick DTG (không cần factory)
GET /v1/fplatform/daily-inventory?team=dtg_pick&date=2026-04-03

# Team In DTG — Machine Split
GET /v1/fplatform/daily-inventory?team=dtg_print_split&date=2026-04-03
```

### Response — Standard (tất cả teams trừ `dtg_print_split`)

```json
{
  "data": {
    "estimate_date": "2026-04-03",
    "ton_dau": 1250,
    "ton_cuoi": 340,
    "team": "in",
    "factory": "FLS"
  }
}
```

### Response — Machine Split (`dtg_print_split`)

```json
{
  "data": {
    "estimate_date": "2026-04-03",
    "machines": {
      "apollo": {
        "ratio": "62.5%",
        "ton_dau": 781,
        "ton_cuoi": 212
      },
      "atlas_1": {
        "ratio": "18.75%",
        "ton_dau": 234,
        "ton_cuoi": 63
      },
      "atlas_2": {
        "ratio": "18.75%",
        "ton_dau": 235,
        "ton_cuoi": 65
      }
    },
    "team": "dtg_print_split",
    "factory": null
  }
}
```

### Response — Không có dữ liệu

```json
{
  "message": "Không có dữ liệu tồn cho ngày này."
}
```
HTTP Status: `404`

### Error — Validation

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "team": ["Vui lòng chọn team (in, cat, pick, mockup, pack_ship, dtg_pick, dtg_print, dtg_print_split)."],
    "factory": ["Vui lòng chọn factory (FLS hoặc PD) cho team DTF."]
  }
}
```
HTTP Status: `422`

## Caching

| Loại dữ liệu | TTL | Lý do |
|---------------|-----|-------|
| Ngày trong quá khứ | **1 giờ** | Dữ liệu đã cố định, không thay đổi |
| Ngày hôm nay | **5 phút** | Dữ liệu đang cập nhật liên tục |

Cache key format: `fplatform:inventory:{team}:{factory}:{date}`

## FE Integration

```typescript
// hooks/useFplatformInventory.ts
import useSWR from "swr";

interface InventoryData {
  estimate_date: string;
  ton_dau: number;
  ton_cuoi: number;
  team: string;
  factory: string | null;
}

interface MachineSplitData {
  estimate_date: string;
  machines: Record<string, { ratio: string; ton_dau: number; ton_cuoi: number }>;
  team: "dtg_print_split";
  factory: null;
}

export function useInventory(team: string, factory?: string, date?: string) {
  const params = new URLSearchParams({ team });
  if (factory) params.set("factory", factory);
  if (date) params.set("date", date);

  const isToday = !date || date === new Date().toISOString().slice(0, 10);

  return useSWR<{ data: InventoryData | MachineSplitData }>(
    `/v1/fplatform/daily-inventory?${params}`,
    { refreshInterval: isToday ? 5 * 60 * 1000 : 0 }  // 5 min refresh for today
  );
}

// Usage
const { data: dtfIn } = useInventory("in", "FLS");
const { data: dtgPrint } = useInventory("dtg_print_split");
```

## Optimizations

- **QueriesFplatform Trait**: DRY — printer CTE builder, safe query execution, result formatting
- **Error Handling**: `try/catch` + `Log::warning` — remote DB failure trả về 404 thay vì 500
- **PDO Timeout**: 10s connection timeout cho remote RDS slave
- **Tiered Caching**: 1h cho lịch sử, 5 phút cho hôm nay

## Cross-Container Dependencies

| Direction | Container | Via |
|-----------|-----------|-----|
| ← used by | Production | Có thể inject `GetDailyInventoryTask` để hiển thị tồn trên dashboard |

## File Structure

```
FplatformData/
├── Enums/
│   ├── FactoryLine.php       # FLS | PD + extraPrinters()
│   ├── WorkType.php          # In(0) | Cat(2) | Pick(100)
│   └── Team.php              # 8 team types + requiresFactory()
├── Models/                   # Read-only, connection = 'fplatform'
│   ├── FolderManage.php      # folder_manage
│   ├── UserGroupScan.php     # user_group_scan
│   └── PrinterManage.php     # printer_manage
├── Traits/
│   └── QueriesFplatform.php  # Shared: printer builder, safe query, formatResult
├── Tasks/
│   ├── GetDailyInventoryTask.php      # Team IN/CẮT (DTF) — ref: ton_dau_ton_cuoi_in.sql
│   ├── GetPickInventoryTask.php       # Team Pick (DTF) — ref: ton_dau_ton_cuoi_pick.sql
│   ├── GetMockupInventoryTask.php     # Team Mockup (DTF) — ref: ton_dau_ton_cuoi_mockup.sql
│   ├── GetPackShipInventoryTask.php   # Team Pack & Ship (DTF) — ref: ton_dau_ton_cuoi_pack_ship.sql
│   ├── GetDtgPickInventoryTask.php    # Team Pick (DTG) — ref: ton_dau_ton_cuoi_pick.sql
│   ├── GetDtgPrintInventoryTask.php   # Team IN (DTG) — ref: ton_dau_ton_cuoi_in.sql
│   └── GetDtgPrintMachineSplitTask.php # Team IN (DTG) split — ref: ton_dau_ton_cuoi_in.sql
├── Actions/
│   └── GetDailyInventoryAction.php    # Dispatch by Team enum
└── UI/API/
    ├── Controllers/
    │   └── GetDailyInventoryController.php  # Invokable + tiered cache
    ├── Requests/
    │   └── GetDailyInventoryRequest.php     # Conditional factory validation
    └── Routes/
        └── GetDailyInventory.v1.public.php  # GET /v1/fplatform/daily-inventory
```

### SQL Reference Files (docs/)

```
docs/
├── ton_dau_ton_cuoi_in.sql         # Team In/Cắt (DTF) + In (DTG) + Machine Split
├── ton_dau_ton_cuoi_pick.sql       # Team Pick (DTF) + Pick (DTG)
├── ton_dau_ton_cuoi_mockup.sql     # Team Mockup (DTF)
├── ton_dau_ton_cuoi_pack_ship.sql  # Team Pack & Ship (DTF)
└── ton_dau_ngay_update.sql         # [DEPRECATED] Phiên bản cũ, đã thay bằng 4 file trên
```
