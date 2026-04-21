-- ============================================================
-- @file    : 06_tong_don_theo_don.sql
-- @version : v1.0.0
-- @updated : 2026-04-21
-- @desc    : Lấy tổng việc & đã làm theo đơn (DTF1-FLS, DTF2-PD, DTG)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
-- ============================================================

-- =========================================
-- Description: Lấy tổng việc & đã làm theo đơn
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH 
target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
folder_printer AS (
	SELECT fm.estimate_date, fm.folder
	FROM fplatform.folder_manage fm
	JOIN target_printers p 
		ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
	WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
		AND fm.status_folder <> 2
)
, a AS (
SELECT *, COUNT(*) AS num_shirt FROM (
    SELECT 
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        d.file_name_index_number
    FROM fplatform.order_check_file_dropbox d 
    JOIN folder_printer f
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
	AND d.status <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, file_name_index_number
	) c
GROUP BY estimate_date, folder, file_name_order_code, file_name_index_number 
)
, daily_aggregated AS (
    SELECT 
        estimate_date, 
        COUNT(DISTINCT IF(created_at IS NULL,file_name_order_code, null)) AS chua_lam,
        COUNT(DISTINCT file_name_order_code) AS total_order
    FROM (
		SELECT a.*, l.created_at
		FROM a
		LEFT JOIN fplatform.scan_label_history l ON a.file_name_order_code COLLATE utf8mb4_0900_ai_ci = l.barcode 
        AND l.created_at >= ':estimate_date'  - INTERVAL 15 DAY 
		AND a.file_name_index_number = l.index_num
        GROUP BY 1,2,3,4
		) b
	GROUP BY estimate_date
)
SELECT tong_don, tong_don - con_lai as da_lam  FROM (
	SELECT 
        estimate_date,
        total_order + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_don,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_aggregated
) final_result
WHERE estimate_date = ':estimate_date';

-- DTF2 - PD
WITH 
target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
),
folder_printer AS (
	SELECT fm.estimate_date, fm.folder
	FROM fplatform.folder_manage fm
	JOIN target_printers p 
		ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
	WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
		AND fm.status_folder <> 2
)
, a AS (
SELECT *, COUNT(*) AS num_shirt FROM (
    SELECT 
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        file_name_index_number
    FROM fplatform.order_check_file_dropbox d 
    JOIN folder_printer f
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
	AND d.status <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, file_name_index_number
	) c
GROUP BY estimate_date, folder, file_name_order_code, file_name_index_number 
)
, daily_aggregated AS (
    SELECT 
        estimate_date, 
        COUNT(DISTINCT IF(created_at IS NULL,file_name_order_code, null)) AS chua_lam,
        COUNT(DISTINCT file_name_order_code) AS total_order
    FROM (
		SELECT a.*, l.created_at
		FROM a
		LEFT JOIN fplatform.scan_label_history l ON a.file_name_order_code COLLATE utf8mb4_0900_ai_ci = l.barcode 
        AND l.created_at >= ':estimate_date'  - INTERVAL 15 DAY 
		AND a.file_name_index_number = l.index_num
        GROUP BY 1,2,3,4
		) b
	GROUP BY estimate_date
)
SELECT tong_don, tong_don - con_lai as da_lam FROM (
	SELECT 
        estimate_date,
        total_order + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_don,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_aggregated
) final_result
WHERE estimate_date = ':estimate_date';

-- DTG - PD
WITH detail_item AS (
	SELECT 
		estimate_folder_date AS estimate_date, 
        order_code,index_num 
	FROM fplatform.dtg_item_detail 
	WHERE folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
		AND active = 1
)
, daily_aggregated AS (
    SELECT 
        estimate_date, 
        COUNT(DISTINCT IF(created_at IS NULL,order_code, null)) AS chua_lam,
        COUNT(DISTINCT order_code) AS total_order
    FROM (
		SELECT a.*, l.created_at
		FROM detail_item a
		LEFT JOIN fplatform.scan_label_history l ON a.order_code = l.barcode 
        AND l.created_at >= ':estimate_date'  - INTERVAL 15 DAY 
		AND a.index_num = l.index_num
        GROUP BY 1,2,3,4
		) b
	GROUP BY estimate_date
)
SELECT * FROM (
	SELECT 
        estimate_date,
        total_order + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS da_lam
    FROM daily_aggregated
) final_result
WHERE estimate_date = ':estimate_date';
