-- ============================================================
-- @file    : 04_tong_viec_team_mockup.sql
-- @version : v2.0.0
-- @updated : 2026-04-24
-- @desc    : Lấy tổng việc team mockup (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Refactor: target_folders/calc_total/file_groups/calc_done CTE; handle HOTSHOT date cutoff
--   v2.0.0 (2026-04-24) - target_folders JOIN order_check_file_dropbox from start,
--                          add order_status CTE filtering orders table,
--                          calc_total from order_status SUM(num_file)
-- ============================================================

-- =========================================
-- Description: Lấy tổng việc team mockup
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH target_folders AS (
    SELECT
        fm.folder,
        fm.estimate_date,
        fm.printer_default,
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
    GROUP BY fm.estimate_date, fm.folder, fm.printer_default, d.file_name_order_code, d.file_name_index_number
),
order_status AS (
    SELECT tf.*
    FROM target_folders tf
    JOIN orders o ON o.order_code = tf.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
),
calc_total AS (
    SELECT SUM(num_file) AS sum_total_file
    FROM order_status
),
calc_done AS (
    SELECT SUM(num_file) AS sum_done_file
    FROM (
        SELECT fg.num_file
        FROM order_status fg
        LEFT JOIN fplatform.log_check_mockup l
            ON l.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
            AND l.index_number = fg.file_name_index_number
            AND l.created >= ':estimate_date' - INTERVAL 15 DAY
        GROUP BY fg.estimate_date, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number, fg.num_file
        HAVING
            CASE
                WHEN fg.printer_default IN ('MayHOTSHOT', 'MayREPRINT') THEN
                    MIN(CASE WHEN DATE(CONVERT_TZ(l.created, '+7:00', 'US/Central')) >= fg.estimate_date
                             THEN DATE(CONVERT_TZ(l.created, '+7:00', 'US/Central')) END)
                ELSE DATE(MIN(CONVERT_TZ(l.created, '+7:00', 'US/Central')))
            END < ':estimate_date'
    ) done_groups
)
SELECT
    ':estimate_date' AS estimate_date,
    COALESCE((SELECT sum_total_file FROM calc_total), 0) - COALESCE((SELECT sum_done_file FROM calc_done), 0) AS tong_viec;


-- DTF2 - Printdash
WITH target_folders AS (
    SELECT
        fm.folder,
        fm.estimate_date,
        fm.printer_default,
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
    GROUP BY fm.estimate_date, fm.folder, fm.printer_default, d.file_name_order_code, d.file_name_index_number
),
order_status AS (
    SELECT tf.*
    FROM target_folders tf
    JOIN orders o ON o.order_code = tf.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
),
calc_total AS (
    SELECT SUM(num_file) AS sum_total_file
    FROM order_status
),
calc_done AS (
    SELECT SUM(num_file) AS sum_done_file
    FROM (
        SELECT fg.num_file
        FROM order_status fg
        LEFT JOIN fplatform.log_check_mockup l
            ON l.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
            AND l.index_number = fg.file_name_index_number
            AND l.created >= ':estimate_date' - INTERVAL 15 DAY
        GROUP BY fg.estimate_date, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number, fg.num_file
        HAVING
            CASE
                WHEN fg.printer_default IN ('MayHOTSHOTPD', 'MayREPRINTPD') THEN
                    MIN(CASE WHEN DATE(CONVERT_TZ(l.created, '+7:00', 'US/Central')) >= fg.estimate_date
                             THEN DATE(CONVERT_TZ(l.created, '+7:00', 'US/Central')) END)
                ELSE DATE(MIN(CONVERT_TZ(l.created, '+7:00', 'US/Central')))
            END < ':estimate_date'
    ) done_groups
)
SELECT
    ':estimate_date' AS estimate_date,
    COALESCE((SELECT sum_total_file FROM calc_total), 0) - COALESCE((SELECT sum_done_file FROM calc_done), 0) AS tong_viec;
