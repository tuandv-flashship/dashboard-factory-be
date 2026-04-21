-- ============================================================
-- @file    : 03_tong_viec_team_cat.sql
-- @version : v1.1.0
-- @updated : 2026-04-21
-- @desc    : Lấy tổng việc team cắt (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Replace sliding window → flat IF(created_at IS NULL OR ...) logic
-- ============================================================

-- =========================================
-- Description: Lấy tổng việc team cắt
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
SELECT
    ':estimate_date' AS estimate_date,
    SUM(IF(s.created_at IS NULL OR s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), f.total_file, 0)) AS tong_viec
FROM fplatform.folder_manage f
LEFT JOIN fplatform.user_group_scan s
    ON f.folder_code = s.folder_code
    AND s.work_type = 2
    AND s.work_status = 0
WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
  AND f.status_folder <> 2
  AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
      SELECT REPLACE(NAME, 'Machine ', 'May')
      FROM fplatform.printer_manage
      WHERE factory = 'FLS'
      UNION ALL SELECT 'MayHOTSHOT'
      UNION ALL SELECT 'MayREPRINT'
  );

-- DTF2 - Printdash
SELECT
    ':estimate_date' AS estimate_date,
    SUM(IF(s.created_at IS NULL OR s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), f.total_file, 0)) AS tong_viec
FROM fplatform.folder_manage f
LEFT JOIN fplatform.user_group_scan s
    ON f.folder_code = s.folder_code
    AND s.work_type = 2
    AND s.work_status = 0
WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
  AND f.status_folder <> 2
  AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
      SELECT REPLACE(NAME, 'Machine ', 'May')
      FROM fplatform.printer_manage
      WHERE factory = 'PD'
      UNION ALL SELECT 'MayHOTSHOTPD'
      UNION ALL SELECT 'MayREPRINTPD'
  );
