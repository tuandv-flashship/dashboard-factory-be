# Tính Số Hình Thực Tế Trong Từng Block Giờ (Team CẮT)

> **Version:** v1.0.0
> **Updated:** 2026-05-20
> **Nguồn gốc:** Google Sheet — Công thức tính hiệu suất cắt theo block 1h

## Mục đích

Phân bổ **số hình thực tế (actual images)** mà mỗi nhân viên CẮT đã xử lý vào từng **block 1 giờ**, dựa trên:

1. Log scan CUT (thời gian scan file)
2. Lịch nghỉ giải lao / nghỉ ăn trong ca
3. Tổng số hình của mỗi file

Kết quả cuối cùng: **Số hình làm thực tế trong từng block giờ** cho mỗi nhân viên.

---

## 1. Bảng Log Scan CUT (Dữ liệu đầu vào)

Dữ liệu lấy từ `user_group_scan` (work_type=2, work_status=0), mỗi bản ghi là 1 file CUT:

| Cột          | Ý nghĩa                                 | Ghi chú                                                  |
| ------------ | ---------------------------------------- | -------------------------------------------------------- |
| `folder`     | Mã folder                               | JOIN `folder_manage`                                     |
| `user_id`    | ID nhân viên                             | JOIN `user` → `username`                                 |
| `created_at` | Thời gian scan xong (**End time**)       | Giờ scan = End time = thời điểm CUT hoàn thành file      |
| `total_file` | Số hình trong file                       | Đơn vị tính hình (không phải file)                       |

> **Lưu ý:** `created_at` chính là **"Giờ scan"** = **End time** = last scan round time.

---

## 2. Rule Tính Start Time Của CUT

Mỗi bản ghi CUT chỉ lưu **End time** (thời điểm scan xong). Cần suy ngược **Start time** (thời điểm bắt đầu CUT file đó):

### Quy tắc

| # | Điều kiện                                                    | Start time =                                                    |
|---|--------------------------------------------------------------|-----------------------------------------------------------------|
| 1 | File **đầu tiên** của user (không có bản ghi trước đó)       | **Giờ bắt đầu ca của bộ phận CUT** (`shift_details.start_time` where dept=`cut`) |
| 2 | File **tiếp theo** (có bản ghi trước đó)                     | **End time (giờ scan) của file trước**                           |
| 3 | Giờ bắt đầu ca nằm **giữa** block 1h (vd: 8:30)            | Lấy Start time = **giờ bắt đầu ca của bộ phận CUT**             |

> **Quan trọng:** "Giờ bắt đầu ca" ở đây là giờ bắt đầu **riêng của bộ phận CUT** (lấy từ `shift_details` với `dept_code = 'cut'`), **không phải** giờ bắt đầu ca chung. Mỗi bộ phận có thể có giờ bắt đầu ca khác nhau.

### Ví dụ minh họa

**Trường hợp 1:** Bộ phận CUT bắt đầu ca lúc `08:00:00`

| File | User | Start time   | End time     |
| ---- | ---- | ------------ | ------------ |
| F1   | A    | **08:00:00** | 08:40:01     |
| F2   | A    | 08:40:01     | 09:10:16     |
| F3   | A    | 09:10:16     | 09:55:31     |
| F4   | A    | 09:55:31     | 10:10:46     |
| F5   | A    | 10:10:46     | 10:41:01     |

**Trường hợp 2:** Bộ phận CUT bắt đầu ca lúc `08:30:00`

| File | User | Start time   | End time     |
| ---- | ---- | ------------ | ------------ |
| F1   | A    | **08:30:00** | 08:40:01     |
| F2   | A    | 08:40:01     | 09:10:16     |
| F3   | A    | 09:10:16     | 09:55:31     |
| F4   | A    | 09:55:31     | 10:10:46     |
| F5   | A    | 10:10:46     | 11:41:01     |

> F1 luôn lấy Start time = giờ bắt đầu ca **của bộ phận CUT**. F2 trở đi lấy giờ scan (End time) của file trước.

---

## 3. Rule Tính Thời Gian CUT File Theo Từng Block 1h

Mỗi file CUT có thể **trải qua nhiều block 1h** (ví dụ: file bắt đầu 8:50, kết thúc 9:20 sẽ nằm ở cả block 8:00-9:00 và 9:00-10:00).

### 3.1. Số phút làm trong từng block (chưa trừ nghỉ)

```
A = max(0, min("Kết thúc việc", "Kết thúc Block") - max("Bắt đầu việc", "Bắt đầu Block"))
```

Trong đó:
- **Bắt đầu việc** = Start time của file
- **Kết thúc việc** = End time của file (giờ scan)
- **Bắt đầu Block** = giờ tròn bắt đầu block (vd: 8:00, 9:00, 10:00, ...)
- **Kết thúc Block** = giờ tròn kết thúc block (vd: 9:00, 10:00, 11:00, ...)

### 3.2. Số phút nghỉ trong từng block

```
B = max(0, min("Kết thúc nghỉ", "Kết thúc Block") - max("Bắt đầu nghỉ", "Bắt đầu việc", "Bắt đầu Block"))
```

Trong đó:
- **Bắt đầu nghỉ** = `break1_start`, `meal_break_start`, `break2_start`, `break3_start`
- **Kết thúc nghỉ** = `Bắt đầu nghỉ + break_minutes`
- Chỉ tính phần nghỉ **overlap** với cả block giờ VÀ thời gian làm việc của file

> **Lưu ý:** Công thức B dùng `max(3 giá trị)` — đảm bảo chỉ trừ phần nghỉ xảy ra **trong khoảng thời gian file đang được xử lý** (không trừ nghỉ trước khi bắt đầu file).

### 3.3. Số phút làm thực tế trong từng block

```
C = A - B
```

C là thời gian thực tế nhân viên dành cho file đó trong block giờ (đã loại nghỉ).

---

## 4. Bảng Danh Sách File (Ví dụ tham chiếu)

| File | User | Tổng hình | Start time | End time   | Nghỉ giải lao 1     | Nghỉ ăn              | Nghỉ giải lao 2     | Nghỉ giải lao 3     |
| ---- | ---- | --------- | ---------- | ---------- | -------------------- | --------------------- | -------------------- | -------------------- |
| F1   | A    | 41        | 06:00      | 08:40:01   | 08:30~08:45 (15')    | 11:00~11:30 (30')     | 13:00~13:15 (15')    | 16:00~16:15 (15')    |
| F2   | A    | 20        | 08:40:01   | 09:10:16   | (như trên)           | (như trên)            | (như trên)           | (như trên)           |
| F3   | A    | 14        | 09:10:16   | 09:55:31   | (như trên)           | (như trên)            | (như trên)           | (như trên)           |
| F4   | A    | 30        | 09:55:31   | 10:10:46   | (như trên)           | (như trên)            | (như trên)           | (như trên)           |
| F5   | A    | 2         | 10:10:46   | 10:41:01   | (như trên)           | (như trên)            | (như trên)           | (như trên)           |

> Lịch nghỉ lấy từ `shift_details` (`break1_start` / `break1_minutes`, `meal_break_start` / `meal_break_minutes`, v.v.)

> **1 ca làm việc có khoảng 12 tiếng nên tối đa 12 block 1h.**

---

## 5. Tính Số Phút Theo Từng Block (Chi tiết)

### 5.1. Số phút làm chưa trừ nghỉ (A) — cho từng file × block

Áp dụng công thức A cho mỗi cặp (file, block):

| File | 6:00 | 7:00 | 8:00  | 9:00  | 10:00 | 11:00 | ... | 17:00 |
| ---- | ---- | ---- | ----- | ----- | ----- | ----- | --- | ----- |
| F1   | 60   | 60   | 40.02 | 0     | 0     | 0     | ... | 0     |
| F2   | 0    | 0    | 19.98 | 10.27 | 0     | 0     | ... | 0     |
| F3   | 0    | 0    | 0     | 45.25 | 0     | 0     | ... | 0     |
| F4   | 0    | 0    | 0     | 4.48  | 10.77 | 0     | ... | 0     |
| F5   | 0    | 0    | 0     | 0     | 30.25 | 0     | ... | 0     |

### 5.2. Số phút nghỉ trong từng block (B)

Tính phần nghỉ overlap với block giờ VÀ thời gian file:

| File | 6:00 | 7:00 | 8:00  | 9:00 | 10:00 | 11:00 | ... | 17:00 |
| ---- | ---- | ---- | ----- | ---- | ----- | ----- | --- | ----- |
| F1   | 0    | 0    | 10.28 | 0    | 0     | 0     | ... | 0     |
| F2   | 0    | 0    | 4.73  | 0    | 0     | 0     | ... | 0     |
| F3   | 0    | 0    | 0     | 0    | 0     | 0     | ... | 0     |
| F4   | 0    | 0    | 0     | 0    | 0     | 0     | ... | 0     |
| F5   | 0    | 0    | 0     | 0    | 19.25 | 0     | ... | 0     |

> Ví dụ: F1 block 8:00-9:00, nghỉ giải lao 1 (8:30~8:45) overlap 10.28 phút (tính từ max(8:30, 06:00, 8:00) đến min(8:45, 9:00)).

### 5.3. Số phút làm thực tế (C = A - B)

| File | 6:00 | 7:00 | 8:00  | 9:00  | 10:00 | 11:00 | ... | 17:00 |
| ---- | ---- | ---- | ----- | ----- | ----- | ----- | --- | ----- |
| F1   | 60   | 60   | 29.74 | 0     | 0     | 0     | ... | 0     |
| F2   | 0    | 0    | 15.25 | 10.27 | 0     | 0     | ... | 0     |
| F3   | 0    | 0    | 0     | 45.25 | 0     | 0     | ... | 0     |
| F4   | 0    | 0    | 0     | 4.48  | 10.77 | 0     | ... | 0     |
| F5   | 0    | 0    | 0     | 0     | 11.00 | 0     | ... | 0     |

---

## 6. Tổng Phút Làm Thực Tế + Tổng Hình

Tổng hợp theo từng file:

| File | Tổng phút làm (ΣC) | Tổng hình |
| ---- | ------------------- | --------- |
| F1   | 149.74              | 41        |
| F2   | 25.52               | 20        |
| F3   | 45.25               | 14        |
| F4   | 15.25               | 30        |
| F5   | 11.00               | 2         |

> **Tổng hình** lấy từ `total_file` trong `user_group_scan` (số hình trong file CUT đó).

---

## 7. Tỷ Lệ Cắt Hình Thực Tế Từng Block

### Công thức

```
Rate = C(file, block) / ΣC(file)
```

Trong đó:
- `C(file, block)` = số phút làm thực tế của file đó trong block giờ
- `ΣC(file)` = tổng số phút làm thực tế của file đó (tổng tất cả block)

### Bảng tỷ lệ

| File | 6:00  | 7:00  | 8:00   | 9:00   | 10:00  | 11:00 | ... | 17:00 |
| ---- | ----- | ----- | ------ | ------ | ------ | ----- | --- | ----- |
| F1   | 40.1% | 40.1% | 19.9%  | 0%     | 0%     | 0%    | ... | 0%    |
| F2   | 0%    | 0%    | 100.0% | 0%     | 0%     | 0%    | ... | 0%    |
| F3   | 0%    | 0%    | 0%     | 100.0% | 0%     | 0%    | ... | 0%    |
| F4   | 0%    | 0%    | 0%     | 64.4%  | 35.6%  | 0%    | ... | 0%    |
| F5   | 0%    | 0%    | 0%     | 0%     | 100.0% | 0%    | ... | 0%    |

> Ý nghĩa: Rate cho biết bao nhiêu % thời gian xử lý file đó rơi vào block giờ nào.

---

## 8. Số Hình Làm Thực Tế Trong Từng Block (Kết quả cuối cùng)

### Công thức

```
Hình = Round(Rate × "Tổng hình", 0)
```

Trong đó:
- `Rate` = tỷ lệ thời gian ở bước 7
- `Tổng hình` = `total_file` của file CUT

### Bảng kết quả

| File | 6:00 | 7:00 | 8:00 | 9:00 | 10:00 | 11:00 | ... | 17:00 | **Tổng** |
| ---- | ---- | ---- | ---- | ---- | ----- | ----- | --- | ----- | -------- |
| F1   | 16   | 16   | 8    | 0    | 0     | 0     | ... | 0     | **41**   |
| F2   | 0    | 0    | 20   | 0    | 0     | 0     | ... | 0     | **20**   |
| F3   | 0    | 0    | 0    | 14   | 0     | 0     | ... | 0     | **14**   |
| F4   | 0    | 0    | 0    | 19   | 11    | 0     | ... | 0     | **30**   |
| F5   | 0    | 0    | 0    | 0    | 2     | 0     | ... | 0     | **2**    |

> **Tổng hình mỗi block** = SUM cột → đây là **Số hình thực tế trong từng block 1h** (kết quả cuối cùng).

---

## 9. Tổng Hợp Quy Trình

```
┌─────────────────────────────┐
│ 1. Lấy log scan CUT        │  ← user_group_scan (created_at = End time)
│    (username, end_time,     │
│     total_file)             │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 2. Suy Start time           │  ← File đầu: giờ bắt đầu ca
│    cho từng file            │  ← File tiếp: End time file trước
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 3. Tính A (phút làm/block)  │  ← Overlap file time ∩ block time
│    cho từng file × block    │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 4. Tính B (phút nghỉ/block) │  ← Overlap break time ∩ block time ∩ file time
│    cho từng file × block    │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 5. C = A - B                │  ← Phút làm thực tế
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 6. Rate = C / ΣC            │  ← Tỷ lệ phần trăm theo block
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 7. Hình = Round(Rate ×      │  ← Phân bổ hình theo tỷ lệ
│          Tổng hình, 0)      │
└─────────────────────────────┘
```

---

## 10. Dữ Liệu Nguồn (API / Database)

| Dữ liệu                 | Nguồn                                     | Endpoint / Table                        |
| ------------------------ | ---------------------------------------- | --------------------------------------- |
| Log scan CUT             | FPlatform DB (read-only)                  | `GET /v1/fplatform/log-file-cut`        |
| Lịch nghỉ giải lao       | Dashboard DB — `shift_details`           | `break1_start`, `break1_minutes`, ...   |
| Giờ bắt đầu/kết thúc ca | Dashboard DB — `shift_details`           | `start_time`, `work_hours`              |
| Block giờ                | Dashboard DB — `hourly_records`           | `hour_start`, `hour_end` (slot 1h)      |

### Schema liên quan (shift_details)

| Cột                 | Type      | Mô tả                    |
| ------------------- | --------- | ------------------------ |
| `start_time`        | datetime  | Giờ bắt đầu ca           |
| `work_hours`        | decimal   | Số giờ làm (net)         |
| `meal_break_start`  | time      | Giờ bắt đầu nghỉ ăn     |
| `meal_break_minutes`| smallint  | Số phút nghỉ ăn          |
| `break1_start`      | time      | Nghỉ giải lao 1 — bắt đầu |
| `break1_minutes`    | smallint  | Nghỉ giải lao 1 — số phút  |
| `break2_start`      | time      | Nghỉ giải lao 2 — bắt đầu |
| `break2_minutes`    | smallint  | Nghỉ giải lao 2 — số phút  |
| `break3_start`      | time      | Nghỉ giải lao 3 — bắt đầu |
| `break3_minutes`    | smallint  | Nghỉ giải lao 3 — số phút  |

---

## 11. Lưu Ý Quan Trọng

1. **1 ca có tối đa 12 block 1h** (ví dụ: 6:00 → 18:00)
2. **Đơn vị "hình"** lấy từ `total_file` trong `user_group_scan` — không phải số file mà là số hình trong file CUT
3. **Nghỉ giải lao** chỉ trừ khi overlap với cả block giờ **VÀ** thời gian xử lý file (công thức B dùng `max(3 giá trị)`)
4. **Round** kết quả cuối về số nguyên (`Round(..., 0)`)
5. **Timezone**: Log CUT lưu ở UTC+7, cần convert qua US/Central khi query API
6. **Sắp xếp file**: Các file của cùng 1 user được sắp theo thời gian scan (`created_at` ASC) để suy Start time

---

## Changelog

### v1.0.0 (2026-05-20)
- Initial documentation — chuyển từ Google Sheet specification sang markdown
