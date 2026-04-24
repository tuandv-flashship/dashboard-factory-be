-- ============================================================
-- @file    : 01_tong_viec_team_in.sql
-- @version : v2.0.0
-- @updated : 2026-04-24
-- @desc    : Lấy tổng việc team in (DTF1-FLS, DTF2-PD, DTG)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Replace sliding window → flat IF(created_at IS NULL OR ...) logic
--   v2.0.0 (2026-04-24) - JOIN order_check_file_dropbox for file-level granularity,
--                          add order_status CTE filtering orders table,
--                          work_status 0→1, DTG joins orders by order_id
-- ============================================================

-- =========================================
-- Description: Lấy tổng việc team in
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

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
      AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May')
          FROM fplatform.printer_manage
          WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
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
SUM(IF(s.created_at IS NULL OR s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), o.num_file, 0)) AS tong_viec
FROM order_status o
LEFT JOIN fplatform.user_group_scan s ON s.folder = o.folder
    AND s.work_type = 0
    AND s.work_status = 1;


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
      AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May')
          FROM fplatform.printer_manage
          WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
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
SUM(IF(s.created_at IS NULL OR s.created_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), o.num_file, 0)) AS tong_viec
FROM order_status o
LEFT JOIN fplatform.user_group_scan s ON s.folder = o.folder
    AND s.work_type = 0
    AND s.work_status = 1;


-- DTG
WITH item_detail AS (
    SELECT
        d.estimate_folder_date,
        d.folder_key,
        d.order_code,
        d.distribute_id,
        d.index_num
    FROM fplatform.dtg_item_detail d
    JOIN fplatform.orders o ON o.id = d.order_id
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
    WHERE d.estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
        AND d.active = 1
    GROUP BY d.estimate_folder_date, d.folder_key, d.order_code, d.distribute_id, d.index_num
)
SELECT
    ':estimate_date' AS estimate_date,
    SUM(IF(p.printed_at IS NULL OR p.printed_at >= CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00'), 1, 0)) AS tong_viec
FROM item_detail i
LEFT JOIN fplatform.dtg_printed_product p
    ON i.order_code = p.order_code
    AND i.index_num = p.index_num
    AND i.distribute_id = p.distribute_id;
