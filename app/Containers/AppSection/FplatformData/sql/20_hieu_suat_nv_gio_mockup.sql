-- =========================================
-- Description: Lấy hiệu suất nhân viên theo từng giờ của team mockup
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
),
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed, 
        index_number,
        user_id,
        LEFT(CONVERT_TZ(created, '+7:00', 'US/Central'), 13) as hour_us
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    lcm.hour_us AS date_hour,
    u.username,
    COUNT(DISTINCT lcm.barcode_fixed, ocfd.file_name_index_number, ocfd.file_name_side) AS total_file
FROM lcm_filtered lcm
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed 
    AND ocfd.file_name_index_number = lcm.index_number
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
JOIN fplatform.user u 
    ON u.id = lcm.user_id
GROUP BY 1,2
ORDER BY 1,2;

-- DTF1 - FLS   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed, 
        index_number,
        user_id,
        LEFT(CONVERT_TZ(created, '+7:00', 'US/Central'), 13) as hour_us
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    lcm.hour_us AS date_hour,
    u.username,
    COUNT(DISTINCT lcm.barcode_fixed, ocfd.file_name_index_number, ocfd.file_name_side) AS total_file
FROM lcm_filtered lcm
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed 
    AND ocfd.file_name_index_number = lcm.index_number
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
JOIN fplatform.user u 
    ON u.id = lcm.user_id
GROUP BY 1,2
ORDER BY 1,2;
