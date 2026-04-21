
-- =========================================
-- Description: Lấy tổng việc team pack & ship
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
        file_name_index_number
    FROM fplatform.order_check_file_dropbox d 
    JOIN folder_printer f
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
	AND d.status <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, file_name_index_number
	) c
GROUP BY estimate_date, folder, file_name_order_code, file_name_index_number )
, daily_aggregated AS (
    SELECT 
        estimate_date, 
        SUM(IF(created_at IS NULL, num_shirt, 0)) AS chua_lam,
        SUM(num_shirt) AS total_shirt
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
SELECT * FROM (
	SELECT 
        estimate_date,
        total_shirt + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_aggregated
) result
WHERE estimate_date = ':estimate_date';

-- PD
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci AS printer_id 
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
),
dtf AS (
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
),
dtg AS (
    SELECT estimate_folder_date, folder_key, order_code, index_num, count(*) as num_shirt
    FROM fplatform.dtg_item_detail
    WHERE estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
    AND active = 1
    GROUP BY 1,2,3,4
), 
a AS (
    SELECT 
        estimate_date, 
        folder COLLATE utf8mb4_unicode_ci AS folder, 
        file_name_order_code COLLATE utf8mb4_unicode_ci AS order_code,
        file_name_index_number AS index_num, 
        num_shirt 
    FROM dtf
    UNION ALL
    SELECT 
        estimate_folder_date AS estimate_date, 
        folder_key COLLATE utf8mb4_unicode_ci AS folder, 
        order_code COLLATE utf8mb4_unicode_ci AS order_code, 
        index_num, 
        num_shirt 
    FROM dtg
), 
daily_aggregated AS (
    SELECT 
        estimate_date, 
        SUM(IF(created_at IS NULL, num_shirt, 0)) AS chua_lam,
        SUM(num_shirt) AS total_shirt
    FROM (
		SELECT a.*, l.created_at
		FROM a
		LEFT JOIN fplatform.scan_label_history l 
            ON a.order_code COLLATE utf8mb4_0900_ai_ci = l.barcode 
            AND l.created_at >= ':estimate_date' - INTERVAL 15 DAY 
		    AND a.index_num = l.index_num
        GROUP BY 1,2,3,4
	) b
	GROUP BY estimate_date
)
SELECT * FROM (
	SELECT 
        estimate_date,
        total_shirt + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_aggregated
) result
WHERE estimate_date = ':estimate_date';
