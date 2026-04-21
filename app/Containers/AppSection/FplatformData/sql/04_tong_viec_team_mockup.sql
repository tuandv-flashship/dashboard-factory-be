-- ============================================================
-- @file    : 04_tong_viec_team_mockup.sql
-- @version : v1.1.0
-- @updated : 2026-04-21
-- @desc    : Lấy tổng việc team mockup (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Refactor: target_folders/calc_total/file_groups/calc_done CTE; handle HOTSHOT date cutoff
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
        fm.total_file
    FROM fplatform.folder_manage fm
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May')
          FROM fplatform.printer_manage
          WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
),
calc_total AS (
    SELECT SUM(total_file) AS sum_total_file
    FROM target_folders
),
file_groups AS (
    SELECT
        f.estimate_date,
        f.printer_default,
        d.file_name_order_code,
        d.file_name_index_number,
        COUNT(*) AS num_file
    FROM target_folders f
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    GROUP BY f.estimate_date, f.printer_default, d.file_name_order_code, d.file_name_index_number
),
calc_done AS (
    SELECT SUM(num_file) AS sum_done_file
    FROM (
        SELECT fg.num_file
        FROM file_groups fg
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
        fm.total_file
    FROM fplatform.folder_manage fm
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May')
          FROM fplatform.printer_manage
          WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
),
calc_total AS (
    SELECT SUM(total_file) AS sum_total_file
    FROM target_folders
),
file_groups AS (
    SELECT
        f.estimate_date,
        f.printer_default,
        d.file_name_order_code,
        d.file_name_index_number,
        COUNT(*) AS num_file
    FROM target_folders f
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    GROUP BY f.estimate_date, f.printer_default, d.file_name_order_code, d.file_name_index_number
),
calc_done AS (
    SELECT SUM(num_file) AS sum_done_file
    FROM (
        SELECT fg.num_file
        FROM file_groups fg
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
