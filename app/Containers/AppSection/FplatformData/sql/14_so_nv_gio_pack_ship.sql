-- =========================================
-- Description: Lấy số nhân viên theo từng giờ của team pack & ship áo
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF1 - FLS   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
slh_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed,
        user_id, 
        created_at
    FROM fplatform.scan_label_history
WHERE created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
      AND created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
),
ocfd_prepared AS (
    SELECT 
        file_name_order_code,
        folder COLLATE utf8mb4_unicode_ci AS folder_fixed
    FROM fplatform.order_check_file_dropbox
    WHERE folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
    AND status <> 2
)
SELECT 
    LEFT(CONVERT_TZ(slh.created_at, '+07:00', 'US/Central'), 13) AS hour,
    COUNT(DISTINCT slh.user_id) AS sum_staff
FROM slh_filtered slh
JOIN ocfd_prepared ocfd 
    ON ocfd.file_name_order_code = slh.barcode_fixed
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder_fixed
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
WHERE fm.status_folder <> 2
GROUP BY 1
ORDER BY 1;

-- PD 
WITH fls_printers AS (
    SELECT REPLACE(NAME, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci AS machine_name 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION SELECT 'MayHOTSHOT' COLLATE utf8mb4_unicode_ci
    UNION SELECT 'MayREPRINT' COLLATE utf8mb4_unicode_ci
),
slh_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode, 
        user_id, 
        created_at
    FROM fplatform.scan_label_history
    WHERE created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    LEFT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    COUNT(DISTINCT slh.user_id) AS num_staff
FROM slh_filtered slh
LEFT JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = slh.barcode AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
LEFT JOIN fplatform.folder_manage fm 
    ON ocfd.folder COLLATE utf8mb4_unicode_ci = fm.folder 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
LEFT JOIN fls_printers fls
    ON COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) COLLATE utf8mb4_unicode_ci = fls.machine_name
WHERE 
    fls.machine_name IS NULL 
GROUP BY 1
ORDER BY 1;
