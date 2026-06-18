# Đặc tả Logic Tính Giờ Dự Kiến Hoàn Thành (Estimated End Time)

Tài liệu này mô tả chi tiết yêu cầu nghiệp vụ, công thức toán học và các bước xử lý kỹ thuật cho tính năng tính **Giờ Dự Kiến Hoàn Thành (Estimated End Time)** của từng bộ phận và của cả Line sản xuất.

---

## 1. Tổng quan & Yêu cầu nghiệp vụ

Trong quá trình quản lý sản xuất theo thời gian thực (real-time production dashboard), việc dự báo thời điểm bộ phận hoặc dây chuyền (line) chạy hết việc là rất quan trọng để điều phối nhân sự và máy móc.

*   **Giờ dự kiến hoàn thành của Bộ phận (`estimated_end_time`)**: Là thời điểm dự kiến bộ phận đó hoàn thành toàn bộ khối lượng công việc hiện tại (tồn đơn hàng đầu giờ của ca hoặc của khung giờ).
*   **Thời điểm hết việc (`out_of_work_at`)**: Khung giờ (slot) đầu tiên mà bộ phận đó hoàn thành hết lượng tồn việc đầu giờ.
*   **Giờ dự kiến hoàn thành của Line sản xuất (`estimated_done`)**: Do các bộ phận hoạt động theo dạng dây chuyền/chuỗi liên kết, thời điểm kết thúc dự kiến của toàn line được quyết định bởi **bộ phận hoàn thành muộn nhất** trong ca đó.

---

## 2. Công thức & Quy trình tính toán

### Bước 2.1: Tính Mục tiêu hiệu dụng (Effective Target) cho mỗi khung giờ

Trước khi tính thời gian, hệ thống cần biết mục tiêu sản xuất thực tế của từng khung giờ. Mục tiêu này được tính toán động thông qua class [TargetEstimator](file:///Users/tuandang/Data/FlashShip/dashboard-factory/dashboard-be/app/Containers/AppSection/Production/Support/TargetEstimator.php).

*   **Nếu có mục tiêu thủ công (`manualTarget > 0`)**: Sử dụng luôn mục tiêu này.
*   **Nếu không có mục tiêu thủ công**: Tính toán mục tiêu ước lượng dựa trên năng suất (KPI per hour):
    *   **Đối với bộ phận tính theo Máy (Per Machine - ví dụ DTF, DTG)**:
        $$\text{Target} = \text{round}\left( \frac{\text{kpi\_per\_hour} \times \text{kpi\_percent}}{100} \right)$$
    *   **Đối với bộ phận tính theo Người (Per Person)**:
        $$\text{Target} = \text{round}\left( \frac{\text{kpi\_per\_hour} \times \text{kpi\_percent}}{100} \right) \times \text{staff\_required}$$

---

### Bước 2.2: Tính Giờ hoàn thành dự kiến của Bộ phận (`estimated_end_time`)

Hàm [computeEstimatedEndTime](file:///Users/tuandang/Data/FlashShip/dashboard-factory/dashboard-be/app/Containers/AppSection/Production/Support/DepartmentSummary.php#L227-L258) trong class [DepartmentSummary](file:///Users/tuandang/Data/FlashShip/dashboard-factory/dashboard-be/app/Containers/AppSection/Production/Support/DepartmentSummary.php) sẽ duyệt tuần tự qua các khung giờ từ đầu ca đến cuối ca:

#### Kịch bản A: Có khung giờ hết việc
Nếu tồn tại khung giờ $i$ thỏa mãn:
$$\text{hour\_start\_inventory}_i \le \text{effectiveTarget}_i \quad (\text{với } \text{effectiveTarget}_i > 0)$$

Hệ thống xác định đây là khung giờ sẽ hết việc:
1.  **Mốc thời điểm hết việc (`out_of_work_at`)**: Lưu lại nhãn khung giờ đó (ví dụ: `14h-15h`).
2.  **Tính tỷ lệ hoàn thành công việc**:
    $$\text{ratio} = \frac{\text{hour\_start\_inventory}_i}{\text{effectiveTarget}_i}$$
3.  **Quy đổi ra số phút chạy trong slot**:
    $$\text{minutes} = \text{ceil}(\text{ratio} \times \text{slotMinutes})$$
    *(Với `slotMinutes` là thời lượng của khung giờ, mặc định là 60 phút).*
4.  **Tính mốc thời gian hoàn thành cụ thể**:
    $$\text{totalMinutes} = (\text{startHour} \times 60) + \text{minutes}$$
    $$\text{estimated\_end\_time} = \text{formatTime}(\text{totalMinutes})$$

#### Kịch bản B: Không có khung giờ nào hết việc (Quá tải)
Nếu duyệt qua toàn bộ các khung giờ của ca mà lượng tồn việc đầu giờ luôn lớn hơn mục tiêu của khung giờ đó, hệ thống sẽ tính toán thêm thời gian cần thiết (phút bù giờ) ngoài ca để hoàn thành nốt lượng việc tồn:

1.  **Xác định lượng việc tồn còn lại sau ca (`remainingInventory`)**:
    $$\text{remainingInventory} = \max(0, \text{hour\_start\_inventory}_{\text{last}} - \text{effectiveTarget}_{\text{last}})$$
2.  **Xác định năng suất sản xuất (đơn/phút) của bộ phận (`ratePerMinute`)**:
    *   Nếu $\text{effectiveTarget}_{\text{last}} > 0$:
        $$\text{ratePerMinute} = \frac{\text{effectiveTarget}_{\text{last}}}{\text{kpi\_minutes}_{\text{last}}}$$
    *   Nếu $\text{effectiveTarget}_{\text{last}} = 0$: Sử dụng năng suất danh định làm dự phòng:
        $$\text{ratePerMinute} = \frac{\text{fallbackCapacityPerHour}}{\text{kpi\_minutes}_{\text{last}}}$$
        *(Với `fallbackCapacityPerHour` là `kpi_per_hour` gốc, được nhân với nhân sự/máy móc mặc định nếu cần).*
3.  **Tính toán số phút bù giờ (`extraMinutes`)**:
    *   Nếu $\text{ratePerMinute} > 0$:
        $$\text{extraMinutes} = \text{ceil}\left( \frac{\text{remainingInventory}}{\text{ratePerMinute}} \right)$$
    *   Nếu không xác định được năng suất: $\text{extraMinutes} = 0$.
4.  **Giờ dự kiến hoàn thành mới**:
    $$\text{totalMinutes} = (\text{Giờ bắt đầu của slot cuối} \times 60) + \text{kpi\_minutes của slot cuối} + \text{extraMinutes}$$

    Quy đổi `totalMinutes` sang định dạng chuỗi `HH:MM`:
    *   Nếu tổng số giờ $\text{hours} < 24$: Trả về `"HH:MM"` (ví dụ: `"15:23"`).
    *   Nếu tổng số giờ $\text{hours} \ge 24$: Rollover qua ngày hôm sau và thêm hậu tố chênh lệch ngày:
        $$\text{days} = \text{intdiv}(\text{hours}, 24)$$
        $$\text{hoursOfDay} = \text{hours} \bmod 24$$
        Định dạng trả về: `"HH:MM (+d)"` (ví dụ: `"01:00 (+1)"`, `"15:00 (+2)"`). 
        *(Lưu ý: Độ dài định dạng này tối đa là 10 ký tự, khớp hoàn hảo với giới hạn `string(10)` của cột `estimated_done` trong DB).*

---

### Bước 2.3: Tính Giờ hoàn thành dự kiến của Line sản xuất (`estimated_done`)

Được xử lý tại hàm `resolveEstimatedDone` trong [SyncOrderInventoryTask](file:///Users/tuandang/Data/FlashShip/dashboard-factory/dashboard-be/app/Containers/AppSection/Order/Tasks/SyncOrderInventoryTask.php#L193-L250):

1.  Lấy toàn bộ các bộ phận thuộc ca sản xuất hiện tại.
2.  Tính toán `estimated_end_time` cho từng bộ phận bằng thuật toán ở **Bước 2.2**.
3.  Tìm giá trị lớn nhất (**MAX**) của `estimated_end_time` giữa các bộ phận. Bộ phận nào xong muộn nhất sẽ là thời gian kết thúc dự kiến của toàn bộ line đó.
    $$\text{estimated\_done}_{\text{Line}} = \max \left( \text{estimated\_end\_time}_{\text{Bộ phận 1}}, \text{estimated\_end\_time}_{\text{Bộ phận 2}}, \dots \right)$$
4.  *Fallback:* Nếu chưa có dữ liệu hourly records nào được ghi nhận, hệ thống sẽ fallback về thời gian kết thúc ca lớn nhất được thiết lập sẵn trong bảng chi tiết ca (`shift_details.end_time`).

---

## 3. Ví dụ Minh Họa Cụ Thể

Giả sử Ca sản xuất có 3 khung giờ: `13h-14h`, `14h-15h`, `15h-16h`.

| Khung giờ | Tồn việc đầu giờ (`hour_start_inventory`) | Mục tiêu hiệu dụng (`effectiveTarget`) | Nhận xét |
| :--- | :--- | :--- | :--- |
| **13h-14h** | 150 đơn | 100 đơn | Lớn hơn mục tiêu $\to$ Chưa hết việc |
| **14h-15h** | 40 đơn | 80 đơn | Bé hơn hoặc bằng mục tiêu $\to$ **Hết việc tại khung giờ này** |
| **15h-16h** | 0 đơn | 80 đơn | - |

**Tính toán Giờ dự kiến hoàn thành:**
*   Phát hiện hết việc tại slot `14h-15h` (giờ bắt đầu `startHour = 14`).
*   Tỷ lệ hết việc: $\text{ratio} = 40 / 80 = 0.5$
*   Số phút chạy thêm từ đầu khung giờ: $\text{minutes} = 0.5 \times 60 \text{ phút} = 30 \text{ phút}$.
*   Mốc giờ dự kiến xong: $14\text{h} + 30\text{ phút} = \mathbf{14:30}$.
*   Mốc khung giờ hết việc (`out_of_work_at`): `14h-15h`.

---

## 4. Danh sách các file & symbol liên quan trong Codebase

*   **Tính toán KPI mục tiêu**: [TargetEstimator::effective()](file:///Users/tuandang/Data/FlashShip/dashboard-factory/dashboard-be/app/Containers/AppSection/Production/Support/TargetEstimator.php#L27)
*   **Tính giờ dự kiến của bộ phận**: [DepartmentSummary::computeEstimatedEndTime()](file:///Users/tuandang/Data/FlashShip/dashboard-factory/dashboard-be/app/Containers/AppSection/Production/Support/DepartmentSummary.php#L227)
*   **Tính giờ dự kiến của Line**: [SyncOrderInventoryTask::resolveEstimatedDone()](file:///Users/tuandang/Data/FlashShip/dashboard-factory/dashboard-be/app/Containers/AppSection/Order/Tasks/SyncOrderInventoryTask.php#L193)
