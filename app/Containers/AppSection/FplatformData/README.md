# FplatformData Container

Container lấy dữ liệu **tồn đầu/cuối ngày** và **hiệu suất theo giờ** từ database `fplatform` (external, read-only).

## Mô tả

Container cung cấp số liệu tồn kho, hiệu suất sản xuất, nhân viên và máy in theo giờ cho các team thuộc 2 dây chuyền DTF (FLS/PD) và DTG. Tất cả dữ liệu được query từ database `fplatform` thông qua connection riêng — không migration, không write.

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
| `user` | Thông tin nhân viên. Dùng cho hourly staff queries. | `id`, `username` |

### Bảng DTF — Team In / Cắt / Pick

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `user_group_scan` | Trạng thái xử lý folder theo nhóm công việc. Dùng để xác định folder đã hoàn thành hay chưa. | `folder_code`, `work_type` (0=In, 2=Cắt, 100=Pick), `work_status` (In: 1=done, Cắt: 0=received), `copy_job` (0=original), `user_id`, `total_file`, `total_product`, `total_product_part` |

### Bảng DTF — Team Mockup

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `order_check_file_dropbox` | Chi tiết file của đơn hàng trong Dropbox. | `folder`, `file_name_order_code`, `file_name_index_number`, `file_name_side`, `status` (2=cancelled), `folder_date` |
| `log_check_mockup` | Log kiểm tra mockup đã hoàn thành. | `barcode`, `index_number`, `user_id`, `created` |

### Bảng DTF — Team Pack & Ship

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `order_check_file_dropbox` | _(giống Mockup)_ Chi tiết file của đơn hàng trong Dropbox. | `folder`, `file_name_order_code`, `file_name_index_number`, `status` |
| `scan_label_history` | Lịch sử quét nhãn khi đóng gói. | `barcode`, `index_num`, `user_id`, `created_at` |

### Bảng DTG

| Table | Mô tả | Các cột chính sử dụng |
|-------|--------|----------------------|
| `dtg_folder_detail` | Chi tiết folder DTG. | `folder_key`, `estimate_folder_date`, `done_at`, `done_by`, `done_user_name` |
| `dtg_item_detail` | Chi tiết item in DTG. | `folder_key`, `order_code`, `index_num`, `distribute_id`, `estimate_folder_date`, `active` |
| `dtg_printed_product` | Trạng thái in DTG. | `order_code`, `index_num`, `distribute_id`, `print_status` (0/null=chưa in, 1=đã in), `printed_by` (Apollo/ATLAS_1/ATLAS_2), `product_id` |

## SQL Reference

Tất cả SQL tham chiếu nằm trong file `docs/rpt_factory_ops_metrics_v4.sql` (1813 dòng, 21 nhóm query).

> **Ghi chú:**
> - Các file `ton_dau_ton_cuoi_*.sql` cũ vẫn giữ lại để tham chiếu, nhưng v4 là nguồn chính thức.
> - Parameter `:estimate_date` (date) — cho tồn đầu/cuối ngày.
> - Parameter `:start_shift`, `:end_shift` (datetime, US/Central) — cho hourly metrics.

## Teams & Data Sources

### DTF Teams (yêu cầu `factory`: FLS hoặc PD)

| Team | `team` param | Dept code | Data Source | Đơn vị |
|------|-------------|-----------|-------------|--------|
| In | `print` | `print` | `folder_manage` + `user_group_scan` (work_type=0, work_status=1) | file |
| Cắt | `cut` | `cut` | `folder_manage` + `user_group_scan` (work_type=2, work_status=0) | file |
| Pick | `pick` | `pick` | `folder_manage` + `user_group_scan` (work_type=100, copy_job=0) | product |
| Mockup | `mockup` | `mockup` | `folder_manage` + `order_check_file_dropbox` + `log_check_mockup` | file |
| Pack & Ship | `pack_ship` | `pack_ship` | `folder_manage` + `order_check_file_dropbox` + `scan_label_history`. PD: thêm DTG union từ `dtg_item_detail` | shirt |
| Tồn đơn | `order_inventory` | — | Giống Pack & Ship nhưng đếm `COUNT(DISTINCT order_code)` | order |

### DTG Teams (**không** cần `factory`)

| Team | `team` param | Dept code | Data Source | Đơn vị |
|------|-------------|-----------|-------------|--------|
| Pick DTG | `pick_dtg` | `pick_dtg` | `dtg_folder_detail` + `dtg_item_detail` | shirt |
| In DTG | `dtg_print` | `dtg_print` | `dtg_item_detail` + `dtg_printed_product` | file |
| In DTG (Machine Split) | `dtg_print_split` | — | Tương tự `dtg_print` + chia theo tỷ lệ máy | file |

> **Machine Split Ratios (DTG Print):**
> - Apollo: 250 file/h → **62.5%**
> - ATLAS_1: 75 file/h → **18.75%**
> - ATLAS_2: 75 file/h → **18.75%**

## API Endpoints

### 1. GET `/v1/fplatform/daily-inventory` — Tồn đầu/cuối ngày (Public)

| Param | Type | Required | Values | Default |
|-------|------|----------|--------|---------|
| `team` | string | ✅ | `print`, `cut`, `pick`, `mockup`, `pack_ship`, `order_inventory`, `pick_dtg`, `dtg_print`, `dtg_print_split` | — |
| `date` | string | ❌ | `YYYY-MM-DD` | Hôm nay |

### 2. GET `/v1/admin/fplatform/inventory` — Tồn tất cả team (Private)

| Param | Type | Required | Values | Default |
|-------|------|----------|--------|---------|
| `date` | string | ❌ | `YYYY-MM-DD` | Hôm nay |

### 3. GET `/v1/admin/fplatform/hourly-metrics` — Hiệu suất theo giờ (Private)

| Param | Type | Required | Values |
|-------|------|----------|--------|
| `team` | string | ✅ | `print`, `cut`, `pick`, `mockup`, `pack_ship`, `dtg_print`, `pick_dtg` |
| `metric` | string | ✅ | `productivity`, `staff_count`, `staff_productivity`, `machine_productivity` |
| `start_shift` | datetime | ✅ | `YYYY-MM-DD HH:mm:ss` (US/Central) |
| `end_shift` | datetime | ✅ | `YYYY-MM-DD HH:mm:ss` (US/Central), phải sau start_shift |

#### Team × Metric Compatibility

| Team | productivity | staff_count | staff_productivity | machine_productivity |
|------|:-----------:|:-----------:|:------------------:|:-------------------:|
| print | ✅ | ✅ | ❌ | ✅ |
| cut | ✅ | ✅ | ✅ | ❌ |
| pick | ✅ | ✅ | ✅ | ❌ |
| mockup | ✅ | ✅ | ✅ | ❌ |
| pack_ship | ✅ | ✅ | ✅ | ❌ |
| dtg_print | ✅ | ❌ | ❌ | ✅ |
| pick_dtg | ✅ | ✅ | ✅ | ❌ |

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

| Param | Type | Required | Values | Default |
|-------|------|----------|--------|---------|
| `date` | string | ❌ | `YYYY-MM-DD` | Hôm nay |

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

| Endpoint | Data Type | TTL | Strategy |
|----------|-----------|-----|----------|
| daily-inventory | Ngày quá khứ | 1 giờ | `Cache::remember` |
| daily-inventory | Ngày hôm nay | 5 phút | `Cache::remember` |
| all-teams inventory | Ngày quá khứ | 1 giờ | `Cache::remember` |
| all-teams inventory | Ngày hôm nay | 5 phút | `Cache::remember` |
| hourly-metrics | Tất cả | 5 phút | `Cache::remember` |
| hotshot-orders | Ngày quá khứ | 1 giờ | `Cache::remember` |
| hotshot-orders | Ngày hôm nay | 5 phút | `Cache::remember` |

## Optimizations

- **QueriesFplatform Trait**: DRY — printer CTE builder, safe query execution, result formatting, multi-row support
- **Parameterized Tasks**: 5 hourly tasks handle 14+ query variants via `HourlyMetricType` enum (thay vì 18 files riêng lẻ)
- **Enum-driven validation**: `Team.supportsMetric()` rejects invalid team×metric combinations at request level
- **Error Handling**: `try/catch` + `Log::warning` — remote DB failure trả về empty/404 thay vì 500
- **PDO Timeout**: 10s connection timeout cho remote RDS slave
- **Cache::remember**: Atomic single-call caching (not has+get pattern)

## File Structure

```
FplatformData/
├── Enums/
│   ├── FactoryLine.php          # FLS | PD + extraPrinters()
│   ├── HourlyMetricType.php     # productivity | staff_count | staff_productivity | machine_productivity
│   ├── WorkType.php             # In(0) | Cat(2) | Pick(100) + doneStatus()
│   └── Team.php                 # 9 team types + requiresFactory() + supportsMetric()
├── Models/
│   ├── FolderManage.php         # folder_manage
│   ├── UserGroupScan.php        # user_group_scan
│   └── PrinterManage.php        # printer_manage
├── Traits/
│   └── QueriesFplatform.php     # Shared: printer builder, queryFplatform/All, formatResult/HourlyResults
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

### SQL Reference Files (docs/)

```
docs/
├── rpt_factory_ops_metrics_v4.sql  # [CHÍNH] 21 nhóm query, nguồn chính thức
├── ton_dau_ton_cuoi_in.sql         # [REF] Team In/Cắt (legacy)
├── ton_dau_ton_cuoi_pick.sql       # [REF] Team Pick (legacy)
├── ton_dau_ton_cuoi_mockup.sql     # [REF] Team Mockup (legacy)
├── ton_dau_ton_cuoi_pack_ship.sql  # [REF] Team Pack & Ship (legacy)
└── ton_dau_ngay_update.sql         # [DEPRECATED]
```
