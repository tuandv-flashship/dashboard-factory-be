# FplatformData Container (v2.0.0)

Container lấy dữ liệu **tồn đầu/cuối ngày** và **hiệu suất theo giờ** từ database `fplatform` (external, read-only).

## Mô tả

Container cung cấp số liệu tồn kho, hiệu suất sản xuất, nhân viên và máy in theo giờ cho các team thuộc 2 dây chuyền DTF (FLS/PD) và DTG. Tất cả dữ liệu được query từ database `fplatform` thông qua connection riêng — không migration, không write.

> **Lưu ý:**
>
> - Database `fplatform` là **read-only** — chỉ SELECT, không INSERT/UPDATE/DELETE.
> - Connection config: `config/database.php` → `fplatform` (sử dụng `DB_*_FPLATFORM` env vars).
> - Có PDO timeout 10s để bảo vệ khi remote DB chậm/down.

## Database Tables (fplatform)

Tất cả table thuộc database `fplatform` (external, read-only). Dưới đây mô tả chi tiết các table và các cột chính được sử dụng.

### Bảng chung (dùng cho nhiều team)

| Table                      | Mô tả                                                                                                                                    | Các cột chính sử dụng                                                                                                                                     |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `printer_manage`           | Quản lý danh sách máy in theo xưởng. Dùng để lọc folder theo factory line.                                                               | `name` (tên máy, format "Machine MayXXX"), `factory` (FLS/PD)                                                                                             |
| `folder_manage`            | Quản lý folder sản xuất — đơn vị công việc chính. Mỗi folder gắn với 1 ngày estimate, 1 máy in, và chứa thông tin số lượng file/product. | `estimate_date`, `folder`, `folder_code`, `total_file`, `total_product`, `status_folder` (2=cancelled), `printer_share`, `printer_run`, `printer_default` |
| `order_check_file_dropbox` | Chi tiết file của đơn hàng trong Dropbox. **(v2.0.0)** Dùng bởi tất cả team để đếm file-level thay vì aggregate.                         | `folder`, `file_name_order_code`, `file_name_index_number`, `status` (2=cancelled). Collation: `utf8mb4_unicode_ci`                                       |
| `orders`                   | **(v2.0.0)** Thông tin đơn hàng. JOIN để lọc đơn HOLD/CANCELED.                                                                          | `order_code` (collation: `utf8mb3_general_ci`), `created`, `status`, `id` (DTG JOIN). Cần GRANT SELECT cho user `dashboard_data`.                         |
| `user`                     | Thông tin nhân viên. Dùng cho hourly staff queries.                                                                                      | `id`, `username`                                                                                                                                          |

### Bảng DTF — Team In / Cắt / Pick

| Table             | Mô tả                                                                                        | Các cột chính sử dụng                                                                                                                                                                    |
| ----------------- | -------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `user_group_scan` | Trạng thái xử lý folder theo nhóm công việc. Dùng để xác định folder đã hoàn thành hay chưa. | `folder_code`, `work_type` (0=In, 2=Cắt, 100=Pick), `work_status` (In: 1=done, Cắt: 0=received), `copy_job` (0=original), `user_id`, `total_file`, `total_product`, `total_product_part` |

### Bảng DTF — Team Mockup

| Table              | Mô tả                              | Các cột chính sử dụng                           |
| ------------------ | ---------------------------------- | ----------------------------------------------- |
| `log_check_mockup` | Log kiểm tra mockup đã hoàn thành. | `barcode`, `index_number`, `user_id`, `created` |

### Bảng DTF — Team Pack & Ship

| Table                | Mô tả                           | Các cột chính sử dụng                           |
| -------------------- | ------------------------------- | ----------------------------------------------- |
| `scan_label_history` | Lịch sử quét nhãn khi đóng gói. | `barcode`, `index_num`, `user_id`, `created_at` |

### Bảng DTG

| Table                 | Mô tả                 | Các cột chính sử dụng                                                                                                                     |
| --------------------- | --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `dtg_folder_detail`   | Chi tiết folder DTG.  | `folder_key`, `estimate_folder_date`, `done_at`, `done_by`, `done_user_name`                                                              |
| `dtg_item_detail`     | Chi tiết item in DTG. | `folder_key`, `order_code`, `index_num`, `distribute_id`, `estimate_folder_date`, `active`                                                |
| `dtg_printed_product` | Trạng thái in DTG.    | `order_code`, `index_num`, `distribute_id`, `print_status` (0/null=chưa in, 1=đã in), `printed_by` (Apollo/ATLAS_1/ATLAS_2), `product_id` |

## SQL Reference

SQL tham chiếu v2.0.0 nằm trong `sql/` directory (12 files) và `docs/sql_v2_update_240226/` (reference).

> **Ghi chú v2.0.0:**
>
> - Tất cả query JOIN `order_check_file_dropbox` để đếm file-level (thay vì `folder_manage.total_file` aggregate).
> - CTE `order_status` JOIN `orders` để lọc status `HOLD/REQUEST_CANCEL/REJECTED/REJECT_REQUESTED/CANCELED`.
> - COLLATE `utf8mb4_unicode_ci` trên `orders.order_code` JOIN (cross-charset: utf8mb3 ↔ utf8mb4).
> - Parameter `:estimate_date` (date) — cho tồn đầu/cuối ngày.
> - Parameter `:start_shift`, `:end_shift` (datetime, US/Central) — cho hourly metrics.

## Teams & Data Sources

### DTF Teams (yêu cầu `factory`: FLS hoặc PD)

| Team        | `team` param      | Dept code   | Data Source (v2.0.0)                                                                                                                  | Đơn vị  |
| ----------- | ----------------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| In          | `print`           | `print`     | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `user_group_scan` (work_type=0, work_status=1)                     | file    |
| Cắt         | `cut`             | `cut`       | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `user_group_scan` (work_type=2, work_status=0, copy_job=0)         | file    |
| Pick        | `pick`            | `pick`      | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `user_group_scan` (work_type=100, copy_job=0, created_at interval) | product |
| Mockup      | `mockup`          | `mockup`    | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `log_check_mockup`                                                 | file    |
| Pack & Ship | `pack_ship`       | `pack_ship` | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `scan_label_history`. PD: thêm DTG union từ `dtg_item_detail`      | shirt   |
| Tồn đơn     | `order_inventory` | —           | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `scan_label_history`. Đếm `COUNT(DISTINCT order_code)`             | order   |

### Hotshot Teams (yêu cầu `factory`: FLS hoặc PD)

| Team                | `team` param        | Data Source (v2.0.0)                                                                        | Đơn vị  | Filter                            |
| ------------------- | ------------------- | ------------------------------------------------------------------------------------------- | ------- | --------------------------------- |
| Hotshot In          | `hotshot_print`     | `folder_manage` → `order_check_file_dropbox` → `orders` → `user_group_scan` (work_type=0)   | file    | `printer_default = MayHOTSHOT/PD` |
| Hotshot Pick        | `hotshot_pick`      | `folder_manage` → `order_check_file_dropbox` → `orders` → `user_group_scan` (work_type=100) | product | `printer_default = MayHOTSHOT/PD` |
| Hotshot Cắt         | `hotshot_cut`       | `folder_manage` → `order_check_file_dropbox` → `orders` → `user_group_scan` (work_type=2)   | file    | `printer_default = MayHOTSHOT/PD` |
| Hotshot Mockup      | `hotshot_mockup`    | `folder_manage` → `order_check_file_dropbox` → `orders` → `log_check_mockup`                | file    | `printer_default = MayHOTSHOT/PD` |
| Hotshot Pack & Ship | `hotshot_pack_ship` | `folder_manage` → `order_check_file_dropbox` → `orders` → `scan_label_history`              | shirt   | `printer_default = MayHOTSHOT/PD` |
| Hotshot Đơn         | _(existing)_        | Dùng endpoint riêng `/hotshot-orders`                                                       | order   | `printer_default = MayHOTSHOT/PD` |

> **Hotshot teams** trả về `{ tong_viec, da_lam }` — khác với teams thường chỉ trả `{ tong_viec }`.

### DTG Teams (**không** cần `factory`)

| Team                   | `team` param      | Dept code   | Data Source                                | Đơn vị |
| ---------------------- | ----------------- | ----------- | ------------------------------------------ | ------ |
| Pick DTG               | `pick_dtg`        | `pick_dtg`  | `dtg_folder_detail` + `dtg_item_detail`    | shirt  |
| In DTG                 | `dtg_print`       | `dtg_print` | `dtg_item_detail` + `dtg_printed_product`  | file   |
| In DTG (Machine Split) | `dtg_print_split` | —           | Tương tự `dtg_print` + chia theo tỷ lệ máy | file   |

> **Machine Split Ratios (DTG Print):**
>
> - Apollo: 250 file/h → **62.5%**
> - ATLAS_1: 75 file/h → **18.75%**
> - ATLAS_2: 75 file/h → **18.75%**

## API Endpoints

### 1. GET `/v1/fplatform/daily-inventory` — Tồn đầu/cuối ngày (Public)

| Param  | Type   | Required | Values                                                                                                                                                                                              | Default |
| ------ | ------ | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| `team` | string | ✅       | `print`, `cut`, `pick`, `mockup`, `pack_ship`, `order_inventory`, `pick_dtg`, `dtg_print`, `dtg_print_split`, `hotshot_print`, `hotshot_pick`, `hotshot_cut`, `hotshot_mockup`, `hotshot_pack_ship` | —       |
| `date` | string | ❌       | `YYYY-MM-DD`                                                                                                                                                                                        | Hôm nay |

### 2. GET `/v1/admin/fplatform/inventory` — Tồn tất cả team (Private)

| Param  | Type   | Required | Values       | Default |
| ------ | ------ | -------- | ------------ | ------- |
| `date` | string | ❌       | `YYYY-MM-DD` | Hôm nay |

### 3. GET `/v1/admin/fplatform/hourly-metrics` — Hiệu suất theo giờ (Private)

| Param         | Type     | Required | Values                                                                      |
| ------------- | -------- | -------- | --------------------------------------------------------------------------- |
| `team`        | string   | ✅       | `print`, `cut`, `pick`, `mockup`, `pack_ship`, `dtg_print`, `pick_dtg`      |
| `metric`      | string   | ✅       | `productivity`, `staff_count`, `staff_productivity`, `machine_productivity` |
| `start_shift` | datetime | ✅       | `YYYY-MM-DD HH:mm:ss` (US/Central)                                          |
| `end_shift`   | datetime | ✅       | `YYYY-MM-DD HH:mm:ss` (US/Central), phải sau start_shift                    |

#### Team × Metric Compatibility

| Team      | productivity | staff_count | staff_productivity | machine_productivity |
| --------- | :----------: | :---------: | :----------------: | :------------------: |
| print     |      ✅      |     ✅      |         ❌         |          ✅          |
| cut       |      ✅      |     ✅      |         ✅         |          ❌          |
| pick      |      ✅      |     ✅      |         ✅         |          ❌          |
| mockup    |      ✅      |     ✅      |         ✅         |          ❌          |
| pack_ship |      ✅      |     ✅      |         ✅         |          ❌          |
| dtg_print |      ✅      |     ❌      |         ❌         |          ✅          |
| pick_dtg  |      ✅      |     ✅      |         ✅         |          ❌          |

#### Response Examples

```json
// productivity / staff_productivity / machine_productivity
{
  "data": {
    "team": "print",
    "metric": "productivity",
    "hours": [
      { "date_hour": "2026-04-14 08", "value": 125 },
      { "date_hour": "2026-04-14 09", "value": 340 }
    ]
  }
}

// staff_count
{
  "data": {
    "team": "pick",
    "metric": "staff_count",
    "hours": [
      { "date_hour": "2026-04-14 08", "num_staff": 5 },
      { "date_hour": "2026-04-14 09", "num_staff": 7 }
    ]
  }
}

// staff_productivity (grouped by username)
{
  "data": {
    "team": "cut",
    "metric": "staff_productivity",
    "hours": [
      { "date_hour": "2026-04-14 08", "username": "user1", "value": 25 },
      { "date_hour": "2026-04-14 08", "username": "user2", "value": 30 }
    ]
  }
}

// machine_productivity (grouped by machine/printed_by)
{
  "data": {
    "team": "print",
    "metric": "machine_productivity",
    "hours": [
      { "date_hour": "2026-04-14 08", "machine": "May1", "value": 50 },
      { "date_hour": "2026-04-14 08", "machine": "May2", "value": 75 }
    ]
  }
}
```

### 4. GET `/v1/admin/fplatform/hotshot-orders` — Đơn Hotshot (Private)

| Param  | Type   | Required | Values       | Default |
| ------ | ------ | -------- | ------------ | ------- |
| `date` | string | ❌       | `YYYY-MM-DD` | Hôm nay |

```json
{
    "data": {
        "estimate_date": "2026-04-14",
        "ton_dau": 15,
        "ton_cuoi": 3,
        "factory": "FLS"
    }
}
```

## Caching

| Endpoint            | Data Type    | TTL    | Strategy          |
| ------------------- | ------------ | ------ | ----------------- |
| daily-inventory     | Ngày quá khứ | 1 giờ  | `Cache::remember` |
| daily-inventory     | Ngày hôm nay | 5 phút | `Cache::remember` |
| all-teams inventory | Ngày quá khứ | 1 giờ  | `Cache::remember` |
| all-teams inventory | Ngày hôm nay | 5 phút | `Cache::remember` |
| per-team inventory  | Ngày quá khứ | 1 giờ  | `Cache::put`      |
| per-team inventory  | Ngày hôm nay | 5 phút | `Cache::put`      |
| hourly-metrics      | Tất cả       | 5 phút | `Cache::remember` |
| hotshot-orders      | Ngày quá khứ | 1 giờ  | `Cache::remember` |
| hotshot-orders      | Ngày hôm nay | 5 phút | `Cache::remember` |

> **Per-team cache key:** `fplatform:team-inventory:{team}:{date}` — written by `FetchTeamInventoryJob`,
> assembled into composite `fplatform:all-inventory:{date}` by `GetAllTeamsInventoryTask`.

## Optimizations

- **2-Stage Parallel Pipeline**: Stage 1 dispatches `FetchTeamInventoryJob × 11-13` via `Bus::batch()` → Horizon workers execute in parallel (~2-3s vs ~15s sequential). Stage 2 `then()` callback assembles cache → dispatches dept sync + order sync.
- **Per-Team Caching**: Each team's inventory cached individually by `FetchTeamInventoryJob`, enabling parallel writes and fallback assembly.
- **QueriesFplatform Trait**: DRY — printer CTE builder, safe query execution, result formatting, `formatHotshotResult`, `hotshotPrinterList`, multi-row support
- **COLLATE Handling**: Explicit `COLLATE utf8mb4_unicode_ci` trên `orders.order_code` JOINs (utf8mb3 ↔ utf8mb4 cross-charset)
- **orders.status Filter**: CTE `order_status` loại bỏ HOLD/CANCELED/REJECTED (~0.1% đơn)
- **Parameterized Tasks**: 5 hourly tasks handle 14+ query variants via `HourlyMetricType` enum (thay vì 18 files riêng lẻ)
- **Enum-driven validation**: `Team.supportsMetric()` rejects invalid team×metric combinations at request level
- **Error Handling**: `try/catch` + `Log::warning` — remote DB failure trả về empty/404 thay vì 500
- **PDO Timeout**: 10s connection timeout cho remote RDS slave
- **Cache::remember**: Atomic single-call caching (not has+get pattern)
- **Horizon**: 15 workers, `auto` balance strategy, `sync` queue for parallel workloads

## File Structure

```
FplatformData/
├── Enums/
│   ├── FactoryLine.php          # FLS | PD + extraPrinters()
│   ├── HourlyMetricType.php     # productivity | staff_count | staff_productivity | machine_productivity
│   ├── WorkType.php             # In(0) | Cat(2) | Pick(100) + doneStatus()
│   └── Team.php                 # 14 team types + requiresFactory() + supportsMetric()
├── Models/
│   ├── FolderManage.php         # folder_manage
│   ├── UserGroupScan.php        # user_group_scan
│   └── PrinterManage.php        # printer_manage
├── Traits/
│   └── QueriesFplatform.php     # Shared: printer builder, queryFplatform/All, formatResult/OrderResult/HotshotResult, hotshotPrinterList
├── Jobs/
│   └── FetchTeamInventoryJob.php       # 1 job = 1 team query + per-team cache
├── Tasks/
│   │ # --- Inventory (tồn đầu/cuối) ---
│   ├── GetDailyInventoryTask.php        # Team IN/CẮT (DTF)
│   ├── GetPickInventoryTask.php         # Team Pick (DTF)
│   ├── GetMockupInventoryTask.php       # Team Mockup (DTF)
│   ├── GetPackShipInventoryTask.php     # Team Pack & Ship (DTF, PD+DTG)
│   ├── GetOrderInventoryTask.php        # Tồn đơn hàng (DTF)
│   ├── GetDtgOrderInventoryTask.php     # Tồn đơn (DTG)
│   ├── GetDtgPickInventoryTask.php      # Team Pick (DTG)
│   ├── GetDtgPrintInventoryTask.php     # Team IN (DTG)
│   ├── GetDtgPrintMachineSplitTask.php  # Team IN (DTG) split
│   ├── GetHotshotOrderInventoryTask.php # Đơn hotshot
│   ├── GetHotshotInventoryTask.php    # Hotshot In/Pick/Cắt (via WorkType)
│   ├── GetHotshotMockupInventoryTask.php # Hotshot Mockup
│   ├── GetHotshotPackShipInventoryTask.php # Hotshot Pack & Ship
│   ├── GetAllTeamsInventoryTask.php     # Tồn tất cả team
│   │ # --- Hourly metrics ---
│   ├── GetHourlyUgsMetricsTask.php      # IN/CẮT/PICK hourly (4 metric types × 3 teams)
│   ├── GetHourlyMockupMetricsTask.php   # Mockup hourly (3 metric types)
│   ├── GetHourlyPackShipMetricsTask.php # Pack&Ship hourly (3 types, FLS/PD strategy)
│   ├── GetHourlyDtgPrintMetricsTask.php # DTG Print hourly (2 metric types)
│   └── GetHourlyDtgPickMetricsTask.php  # DTG Pick hourly (3 metric types)
├── Actions/
│   ├── GetDailyInventoryAction.php      # Dispatch tồn by Team enum
│   └── GetHourlyMetricsAction.php       # Dispatch hourly by Team + Metric
└── UI/API/
    ├── Controllers/
    │   ├── GetDailyInventoryController.php     # Public
    │   ├── GetAllTeamsInventoryController.php   # Private
    │   ├── GetHourlyMetricsController.php      # Private
    │   └── GetHotshotOrdersController.php      # Private
    ├── Requests/
    │   ├── GetDailyInventoryRequest.php
    │   ├── GetAllTeamsInventoryRequest.php
    │   ├── GetHourlyMetricsRequest.php
    │   └── GetHotshotOrdersRequest.php
    └── Routes/
        ├── GetDailyInventory.v1.public.php
        ├── GetAllTeamsInventory.v1.private.php
        ├── GetHourlyMetrics.v1.private.php
        └── GetHotshotOrders.v1.private.php
```

### SQL Reference Files

```
# v2.0.0 reference (authoritative)
sql/
├── 01_tong_viec_team_in.sql            # Team In (FLS + PD + DTG)
├── 02_tong_viec_team_pick.sql          # Team Pick
├── 03_tong_viec_team_cat.sql           # Team Cắt
├── 04_tong_viec_team_mockup.sql        # Team Mockup
├── 05_tong_viec_team_pack_ship.sql     # Team Pack & Ship
├── 06_tong_don_theo_don.sql            # Tồn đơn hàng
├── 16_so_don_hotshot.sql               # Hotshot order count
├── 22_hotshot_file_team_in.sql         # Hotshot In
├── 23_hotshot_ao_team_pick.sql         # Hotshot Pick
├── 24_hotshot_file_team_cat.sql        # Hotshot Cắt
├── 25_hotshot_file_team_mockup.sql     # Hotshot Mockup
└── 26_hotshot_ao_pack_ship.sql         # Hotshot Pack & Ship

# v2.0.0 update spec
docs/sql_v2_update_240226/
├── 01-06, 16, 22-26 .sql               # Bản gốc SQL update spec

# Legacy (deprecated)
docs/
├── rpt_factory_ops_metrics_v8_1.sql   # [DEPRECATED] v1.1.0 - 24 nhóm query
├── ton_dau_ton_cuoi_*.sql             # [DEPRECATED] Legacy refs
└── ton_dau_ngay_update.sql            # [DEPRECATED]
```

## Changelog

### v2.0.0 (2026-04-24)

- **File-level granularity**: Thay `folder_manage.total_file` aggregate bằng `folder_manage JOIN order_check_file_dropbox` file-level counting cho tất cả team.
- **Order status filter**: CTE `order_status` JOIN `orders` table, loại bỏ đơn `HOLD/REQUEST_CANCEL/REJECTED/REJECT_REQUESTED/CANCELED` (~0.1% đơn).
- **DTG orders JOIN**: DTG queries JOIN `orders` bằng `o.id = d.order_id` thay vì `order_code`.
- **Team-specific updates**: In `work_status=1`, Pick thêm `copy_job=0` + `created_at interval`, Cắt thêm `copy_job=0`.
- **COLLATE fix**: Thêm `COLLATE utf8mb4_unicode_ci` trên tất cả `orders.order_code` JOINs (utf8mb3 ↔ utf8mb4).
- **Code dedup**: Di chuyển `formatHotshotResult()` và `hotshotPrinterList()` vào `QueriesFplatform` trait.
- **DB permission**: Cần GRANT SELECT trên `fplatform.orders` cho user `dashboard_data`.
