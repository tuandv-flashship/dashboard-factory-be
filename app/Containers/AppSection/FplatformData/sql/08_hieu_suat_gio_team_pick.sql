-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team pick
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'),13) AS date_hour,
    SUM(IF(s.total_product_part IS NULL, s.total_product, s.total_product_part)) AS sum_shirt
FROM fplatform.folder_manage f
JOIN fplatform.user_group_scan s
    ON s.folder_code = f.folder_code
    AND s.work_type = 100  
    AND s.work_status = 0
JOIN (
    SELECT REPLACE(name,'Machine ','May') AS printer 
    FROM fplatform.printer_manage 
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
) p
ON p.printer = IFNULL(printer_share, IFNULL(f.printer_run, f.printer_default))
WHERE s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
	AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour
ORDER BY date_hour;

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at,'+7:00','US/Central'),13) AS date_hour,
    SUM(s.total_product_part) AS sum_shirt
FROM fplatform.folder_manage f
JOIN fplatform.user_group_scan s
    ON s.folder_code = f.folder_code
    AND s.work_type = 100 
    AND s.work_status = 0
JOIN (
    SELECT REPLACE(name,'Machine ','May') AS printer
    FROM fplatform.printer_manage
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
) p
ON p.printer = IFNULL(printer_share, IFNULL(f.printer_run, f.printer_default))
WHERE s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
	AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour
ORDER BY date_hour;

-- DTG
SELECT 
    LEFT(CONVERT_TZ(s.done_at,'+7:00','US/Central'),13) AS date_hour,
    COUNT(f.order_code) AS sum_shirt
FROM fplatform.dtg_item_detail f
JOIN fplatform.dtg_folder_detail s
    ON s.folder_key = f.folder_key
WHERE 
    s.done_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.done_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour
ORDER BY date_hour;
