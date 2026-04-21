-- ============================================================
-- @file    : 07_hieu_suat_gio_team_in.sql
-- @version : v1.0.0
-- @updated : 2026-04-21
-- @desc    : Lấy hiệu suất theo từng giờ của team in (DTF1-FLS, DTF2-PD, DTG)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
-- ============================================================

-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team in
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    SUM(s.total_file) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 0  
    AND s.work_status = 1
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'PD'
        UNION ALL SELECT 'MayHOTSHOTPD'
        UNION ALL SELECT 'MayREPRINTPD'
    )
GROUP BY date_hour
ORDER BY date_hour;

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    SUM(s.total_file) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 0  
    AND s.work_status = 1
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'FLS'
        UNION ALL SELECT 'MayHOTSHOT'
        UNION ALL SELECT 'MayREPRINT'
    )
GROUP BY date_hour
ORDER BY date_hour;

-- DTG
SELECT 
	LEFT(CONVERT_TZ(printed_at,'+7:00','US/Central'),13) AS date_hour,
	COUNT(CONCAT(product_id,index_num)) as total_file 
FROM fplatform.dtg_printed_product 
WHERE 
    printed_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND printed_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
	-- AND printed_by = 'Apollo'		# printed_by = 'ATLAS_1', printed_by = 'ATLAS_2'
	AND print_status = 1
GROUP BY date_hour;
