-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team pack & ship áo
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- PD   
SELECT 
    DATE_FORMAT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), '%Y-%m-%d %H') AS date_hour,
    COUNT(DISTINCT slh.barcode, slh.index_num) AS sum_shirt
FROM fplatform.scan_label_history slh
LEFT JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = slh.barcode COLLATE utf8mb4_0900_ai_ci 
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
LEFT JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
WHERE 
    slh.created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
    AND slh.created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
    AND (
        COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IS NULL
        OR COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) NOT IN (
            SELECT REPLACE(NAME, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci 
            FROM fplatform.printer_manage 
            WHERE factory = 'FLS'
            UNION ALL SELECT 'MayHOTSHOT' COLLATE utf8mb4_unicode_ci
            UNION ALL SELECT 'MayREPRINT' COLLATE utf8mb4_unicode_ci
        )
    )
GROUP BY date_hour
ORDER BY date_hour;

-- DTF1 - FLS    
WITH 
target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
slh_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode, 
        index_num, 
        created_at
    FROM fplatform.scan_label_history
    WHERE created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    LEFT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    COUNT(DISTINCT slh.barcode, slh.index_num) AS sum_shirt
FROM slh_filtered slh
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = slh.barcode 
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON ocfd.folder COLLATE utf8mb4_unicode_ci = fm.folder 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
GROUP BY 1
ORDER BY 1;
