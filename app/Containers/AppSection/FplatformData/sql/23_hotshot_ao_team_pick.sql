-- ============================================================
-- @file    : 23_hotshot_ao_team_pick.sql
-- @version : v1.1.0
-- @updated : 2026-04-21
-- @desc    : Lấy số áo hotshot - team pick (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Simplified: flat IF(created_at IS NULL OR ...) logic; add da_lam column
-- ============================================================

-- =========================================
-- Description: Lấy số áo hotshot (team pick)
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
SELECT
    ':estimate_date' AS estimate_date,
    SUM(IF(s.created_at IS NULL OR s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), f.total_product, 0)) AS tong_viec,
    SUM(IF(s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') AND s.created_at <= CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00'), f.total_product, 0)) AS da_lam
FROM fplatform.folder_manage f
LEFT JOIN fplatform.user_group_scan s
    ON f.folder_code = s.folder_code
    AND s.work_type = 100
    AND s.work_status = 0
WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
  AND f.status_folder <> 2
  AND f.printer_default = 'MayHOTSHOT';

-- DTF2 - Printdash
SELECT
    ':estimate_date' AS estimate_date,
    SUM(IF(s.created_at IS NULL OR s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), f.total_product, 0)) AS tong_viec,
    SUM(IF(s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') AND s.created_at <= CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00'), f.total_product, 0)) AS da_lam
FROM fplatform.folder_manage f
LEFT JOIN fplatform.user_group_scan s
    ON f.folder_code = s.folder_code
    AND s.work_type = 100
    AND s.work_status = 0
WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
  AND f.status_folder <> 2
  AND f.printer_default = 'MayHOTSHOTPD';
