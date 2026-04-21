-- ============================================================
-- @file    : 16_so_don_hotshot.sql
-- @version : v1.0.0
-- @updated : 2026-04-21
-- @desc    : Lấy số đơn hotshot (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
-- ============================================================

-- =========================================
-- Description: Lấy số đơn hotshot
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF1 - FLS
WITH 
folder_printer AS (
	SELECT fm.estimate_date, fm.folder
	FROM fplatform.folder_manage fm
	WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
		AND fm.printer_default = 'MayHOTSHOT'
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

-- DTF2 - PD
WITH 
folder_printer AS (
	SELECT fm.estimate_date, fm.folder
	FROM fplatform.folder_manage fm
	WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
		AND fm.printer_default = 'MayHOTSHOTPD'
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
