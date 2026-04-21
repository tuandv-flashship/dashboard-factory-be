-- ============================================================
-- @file    : 09_hieu_suat_gio_team_cat.sql
-- @version : v1.0.0
-- @updated : 2026-04-21
-- @desc    : Lấy hiệu suất theo từng giờ của team cắt (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
-- ============================================================

-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team căt
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'),13) AS date_hour,
    SUM(s.total_file) AS sum_file
FROM fplatform.folder_manage f
JOIN fplatform.user_group_scan s
    ON s.folder_code = f.folder_code
    AND s.work_type = 2
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
    SUM(s.total_file) AS sum_file
FROM fplatform.folder_manage f
JOIN fplatform.user_group_scan s
    ON s.folder_code = f.folder_code
    AND s.work_type = 2
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
