-- =========================================
-- Description: Lấy tổng việc team mockup
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
	JOIN target_printers p ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
	WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date' 
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
SELECT * FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_aggregated
) result
WHERE estimate_date = ':estimate_date';

-- DTF2 - Printdash
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
	JOIN target_printers p ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
	WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date' 
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
SELECT * FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_aggregated
) result
WHERE estimate_date = ':estimate_date';
