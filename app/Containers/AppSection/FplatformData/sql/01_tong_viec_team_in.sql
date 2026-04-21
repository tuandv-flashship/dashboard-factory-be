-- ============================================================
-- @file    : 01_tong_viec_team_in.sql
-- @version : v1.1.0
-- @updated : 2026-04-21
-- @desc    : Lấy tổng việc team in (DTF1-FLS, DTF2-PD, DTG)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Replace sliding window → flat IF(created_at IS NULL OR ...) logic
-- ============================================================

-- =========================================
-- Description: Lấy tổng việc team in
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
    AND s.work_type = 0
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
    AND s.work_type = 0
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

-- DTG
SELECT
    ':estimate_date' AS estimate_date,
    SUM(IF(p.print_status = 1 AND p.printed_at < CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), 0, 1)) AS tong_viec
FROM fplatform.dtg_item_detail d
LEFT JOIN fplatform.dtg_printed_product p
    ON d.order_code = p.order_code
    AND d.index_num = p.index_num
    AND d.distribute_id = p.distribute_id
WHERE d.estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
  AND d.active = 1;
