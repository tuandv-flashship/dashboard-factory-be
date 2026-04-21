-- =========================================
-- Description: Lấy số lượng nhân viên theo từng giờ của team mockup
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
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed,
        user_id, 
        created
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
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
    LEFT(CONVERT_TZ(lcm.created, '+07:00', 'US/Central'), 13) AS hour,
    COUNT(DISTINCT lcm.user_id) AS sum_staff
FROM lcm_filtered lcm
JOIN ocfd_prepared ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder_fixed
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
WHERE fm.status_folder <> 2
GROUP BY 1
ORDER BY 1;

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
        user_id, 
        created
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
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
    LEFT(CONVERT_TZ(lcm.created, '+07:00', 'US/Central'), 13) AS hour,
    COUNT(DISTINCT lcm.user_id) AS sum_staff
FROM lcm_filtered lcm
JOIN ocfd_prepared ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder_fixed
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
WHERE fm.status_folder <> 2
GROUP BY 1
ORDER BY 1;
