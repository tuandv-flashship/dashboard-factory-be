-- ============================================================
-- @file    : 24_hotshot_file_team_cat.sql
-- @version : v2.0.0
-- @updated : 2026-04-24
-- @desc    : Lấy số file hotshot - team cắt (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Simplified: flat IF(created_at IS NULL OR ...) logic; add da_lam column
--   v2.0.0 (2026-04-24) - CTE target_folders JOIN order_check_file_dropbox,
--                          add order_status CTE filtering orders table, add copy_job=0
-- ============================================================

-- =========================================
-- Description: Lấy số file hotshot (team cắt)
-- =========================================
-- Parameters:
-- :estimate_date (date) - thời gian bắt đầu ca làm

-- DTF1 - FLS
WITH target_folders AS (
    SELECT
        fm.folder,
        fm.estimate_date,
        d.file_name_order_code,
        d.file_name_index_number,
        COUNT(*) AS num_file
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOT'
    GROUP BY fm.estimate_date, fm.folder, d.file_name_order_code, d.file_name_index_number
),
order_status AS (
    SELECT tf.*
    FROM target_folders tf
    JOIN orders o ON o.order_code = tf.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
SELECT ':estimate_date' AS estimate_date,
SUM(IF(s.created_at IS NULL OR s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), o.num_file, 0)) AS tong_viec,
SUM(IF(s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') AND s.created_at <= CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00'), o.num_file, 0)) AS da_lam
FROM order_status o
LEFT JOIN fplatform.user_group_scan s ON s.folder = o.folder
    AND s.copy_job = 0
    AND s.work_type = 2
    AND s.work_status = 0;


-- DTF2 - Printdash
WITH target_folders AS (
    SELECT
        fm.folder,
        fm.estimate_date,
        d.file_name_order_code,
        d.file_name_index_number,
        COUNT(*) AS num_file
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOTPD'
    GROUP BY fm.estimate_date, fm.folder, d.file_name_order_code, d.file_name_index_number
),
order_status AS (
    SELECT tf.*
    FROM target_folders tf
    JOIN orders o ON o.order_code = tf.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
SELECT ':estimate_date' AS estimate_date,
SUM(IF(s.created_at IS NULL OR s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), o.num_file, 0)) AS tong_viec,
SUM(IF(s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') AND s.created_at <= CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00'), o.num_file, 0)) AS da_lam
FROM order_status o
LEFT JOIN fplatform.user_group_scan s ON s.folder = o.folder
    AND s.copy_job = 0
    AND s.work_type = 2
    AND s.work_status = 0;
