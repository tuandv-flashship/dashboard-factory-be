-- ============================================================
-- @file    : 25_hotshot_file_team_mockup.sql
-- @version : v1.0.0
-- @updated : 2026-04-21
-- @desc    : Lấy số file hotshot - team mockup (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
-- ============================================================

-- =========================================
-- Description: Lấy số file hotshot (team mockup)
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH 
folder_printer AS (
	SELECT fm.estimate_date, fm.folder
	FROM fplatform.folder_manage fm
	WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date' 
    AND fm.printer_default = 'MayHOTSHOT'
	AND fm.status_folder <> 2
),
a AS (
    SELECT 
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        d.file_name_index_number,
        count(*) as num_file
    FROM folder_printer f
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
      AND d.status <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, file_name_index_number
)
, daily_aggregated AS (
    SELECT 
        estimate_date, 
        SUM(IF(created IS NULL, num_file, 0)) AS chua_lam,
        SUM(num_file) AS total_file 
    FROM (
		SELECT a.*, l.created
		FROM a
		LEFT JOIN fplatform.log_check_mockup l 
			ON a.file_name_order_code COLLATE utf8mb4_0900_ai_ci = l.barcode 
				AND created >= ':estimate_date' - INTERVAL 15 DAY 
				AND a.file_name_index_number = l.index_number
        GROUP BY 1,2,3,4
		) b
	GROUP BY estimate_date
)
SELECT tong_viec, tong_viec - con_lai as da_lam FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_aggregated
) result
WHERE estimate_date = ':estimate_date';

-- DTF2 - Printdash
WITH 
folder_printer AS (
	SELECT fm.estimate_date, fm.folder
	FROM fplatform.folder_manage fm
	WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date' 
    AND fm.printer_default = 'MayHOTSHOTPD'
	AND fm.status_folder <> 2
),
a AS (
    SELECT 
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        d.file_name_index_number,
        count(*) as num_file
    FROM folder_printer f
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
      AND d.status <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, file_name_index_number
)
, daily_aggregated AS (
    SELECT 
        estimate_date, 
        SUM(IF(created IS NULL, num_file, 0)) AS chua_lam,
        SUM(num_file) AS total_file 
    FROM (
		SELECT a.*, l.created
		FROM a
		LEFT JOIN fplatform.log_check_mockup l 
			ON a.file_name_order_code COLLATE utf8mb4_0900_ai_ci = l.barcode 
				AND created >= ':estimate_date' - INTERVAL 15 DAY 
				AND a.file_name_index_number = l.index_number
        GROUP BY 1,2,3,4
		) b
	GROUP BY estimate_date
)
SELECT tong_viec, tong_viec - con_lai as da_lam FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_aggregated
) result
WHERE estimate_date = ':estimate_date';
