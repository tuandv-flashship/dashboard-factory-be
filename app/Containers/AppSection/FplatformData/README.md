# FplatformData Container (v3.0.0)

Container lấy dữ liệu **tổng việc ngày**, **hiệu suất theo giờ**, **đơn hotshot** và **log file cut** từ database `fplatform` và `report` (external, read-only).

## Mô tả

Container cung cấp số liệu tồn kho, hiệu suất sản xuất, nhân viên và máy in theo giờ cho các team thuộc 2 dây chuyền DTF (FLS/PD) và DTG. Tất cả dữ liệu được query từ database `fplatform` và `report` (cross-database, cùng MySQL connection) — không migration, không write.

> **Lưu ý:**
>
> - Database `fplatform` và `report` là **read-only** — chỉ SELECT, không INSERT/UPDATE/DELETE.
> - Connection config: `config/database.php` → `fplatform` (sử dụng `DB_*_FPLATFORM` env vars). Cross-database access via `report.report_orders` prefix.
> - Có PDO timeout 10s để bảo vệ khi remote DB chậm/down.

## Database Tables (fplatform + report)

Các table thuộc database `fplatform` và `report` (external, read-only, cùng MySQL server). Dưới đây mô tả chi tiết các table và các cột chính được sử dụng.

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
| `scan_label_history` | Lịch sử quét nhãn khi đóng gói. | `barcode`, `index_num`, `user_id`, `created_at`, `mark_time`, `order_id` |

### Bảng Report (database `report` — cross-database access)

| Table                    | Mô tả                                                                                                 | Các cột chính sử dụng                                             |
| ------------------------ | ----------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| `report.report_orders`   | **(v3.0.0)** Báo cáo trạng thái đơn hàng. JOIN bằng `orders.id`. Thay thế `scan_label_history` cho status tracking. | `id`, `first_get_label_at`, `last_get_label_at` |

### Bảng DTG

| Table                 | Mô tả                 | Các cột chính sử dụng                                                                                                                     |
| --------------------- | --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `dtg_folder_detail`   | Chi tiết folder DTG.  | `folder_key`, `estimate_folder_date`, `done_at`, `done_by`, `done_user_name`                                                              |
| `dtg_item_detail`     | Chi tiết item in DTG. | `folder_key`, `order_code`, `index_num`, `distribute_id`, `estimate_folder_date`, `active`                                                |
| `dtg_printed_product` | Trạng thái in DTG.    | `order_code`, `index_num`, `distribute_id`, `print_status` (0/null=chưa in, 1=đã in), `printed_by` (Apollo/ATLAS_1/ATLAS_2), `product_id` |

## SQL Reference

SQL tham chiếu v3.0.0 nằm trong `docs/sql_v3/` directory (6 files) và `sql/` directory (12 files legacy).

> **Ghi chú v3.0.0:**
>
> - Pack Ship tồn: `scan_label_history` JOIN bằng `mark_time` + `order_id` (thay vì barcode + 15-day interval).
> - Tồn đơn / Hotshot Order / Hotshot Pack Ship: dùng `report.report_orders` (first_get_label_at, last_get_label_at) thay cho `scan_label_history`.
> - `folder_status` column: phân loại DON GUI LAI / DON UU TIEN GUI LAI / IN — special handling trong tong_don/da_lam.
> - Pack Ship trả thêm `da_lam` (tổng đã hoàn thành).
> - PD Hourly Productivity: CTE mới (target_printers → slh_filtered → dtf + dtg UNION ALL).
> - SQL 27 (NEW): Log file cut theo user — `user_group_scan` + `folder_manage` + `user`.
> - Parameter `:estimate_date` (date) — cho tồn đầu/cuối ngày.
> - Parameter `:start_shift`, `:end_shift` (datetime, US/Central) — cho hourly metrics.
> - Parameter `:start_log`, `:end_log` (datetime) — cho log file cut.

## Teams & Data Sources

### DTF Teams (yêu cầu `factory`: FLS hoặc PD)

| Team        | `team` param      | Dept code   | Data Source (v2.0.0)                                                                                                                  | Đơn vị  |
| ----------- | ----------------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| In          | `print`           | `print`     | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `user_group_scan` (work_type=0, work_status=1)                     | file    |
| Cắt         | `cut`             | `cut`       | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `user_group_scan` (work_type=2, work_status=0, copy_job=0)         | file    |
| Pick        | `pick`            | `pick`      | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `user_group_scan` (work_type=100, copy_job=0, created_at interval) | product |
| Mockup      | `mockup`          | `mockup`    | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `log_check_mockup`                                                 | file    |
| Pack & Ship | `pack_ship`       | `pack_ship` | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → `scan_label_history` (mark_time + order_id). PD: thêm DTG union từ `dtg_item_detail`. **v3: trả cả `tong_viec` + `da_lam`**      | shirt   |
| Tồn đơn     | `order_inventory` | —           | `folder_manage` → `order_check_file_dropbox` → `orders` (filter) → **`report.report_orders`** (first_get/last_get). DON GUI LAI special handling. Đếm `COUNT(DISTINCT order_code)`             | order   |

### Hotshot Teams (yêu cầu `factory`: FLS hoặc PD)

| Team                | `team` param        | Data Source (v2.0.0)                                                                        | Đơn vị  | Filter                            |
| ------------------- | ------------------- | ------------------------------------------------------------------------------------------- | ------- | --------------------------------- |
| Hotshot In          | `hotshot_print`     | `folder_manage` → `order_check_file_dropbox` → `orders` → `user_group_scan` (work_type=0)   | file    | `printer_default = MayHOTSHOT/PD` |
| Hotshot Pick        | `hotshot_pick`      | `folder_manage` → `order_check_file_dropbox` → `orders` → `user_group_scan` (work_type=100) | product | `printer_default = MayHOTSHOT/PD` |
| Hotshot Cắt         | `hotshot_cut`       | `folder_manage` → `order_check_file_dropbox` → `orders` → `user_group_scan` (work_type=2)   | file    | `printer_default = MayHOTSHOT/PD` |
| Hotshot Mockup      | `hotshot_mockup`    | `folder_manage` → `order_check_file_dropbox` → `orders` → `log_check_mockup`                | file    | `printer_default = MayHOTSHOT/PD` |
| Hotshot Pack & Ship | `hotshot_pack_ship` | `folder_manage` → `order_check_file_dropbox` → `orders` → **`report.report_orders`**. DON GUI LAI excluded              | shirt   | `printer_default = MayHOTSHOT/PD` |
| Hotshot Đơn         | _(existing)_        | Dùng endpoint riêng `/hotshot-orders`. **v3:** `report.report_orders`                                                       | order   | `printer_default = MayHOTSHOT/PD` |

> **Hotshot teams** trả về `{ tong_viec, da_lam }` — khác với teams thường chỉ trả `{ tong_viec }`.
> **v3.0.0:** Pack & Ship thường cũng trả `{ tong_viec, da_lam }` (giống hotshot).

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
        "tong_don": 15,
        "da_lam": 3,
        "factory": "FLS"
    }
}
```

### 5. GET `/v1/fplatform/log-file-cut` — Log File Cut theo User (Private) 🆕

| Param       | Type     | Required | Values                        | Default |
| ----------- | -------- | -------- | ----------------------------- | ------- |
| `start_log` | datetime | ✅       | `YYYY-MM-DD HH:mm:ss`        | —       |
| `end_log`   | datetime | ✅       | `YYYY-MM-DD HH:mm:ss`, > start_log | —  |

**Auth:** 🔒 Private — `shifts.index` hoặc `admin`

**SQL:** `27_log_file_cut_theo_user.sql` — `user_group_scan` + `folder_manage` + `user`, filter: `work_status=0`, `work_type=2` (CẮT)

**Caching:** 5 phút

```json
{
    "data": [
        {
            "username": "user1",
            "created_at": "2026-04-14 08:30:00",
            "total_file": 25
        },
        {
            "username": "user2",
            "created_at": "2026-04-14 09:15:00",
            "total_file": 18
        }
    ]
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
| log-file-cut        | Tất cả       | 5 phút | `Cache::remember` |

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

## Hourly Sync Pipeline (Đồng bộ khung giờ)

FplatformData container là nguồn dữ liệu chính cho pipeline đồng bộ tự động từ Fplatform DB → Dashboard DB.
Pipeline được orchestrate bởi `Production` container (`SyncHourlyRecordsTask`), sử dụng Actions/Tasks/Jobs của container này.

### Kiến trúc 2-Stage Parallel Pipeline

```
Cron (mỗi phút)
  └→ SyncHourlyRecordsJob
      └→ ShiftSchedulerGuard.shouldSync()
          └→ SyncHourlyRecordsTask.run()
              │
              ├── Stage 1: Parallel Inventory Fetch ─────────────────────────
              │   └→ GetAllTeamsInventoryTask.dispatchParallelFetch()
              │       └→ Bus::batch([FetchTeamInventoryJob × 11-13])
              │           └→ GetDailyInventoryAction.run($date, $team) per job
              │               └→ Cache::put("fplatform:team-inventory:{team}:{date}")
              │
              └── Stage 2: (then callback sau Stage 1 hoàn thành) ───────────
                  ├→ assembleFromCache() → allInventory
                  ├→ Bus::batch([SyncDepartmentHourlyJob × N])
                  │   └→ Mỗi job: 3 FPlatform API calls + update hourly_records
                  └→ SyncOrderInventoryTask.run() (synchronous)
                      └→ Upsert order_summaries
```

### Stage 1: Parallel Inventory Fetch

Dispatch `FetchTeamInventoryJob` cho mỗi team qua `Bus::batch()` trên queue `sync`.
Horizon workers thực thi song song (~2-3s thay vì ~15s sequential).

**Team Resolution** (`GetAllTeamsInventoryTask.resolveTeams()`):

| Factory | Teams | Số lượng |
|---------|-------|----------|
| FLS | 6 DTF + 5 Hotshot | 11 |
| PD | 6 DTF + 2 DTG + 5 Hotshot | 13 |

Kết quả mỗi team được cache riêng: `fplatform:team-inventory:{team}:{date}`
(TTL: hôm nay = 5 phút, quá khứ = 1 giờ, sentinel `false` cho "no data" vì Redis drops `null`).

### Stage 2: Department Sync

Sau Stage 1, `assembleFromCache()` ghép tất cả per-team cache → `allInventory`.
Sau đó dispatch `SyncDepartmentHourlyJob` cho mỗi department.

**Mỗi department job thực hiện 5 bước:**

#### 2.1. Refresh Day Start Inventory

Lấy `tong_viec` từ `allInventory['teams'][team]`, cập nhật `shift_details.day_start_inventory`.

> **Lưu ý:** `DtgPrint` hourly metrics dùng `Team::DtgPrint`, nhưng inventory được lưu dưới key `Team::DtgPrintSplit`.

#### 2.2. Sync Hotshot Data

Cập nhật `shift_details.hotshot_total` và `shift_details.hotshot_completed` từ `allInventory`.
DTG departments không có hotshot → skip.

| Dept code | Hotshot key |
|-----------|-------------|
| `print` | `hotshot_print` |
| `cut` | `hotshot_cut` |
| `pick` | `hotshot_pick` |
| `mockup` | `hotshot_mockup` |
| `pack_ship` | `hotshot_pack_ship` |

#### 2.3. Batch-fetch FPlatform Hourly Metrics (3 API calls per dept)

| # | Metric Type | Mục đích | Kết quả |
|---|------------|----------|---------|
| 1 | `Productivity` | Sản lượng thực tế mỗi giờ | `productivityMap[hourKey] = SUM(value)` |
| 2 | `StaffCount` | Số nhân viên mỗi giờ | `staffCountMap[hourKey] = COUNT(DISTINCT user)` |
| 3 | `MachineProductivity` hoặc `StaffProductivity` | Chi tiết per máy/NV | `productivityDetailMap[hourKey] = [items...]` |

**Logic chọn metric #3:**
- `Team::Print` → luôn `MachineProductivity` (DTF group theo máy)
- `isPerMachineDtg` → `MachineProductivity`
- Còn lại → `StaffProductivity`

#### 2.4. Sync hourly_records

Mỗi slot được phân loại theo thời gian hiện tại:

| Loại slot | Điều kiện | Xử lý |
|-----------|-----------|--------|
| **Future** | `now < activationTime` | Chỉ update `hour_start_inventory` |
| **Active** | slot đang chạy | Sync data + `status = Active` |
| **Passed** | `now >= endTime` | Sync data + `status = Completed` |

**Dữ liệu được sync từ FPlatform (auto):**

| Field | Nguồn | Ghi chú |
|-------|--------|---------|
| `actual` | `productivityMap[hourKey]` | Sản lượng thực tế |
| `staff` | `staffCountMap[hourKey]` hoặc machine count (DTG) | Số NV/máy |
| `hour_start_inventory` | Cascading từ `day_start_inventory` | Tồn đầu giờ |
| `efficiency` | `(actual / effectiveTarget) × 100` | Hiệu suất % |
| `status` | Dựa trên thời gian | Pending → Active → Completed |
| `productivity_json` | `productivityDetailMap[hourKey]` | Chi tiết per machine/staff |

**Dữ liệu KHÔNG bị sync (manual-only):** `target`, `staff_required`, `kpi_minutes`, `kpi_hours`, `kpi_percent`.

#### 2.5. Inventory Cascading

Tồn cuối giờ = tồn đầu giờ trừ sản lượng, cascade sang slot tiếp theo:

- Passed/Completed slots: `currentInv -= actual` (trừ sản lượng thực)
- Active/Future slots: `currentInv -= effectiveTarget` (trừ target dự kiến)
- Luôn đảm bảo `max(0, ...)`

### Stage 2 (cont.): Order Inventory Sync

Chạy synchronous sau khi dispatch dept jobs. Đọc `allInventory['teams']['order_inventory']`
(đã được cache từ Stage 1), upsert vào bảng `order_summaries`.

**Line mapping:**
- FLS: `line='dtf'` (DTF1-FLS) only
- PD: `line='dtf'` (DTF2-PD) + `line='dtg'` (DTG-PD)

**Data written:**

| Field | Source |
|-------|--------|
| `total` | `tong_viec` |
| `completed` | `da_lam` |
| `remaining` | `max(0, total - completed)` |
| `rush_total` | Từ `GetHotshotOrderInventoryTask` |
| `rush_completed` | Từ `GetHotshotOrderInventoryTask` |
| `progress` | `(completed / total) × 100` |
| `estimated_done` | MAX `end_time` từ `shift_details` |

### Scheduled vs Manual Sync

| Mode | Entry Point | Behavior |
|------|------------|----------|
| **Scheduled (cron)** | `SyncHourlyRecordsJob` | Chạy mỗi phút, `ShiftSchedulerGuard` quyết định. **Skip dept đã hết giờ** (end_time guard). |
| **Manual resync (API)** | `ResyncHourlyRecordsController` | Bypass guard. Có thể sync 1 dept (`shift_detail_id`) hoặc tất cả (`forceAll`). |
| **Manual resync (CLI)** | `ResyncHourlyRecordsCommand` | Tương tự API. |

### Timezone Convention

Tất cả datetime truyền vào FPlatform queries phải ở **US/Central (Chicago)**.
FPlatform SQL sử dụng `CONVERT_TZ(?, 'US/Central', '+7:00')` để convert sang UTC+7 (timezone lưu trữ của Fplatform).

- `shift_details.start_time` → US/Central
- `$shiftStart`, `$shiftEnd` → format `Y-m-d H:i:s`, US/Central
- `estimate_date` → date only (date ở US/Central)

### Data Flow Summary

```
FPLATFORM DB (remote, read-only)         DASHBOARD DB (local)
─────────────────────────────────        ─────────────────────────────
                                         hourly_records
  Stage 1: Inventory queries     ──→       ← hour_start_inventory
  (per team × 11-13 parallel)             ← (day_start_inventory)
                                  
                                         shift_details
                                           ← day_start_inventory
                                           ← hotshot_total
                                           ← hotshot_completed
                                  
  Stage 2: Hourly metrics        ──→     hourly_records
  (3 calls × N depts parallel)            ← actual
                                           ← staff
                                           ← efficiency
                                           ← status
                                           ← productivity_json
                                  
  Order inventory (from cache)   ──→     order_summaries
                                           ← total, completed, remaining
                                           ← rush_total, rush_completed
                                           ← progress, estimated_done
```

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
│   ├── GetLogFileCutTask.php            # 🆕 Log file cut theo user (SQL 27)
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
    │   ├── GetHotshotOrdersController.php      # Private
    │   └── GetLogFileCutController.php         # 🆕 Private
    ├── Requests/
    │   ├── GetDailyInventoryRequest.php
    │   ├── GetAllTeamsInventoryRequest.php
    │   ├── GetHourlyMetricsRequest.php
    │   ├── GetHotshotOrdersRequest.php
    │   └── GetLogFileCutRequest.php            # 🆕
    └── Routes/
        ├── GetDailyInventory.v1.public.php
        ├── GetAllTeamsInventory.v1.private.php
        ├── GetHourlyMetrics.v1.private.php
        ├── GetHotshotOrders.v1.private.php
        └── GetLogFileCut.v1.private.php        # 🆕
```

### SQL Reference Files

```
# v3.0.0 reference (authoritative)
docs/sql_v3/
├── 05_tong_viec_team_pack_ship.sql     # Pack & Ship (mark_time + order_id, da_lam)
├── 06_tong_don_theo_don.sql            # Tồn đơn hàng (report.report_orders)
├── 11_hieu_suat_gio_pack_ship.sql      # Hourly Pack Ship (PD: DTF+DTG CTE)
├── 16_so_don_hotshot.sql               # Hotshot order (report.report_orders)
├── 26_hotshot_ao_pack_ship.sql         # Hotshot Pack & Ship (report.report_orders)
└── 27_log_file_cut_theo_user.sql       # 🆕 Log file cut theo user

# v2.0.0 reference (still valid for unchanged teams)
sql/
├── 01_tong_viec_team_in.sql            # Team In (FLS + PD + DTG)
├── 02_tong_viec_team_pick.sql          # Team Pick
├── 03_tong_viec_team_cat.sql           # Team Cắt
├── 04_tong_viec_team_mockup.sql        # Team Mockup
├── 05_tong_viec_team_pack_ship.sql     # [SUPERSEDED by v3] Team Pack & Ship
├── 06_tong_don_theo_don.sql            # [SUPERSEDED by v3] Tồn đơn hàng
├── 16_so_don_hotshot.sql               # [SUPERSEDED by v3] Hotshot order count
├── 22_hotshot_file_team_in.sql         # Hotshot In
├── 23_hotshot_ao_team_pick.sql         # Hotshot Pick
├── 24_hotshot_file_team_cat.sql        # Hotshot Cắt
├── 25_hotshot_file_team_mockup.sql     # Hotshot Mockup
└── 26_hotshot_ao_pack_ship.sql         # [SUPERSEDED by v3] Hotshot Pack & Ship

# Legacy (deprecated)
docs/sql_v2_update_240226/
├── 01-06, 16, 22-26 .sql               # Bản gốc SQL v2 update spec

docs/
├── rpt_factory_ops_metrics_v8_1.sql   # [DEPRECATED] v1.1.0 - 24 nhóm query
├── ton_dau_ton_cuoi_*.sql             # [DEPRECATED] Legacy refs
└── ton_dau_ngay_update.sql            # [DEPRECATED]
```

## Changelog

### v3.0.0 (2026-05-19)

- **Cross-database `report.report_orders`**: Tồn đơn (06), Hotshot Order (16), Hotshot Pack Ship (26) chuyển từ `scan_label_history` sang `report.report_orders` (first_get_label_at / last_get_label_at). Cùng MySQL connection, cross-database via `report.` prefix.
- **Pack Ship mark_time JOIN (05)**: `scan_label_history` JOIN bằng `mark_time` + `order_id` (thay vì barcode + 15-day interval). Trả cả `tong_viec` + `da_lam`.
- **`folder_status` column**: Phân loại DON GUI LAI / DON UU TIEN GUI LAI / IN — special handling trong tong_don/da_lam counts.
- **PD Hourly Pack Ship CTE (11)**: Productivity riêng dùng CTE mới (target_printers → slh_filtered → dtf + dtg UNION ALL). StaffCount/StaffProductivity giữ legacy exclusion.
- **New endpoint: `GET /v1/fplatform/log-file-cut`**: Log file cut theo user (SQL 27). Query `user_group_scan` + `folder_manage` + `user`, filter work_type=2 (CẮT).
- **New files**: `GetLogFileCutTask`, `GetLogFileCutRequest`, `GetLogFileCutController`, route `GetLogFileCut.v1.private.php`.
- **DB permission**: Cần GRANT SELECT trên `report.report_orders` cho user `dashboard_data`.

### v2.0.0 (2026-04-24)

- **File-level granularity**: Thay `folder_manage.total_file` aggregate bằng `folder_manage JOIN order_check_file_dropbox` file-level counting cho tất cả team.
- **Order status filter**: CTE `order_status` JOIN `orders` table, loại bỏ đơn `HOLD/REQUEST_CANCEL/REJECTED/REJECT_REQUESTED/CANCELED` (~0.1% đơn).
- **DTG orders JOIN**: DTG queries JOIN `orders` bằng `o.id = d.order_id` thay vì `order_code`.
- **Team-specific updates**: In `work_status=1`, Pick thêm `copy_job=0` + `created_at interval`, Cắt thêm `copy_job=0`.
- **COLLATE fix**: Thêm `COLLATE utf8mb4_unicode_ci` trên tất cả `orders.order_code` JOINs (utf8mb3 ↔ utf8mb4).
- **Code dedup**: Di chuyển `formatHotshotResult()` và `hotshotPrinterList()` vào `QueriesFplatform` trait.
- **DB permission**: Cần GRANT SELECT trên `fplatform.orders` cho user `dashboard_data`.
