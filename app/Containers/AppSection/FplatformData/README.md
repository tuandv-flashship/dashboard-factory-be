# FplatformData Container

Container lấy dữ liệu **tồn đầu/cuối ngày** từ database `fplatform` (external, read-only).

## Mô tả

Container cung cấp số liệu tồn kho theo ngày cho 8 team thuộc 2 dây chuyền DTF (FLS/PD) và DTG. Tất cả dữ liệu được query từ database `fplatform` thông qua connection riêng — không migration, không write.

> **Lưu ý:**
> - Database `fplatform` là **read-only** — chỉ SELECT, không INSERT/UPDATE/DELETE.
> - Connection config: `config/database.php` → `fplatform` (sử dụng `DB_*_FPLATFORM` env vars).
> - Có PDO timeout 10s để bảo vệ khi remote DB chậm/down.

## Teams & Data Sources

### DTF Teams (yêu cầu `factory`: FLS hoặc PD)

| Team | `team` param | Data Source | Đơn vị |
|------|-------------|-------------|--------|
| In | `in` | `folder_manage` + `user_group_scan` (work_type=0) | file |
| Cắt | `cat` | `folder_manage` + `user_group_scan` (work_type=2) | file |
| Pick | `pick` | `folder_manage` + `user_group_scan` (work_type=100, copy_job=0) | product |
| Mockup | `mockup` | `folder_manage` + `order_check_file_dropbox` + `log_check_mockup` | file |
| Pack & Ship | `pack_ship` | `folder_manage` + `order_check_file_dropbox` + `scan_label_history` | shirt |

### DTG Teams (**không** cần `factory`)

| Team | `team` param | Data Source | Đơn vị |
|------|-------------|-------------|--------|
| Pick DTG | `dtg_pick` | `dtg_folder_detail` + `dtg_item_detail` | shirt |
| In DTG | `dtg_print` | `dtg_item_detail` + `dtg_printed_product` | file |
| In DTG (Machine Split) | `dtg_print_split` | Tương tự `dtg_print` + chia theo tỷ lệ máy | file |

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
│   ├── GetDailyInventoryTask.php      # Team IN/CẮT (DTF) — SQL lines 7-71
│   ├── GetPickInventoryTask.php       # Team Pick (DTF) — SQL lines 81-145
│   ├── GetMockupInventoryTask.php     # Team Mockup (DTF) — SQL lines 154-272
│   ├── GetPackShipInventoryTask.php   # Team Pack & Ship (DTF) — SQL lines 282-396
│   ├── GetDtgPickInventoryTask.php    # Team Pick (DTG) — SQL lines 405-428
│   ├── GetDtgPrintInventoryTask.php   # Team IN (DTG) — SQL lines 437-465
│   └── GetDtgPrintMachineSplitTask.php # Team IN (DTG) split — SQL lines 474-521
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
