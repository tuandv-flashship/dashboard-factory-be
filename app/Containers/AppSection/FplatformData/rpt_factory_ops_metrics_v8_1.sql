-- =========================================
-- Description: Lấy tổng việc team in
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS chua_lam,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 0 
      AND s.work_status = 1
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(NAME, 'Machine ', 'May') FROM fplatform.printer_manage WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec
FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';

-- DTF2 - Printdash   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS chua_lam,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 0 
      AND s.work_status = 1
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(NAME, 'Machine ', 'May') FROM fplatform.printer_manage WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec
FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';

-- DTG
-- Số lượng tồn tính theo tỷ lệ năng suất của máy in: Apollo 250 file/h (62,5%), ATLAS_1 75 file/h (18,75%), ATLAS_2 75 file/h (18,75%) 
WITH daily_aggregated AS (
    SELECT 
        d.estimate_folder_date,
        COUNT(*) AS total_file,
        SUM(IF(p.print_status = 0 OR p.print_status IS NULL, 1, 0)) AS unprint_file
    FROM fplatform.dtg_item_detail d
    LEFT JOIN fplatform.dtg_printed_product p 
        ON d.order_code = p.order_code 
        AND d.index_num = p.index_num 
        AND d.distribute_id = p.distribute_id
    WHERE d.estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND d.active = 1 
    GROUP BY d.estimate_folder_date
),
base_data AS (
    SELECT 
        estimate_folder_date,
        total_file + COALESCE(SUM(unprint_file) OVER (
            ORDER BY estimate_folder_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_aggregated
)
    SELECT 
        estimate_folder_date as estimate_date,
        -- Apollo: 62.5%
        ROUND(tong_viec * 0.625) AS tong_viec_apollo,
        -- Atlas 1: 18.75%
        ROUND(tong_viec * 0.1875) AS tong_viec_atlas1,
        tong_viec - ROUND(tong_viec * 0.625) - ROUND(tong_viec * 0.1875) AS tong_viec_atlas2
    FROM base_data
    WHERE estimate_folder_date = ':estimate_date';



-- =========================================
-- Description: Lấy tổng việc team pick
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_product, 0)) AS chua_pick,
        SUM(f.total_product) AS total_product
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 100 
      AND s.work_status = 0
      AND s.copy_job = 0
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(NAME, 'Machine ', 'May') FROM fplatform.printer_manage WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec
FROM (
    SELECT 
        estimate_date,
        total_product + COALESCE(SUM(chua_pick) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';

-- DTF2 - Printdash   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_product, 0)) AS chua_pick,
        SUM(f.total_product) AS total_product
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 100 
      AND s.work_status = 0
      AND s.copy_job = 0
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(NAME, 'Machine ', 'May') FROM fplatform.printer_manage WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec
FROM (
    SELECT 
        estimate_date,
        total_product + COALESCE(SUM(chua_pick) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';

-- DTG
WITH daily_summary AS (
    SELECT 
        f.estimate_folder_date, 
        COUNT(d.folder_key) AS total_shirt,
        SUM(IF(f.done_at IS NULL, 1, 0)) AS chua_pick
    FROM fplatform.dtg_folder_detail f
    INNER JOIN fplatform.dtg_item_detail d ON d.folder_key = f.folder_key
    WHERE f.estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
    GROUP BY f.estimate_folder_date
)
SELECT estimate_folder_date,
    tong_viec
FROM (
    SELECT 
        estimate_folder_date,
        total_shirt + COALESCE(SUM(chua_pick) OVER (
            ORDER BY estimate_folder_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_summary
) result
WHERE estimate_folder_date = ':estimate_date';



-- =========================================
-- Description: Lấy tổng việc team cắt
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS chua_lam,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 2 
      AND s.work_status = 0 
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(NAME, 'Machine ', 'May') FROM fplatform.printer_manage WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec
FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';

-- DTF2 - Printdash   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS chua_lam,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 2 
      AND s.work_status = 0
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(NAME, 'Machine ', 'May') FROM fplatform.printer_manage WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec
FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';



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



-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team in
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    SUM(s.total_file) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 0  
    AND s.work_status = 1
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'PD'
        UNION ALL SELECT 'MayHOTSHOTPD'
        UNION ALL SELECT 'MayREPRINTPD'
    )
GROUP BY date_hour
ORDER BY date_hour;

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    SUM(s.total_file) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 0  
    AND s.work_status = 1
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'FLS'
        UNION ALL SELECT 'MayHOTSHOT'
        UNION ALL SELECT 'MayREPRINT'
    )
GROUP BY date_hour
ORDER BY date_hour;

-- DTG
SELECT 
	LEFT(CONVERT_TZ(printed_at,'+7:00','US/Central'),13) AS date_hour,
	COUNT(CONCAT(product_id,index_num)) as total_file 
FROM fplatform.dtg_printed_product 
WHERE 
    printed_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND printed_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
	-- AND printed_by = 'Apollo'		# printed_by = 'ATLAS_1', printed_by = 'ATLAS_2'
	AND print_status = 1
GROUP BY date_hour;



-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team pick
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'),13) AS date_hour,
    SUM(IF(s.total_product_part IS NULL, s.total_product, s.total_product_part)) AS sum_shirt
FROM fplatform.folder_manage f
JOIN fplatform.user_group_scan s
    ON s.folder_code = f.folder_code
    AND s.work_type = 100  
    AND s.work_status = 0
JOIN (
    SELECT REPLACE(name,'Machine ','May') AS printer 
    FROM fplatform.printer_manage 
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
) p
ON p.printer = IFNULL(printer_share, IFNULL(f.printer_run, f.printer_default))
WHERE s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
	AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour
ORDER BY date_hour;

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at,'+7:00','US/Central'),13) AS date_hour,
    SUM(s.total_product_part) AS sum_shirt
FROM fplatform.folder_manage f
JOIN fplatform.user_group_scan s
    ON s.folder_code = f.folder_code
    AND s.work_type = 100 
    AND s.work_status = 0
JOIN (
    SELECT REPLACE(name,'Machine ','May') AS printer
    FROM fplatform.printer_manage
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
) p
ON p.printer = IFNULL(printer_share, IFNULL(f.printer_run, f.printer_default))
WHERE s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
	AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour
ORDER BY date_hour;

-- DTG
SELECT 
    LEFT(CONVERT_TZ(s.done_at,'+7:00','US/Central'),13) AS date_hour,
    COUNT(f.order_code) AS sum_shirt
FROM fplatform.dtg_item_detail f
JOIN fplatform.dtg_folder_detail s
    ON s.folder_key = f.folder_key
WHERE 
    s.done_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.done_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour
ORDER BY date_hour;



-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team căt
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'),13) AS date_hour,
    SUM(s.total_file) AS sum_file
FROM fplatform.folder_manage f
JOIN fplatform.user_group_scan s
    ON s.folder_code = f.folder_code
    AND s.work_type = 2
    AND s.work_status = 0
JOIN (
    SELECT REPLACE(name,'Machine ','May') AS printer 
    FROM fplatform.printer_manage 
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
) p
ON p.printer = IFNULL(printer_share, IFNULL(f.printer_run, f.printer_default))
WHERE s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
	AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour
ORDER BY date_hour;

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at,'+7:00','US/Central'),13) AS date_hour,
    SUM(s.total_file) AS sum_file
FROM fplatform.folder_manage f
JOIN fplatform.user_group_scan s
    ON s.folder_code = f.folder_code
    AND s.work_type = 2
    AND s.work_status = 0
JOIN (
    SELECT REPLACE(name,'Machine ','May') AS printer
    FROM fplatform.printer_manage
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
) p
ON p.printer = IFNULL(printer_share, IFNULL(f.printer_run, f.printer_default))
WHERE s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
	AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour
ORDER BY date_hour;



-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team mockup
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
),
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed, 
        index_number, 
        LEFT(CONVERT_TZ(created, '+7:00', 'US/Central'), 13) as hour_us
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    lcm.hour_us AS date_hour,
    COUNT(DISTINCT lcm.barcode_fixed, ocfd.file_name_index_number, ocfd.file_name_side) AS total_file
FROM lcm_filtered lcm
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed 
    AND ocfd.file_name_index_number = lcm.index_number
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
GROUP BY 1
ORDER BY 1;

-- DTF1 - FLS   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed, 
        index_number, 
        LEFT(CONVERT_TZ(created, '+7:00', 'US/Central'), 13) as hour_us
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    lcm.hour_us AS date_hour,
    COUNT(DISTINCT lcm.barcode_fixed, ocfd.file_name_index_number, ocfd.file_name_side) AS total_file
FROM lcm_filtered lcm
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed 
    AND ocfd.file_name_index_number = lcm.index_number
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
GROUP BY 1
ORDER BY 1;



-- =========================================
-- Description: Lấy hiệu suất theo từng giờ của team pack & ship áo
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- PD   
SELECT 
    DATE_FORMAT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), '%Y-%m-%d %H') AS date_hour,
    COUNT(DISTINCT slh.barcode, slh.index_num) AS sum_shirt
FROM fplatform.scan_label_history slh
LEFT JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = slh.barcode COLLATE utf8mb4_0900_ai_ci 
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
LEFT JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
WHERE 
    slh.created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
    AND slh.created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
    AND (
        COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IS NULL
        OR COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) NOT IN (
            SELECT REPLACE(NAME, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci 
            FROM fplatform.printer_manage 
            WHERE factory = 'FLS'
            UNION ALL SELECT 'MayHOTSHOT' COLLATE utf8mb4_unicode_ci
            UNION ALL SELECT 'MayREPRINT' COLLATE utf8mb4_unicode_ci
        )
    )
GROUP BY date_hour
ORDER BY date_hour;

-- DTF1 - FLS    
WITH 
target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
slh_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode, 
        index_num, 
        created_at
    FROM fplatform.scan_label_history
    WHERE created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    LEFT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    COUNT(DISTINCT slh.barcode, slh.index_num) AS sum_shirt
FROM slh_filtered slh
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = slh.barcode 
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON ocfd.folder COLLATE utf8mb4_unicode_ci = fm.folder 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
GROUP BY 1
ORDER BY 1;



-- =========================================
-- Description: Lấy tổng số nhân viên theo từng giờ của team in, cắt, pick theo line DTF - PD, DTF -FLS
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+07:00', 'US/Central'), 13) AS date_hour,
    COUNT(DISTINCT s.user_id) AS num_staff
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f 
    ON s.folder_code = f.folder_code
JOIN (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer
    FROM fplatform.printer_manage
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
) p 
    ON p.printer = COALESCE(f.printer_share, f.printer_run, f.printer_default)
WHERE s.work_type = 0			--Work_type = 0 (in), work_type = 2 (cắt), work_type = 100 (pick)
    AND s.created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
    AND s.created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
    AND f.status_folder <> 2
GROUP BY 1
ORDER BY 1;

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+07:00', 'US/Central'), 13) AS date_hour,
    COUNT(DISTINCT s.user_id) AS num_staff
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f 
    ON s.folder_code = f.folder_code
JOIN (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer
    FROM fplatform.printer_manage
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
) p 
    ON p.printer = COALESCE(f.printer_share, f.printer_run, f.printer_default)
WHERE s.work_type = 0			--work_type = 0 (in), work_type = 2 (cắt), work_type = 100 (pick)
    AND f.status_folder <> 2
    AND s.created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
    AND s.created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
GROUP BY 1
ORDER BY 1;



-- =========================================
-- Description: Lấy số lượng nhân viên theo từng giờ của team mockup
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF1 - FLS   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed,
        user_id, 
        created
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
),
ocfd_prepared AS (
    SELECT 
        file_name_order_code,
        folder COLLATE utf8mb4_unicode_ci AS folder_fixed
    FROM fplatform.order_check_file_dropbox
    WHERE folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
    AND status <> 2
)
SELECT
    LEFT(CONVERT_TZ(lcm.created, '+07:00', 'US/Central'), 13) AS hour,
    COUNT(DISTINCT lcm.user_id) AS sum_staff
FROM lcm_filtered lcm
JOIN ocfd_prepared ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder_fixed
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
WHERE fm.status_folder <> 2
GROUP BY 1
ORDER BY 1;

-- DTF2 - Printdash   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
),
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed,
        user_id, 
        created
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
),
ocfd_prepared AS (
    SELECT 
        file_name_order_code,
        folder COLLATE utf8mb4_unicode_ci AS folder_fixed
    FROM fplatform.order_check_file_dropbox
    WHERE folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
    AND status <> 2
)
SELECT
    LEFT(CONVERT_TZ(lcm.created, '+07:00', 'US/Central'), 13) AS hour,
    COUNT(DISTINCT lcm.user_id) AS sum_staff
FROM lcm_filtered lcm
JOIN ocfd_prepared ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder_fixed
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
WHERE fm.status_folder <> 2
GROUP BY 1
ORDER BY 1;



-- =========================================
-- Description: Lấy số nhân viên theo từng giờ của team pack & ship áo
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF1 - FLS   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
slh_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed,
        user_id, 
        created_at
    FROM fplatform.scan_label_history
WHERE created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
      AND created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
),
ocfd_prepared AS (
    SELECT 
        file_name_order_code,
        folder COLLATE utf8mb4_unicode_ci AS folder_fixed
    FROM fplatform.order_check_file_dropbox
    WHERE folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
    AND status <> 2
)
SELECT 
    LEFT(CONVERT_TZ(slh.created_at, '+07:00', 'US/Central'), 13) AS hour,
    COUNT(DISTINCT slh.user_id) AS sum_staff
FROM slh_filtered slh
JOIN ocfd_prepared ocfd 
    ON ocfd.file_name_order_code = slh.barcode_fixed
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder_fixed
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
WHERE fm.status_folder <> 2
GROUP BY 1
ORDER BY 1;

-- PD 
WITH fls_printers AS (
    SELECT REPLACE(NAME, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci AS machine_name 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION SELECT 'MayHOTSHOT' COLLATE utf8mb4_unicode_ci
    UNION SELECT 'MayREPRINT' COLLATE utf8mb4_unicode_ci
),
slh_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode, 
        user_id, 
        created_at
    FROM fplatform.scan_label_history
    WHERE created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    LEFT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    COUNT(DISTINCT slh.user_id) AS num_staff
FROM slh_filtered slh
LEFT JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = slh.barcode AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
LEFT JOIN fplatform.folder_manage fm 
    ON ocfd.folder COLLATE utf8mb4_unicode_ci = fm.folder 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
LEFT JOIN fls_printers fls
    ON COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) COLLATE utf8mb4_unicode_ci = fls.machine_name
WHERE 
    fls.machine_name IS NULL 
GROUP BY 1
ORDER BY 1;



-- =========================================
-- Description: Lấy số nhân viên từng giờ của team pick áo DTG
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

SELECT 
    LEFT(CONVERT_TZ(s.done_at,'+7:00','US/Central'),13) AS date_hour,
    COUNT(distinct s.done_by) AS total_staff
FROM fplatform.dtg_item_detail f
JOIN fplatform.dtg_folder_detail s
    ON s.folder_key = f.folder_key
WHERE 
    s.done_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.done_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY 1
ORDER BY 1;



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


-- =========================================
-- Description: Lấy hiệu suất máy in theo từng giờ team in
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    COALESCE(f.printer_share, f.printer_run, f.printer_default) AS machine,
    SUM(s.total_file) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 0  
    AND s.work_status = 1
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'PD'
        UNION ALL SELECT 'MayHOTSHOTPD'
        UNION ALL SELECT 'MayREPRINTPD'
    )
GROUP BY date_hour, machine
ORDER BY date_hour, machine;

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    COALESCE(f.printer_share, f.printer_run, f.printer_default) AS machine,
    SUM(s.total_file) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 0  
    AND s.work_status = 1
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'FLS'
        UNION ALL SELECT 'MayHOTSHOT'
        UNION ALL SELECT 'MayREPRINT'
    )
GROUP BY date_hour, machine
ORDER BY date_hour, machine;

-- DTG
SELECT 
	LEFT(CONVERT_TZ(printed_at,'+7:00','US/Central'),13) AS date_hour,
    printed_by,
	COUNT(CONCAT(product_id,index_num)) as total_file 
FROM fplatform.dtg_printed_product 
WHERE 
    printed_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND printed_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
	AND print_status = 1
GROUP BY date_hour, printed_by
ORDER BY date_hour, printed_by;


-- =========================================
-- Description: Lấy hiệu suất nhân viên theo từng giờ của team pick
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    u.username,
    SUM(IF(s.total_product_part IS NULL, s.total_product, s.total_product_part)) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 100  
    AND s.work_status = 0
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'PD'
        UNION ALL SELECT 'MayHOTSHOTPD'
        UNION ALL SELECT 'MayREPRINTPD'
    )
GROUP BY date_hour, username
ORDER BY date_hour, username;

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    u.username,
    SUM(IF(s.total_product_part IS NULL, s.total_product, s.total_product_part)) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 100  
    AND s.work_status = 0
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'FLS'
        UNION ALL SELECT 'MayHOTSHOT'
        UNION ALL SELECT 'MayREPRINT'
    )
GROUP BY date_hour, username
ORDER BY date_hour, username;

-- DTG
SELECT 
    LEFT(CONVERT_TZ(s.done_at,'+7:00','US/Central'),13) AS date_hour,
    s.done_user_name,
    COUNT(f.order_code) AS sum_shirt
FROM fplatform.dtg_item_detail f
JOIN fplatform.dtg_folder_detail s
    ON s.folder_key = f.folder_key
WHERE 
    s.done_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.done_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY date_hour, done_user_name
ORDER BY date_hour, done_user_name;



-- =========================================
-- Description: Lấy hiệu suất nhân viên theo từng giờ của team cắt
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    u.username,
    SUM(s.total_file) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 2  
    AND s.work_status = 0
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'PD'
        UNION ALL SELECT 'MayHOTSHOTPD'
        UNION ALL SELECT 'MayREPRINTPD'
    )
GROUP BY date_hour, username
ORDER BY date_hour, username;

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    u.username,
    SUM(s.total_file) AS sum_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f
    ON s.folder_code = f.folder_code
JOIN fplatform.user u
    ON s.user_id = u.id  
WHERE s.work_type = 2
    AND s.work_status = 0
    AND s.created_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.created_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
        SELECT REPLACE(name, 'Machine ', 'May') 
        FROM fplatform.printer_manage 
        WHERE factory = 'FLS'
        UNION ALL SELECT 'MayHOTSHOT'
        UNION ALL SELECT 'MayREPRINT'
    )
GROUP BY date_hour, username
ORDER BY date_hour, username;



-- =========================================
-- Description: Lấy hiệu suất nhân viên theo từng giờ của team mockup
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF2 - Printdash   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
),
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed, 
        index_number,
        user_id,
        LEFT(CONVERT_TZ(created, '+7:00', 'US/Central'), 13) as hour_us
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    lcm.hour_us AS date_hour,
    u.username,
    COUNT(DISTINCT lcm.barcode_fixed, ocfd.file_name_index_number, ocfd.file_name_side) AS total_file
FROM lcm_filtered lcm
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed 
    AND ocfd.file_name_index_number = lcm.index_number
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
JOIN fplatform.user u 
    ON u.id = lcm.user_id
GROUP BY 1,2
ORDER BY 1,2;

-- DTF1 - FLS   
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
lcm_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed, 
        index_number,
        user_id,
        LEFT(CONVERT_TZ(created, '+7:00', 'US/Central'), 13) as hour_us
    FROM fplatform.log_check_mockup
    WHERE created >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    lcm.hour_us AS date_hour,
    u.username,
    COUNT(DISTINCT lcm.barcode_fixed, ocfd.file_name_index_number, ocfd.file_name_side) AS total_file
FROM lcm_filtered lcm
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = lcm.barcode_fixed 
    AND ocfd.file_name_index_number = lcm.index_number
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
JOIN fplatform.user u 
    ON u.id = lcm.user_id
GROUP BY 1,2
ORDER BY 1,2;



-- =========================================
-- Description: Lấy hiệu suất nhân viên theo từng giờ của team pack & ship áo
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- PD   
WITH fls_printers AS (
    SELECT REPLACE(NAME, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci AS machine_name 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION SELECT 'MayHOTSHOT' COLLATE utf8mb4_unicode_ci
    UNION SELECT 'MayREPRINT' COLLATE utf8mb4_unicode_ci
),
slh_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode, 
        index_num, 
        user_id,
        created_at
    FROM fplatform.scan_label_history
    WHERE created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    LEFT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    u.username,
    COUNT(DISTINCT slh.barcode, slh.index_num) AS sum_shirt
FROM slh_filtered slh
LEFT JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = slh.barcode AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
LEFT JOIN fplatform.folder_manage fm 
    ON ocfd.folder COLLATE utf8mb4_unicode_ci = fm.folder 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
LEFT JOIN fls_printers fls
    ON COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) COLLATE utf8mb4_unicode_ci = fls.machine_name
JOIN fplatform.user u 
    ON u.id = slh.user_id
WHERE 
    fls.machine_name IS NULL 
GROUP BY 1,2
ORDER BY 1,2;

-- DTF1 - FLS    
WITH 
target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id 
    FROM fplatform.printer_manage 
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
slh_filtered AS (
    SELECT 
        barcode COLLATE utf8mb4_0900_ai_ci AS barcode, 
        index_num, 
        user_id,
        created_at
    FROM fplatform.scan_label_history
    WHERE created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+7:00')
      AND created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+7:00')
)
SELECT 
    LEFT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
    u.username,
    COUNT(DISTINCT slh.barcode, slh.index_num) AS sum_shirt
FROM slh_filtered slh
JOIN fplatform.order_check_file_dropbox ocfd 
    ON ocfd.file_name_order_code = slh.barcode 
    AND ocfd.status <> 2
    AND ocfd.folder_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN fplatform.folder_manage fm 
    ON ocfd.folder COLLATE utf8mb4_unicode_ci = fm.folder 
    AND fm.status_folder <> 2 
    AND fm.estimate_date BETWEEN DATE(':start_shift') - INTERVAL 20 DAY AND DATE(':start_shift')
JOIN target_printers p 
    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
JOIN fplatform.user u 
    ON u.id = slh.user_id
GROUP BY 1,2
ORDER BY 1,2;



-- =========================================
-- Description: Lấy số file hotshot (team in)
-- =========================================
-- Parameters:
-- :estimate_date (date) - thời gian bắt đầu ca làm

-- DTF1 - FLS
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS chua_lam,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 0 
      AND s.work_status = 1
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND f.printer_default = 'MayHOTSHOT'
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec, tong_viec - con_lai as da_lam
FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';

-- DTF2 - Printdash   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS chua_lam,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 0 
      AND s.work_status = 1
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND f.printer_default = 'MayHOTSHOTPD'
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec, tong_viec - con_lai as da_lam
FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';



-- =========================================
-- Description: Lấy số áo hotshot (team pick)
-- =========================================
-- Parameters:
-- :estimate_date (date) - thời gian bắt đầu ca làm

-- DTF1 - FLS
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_product, 0)) AS chua_lam,
        SUM(f.total_product) AS total_product
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 100 
      AND s.work_status = 0
      AND s.copy_job = 0
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND f.printer_default = 'MayHOTSHOT'
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec, tong_viec - con_lai as da_lam
FROM (
    SELECT 
        estimate_date,
        total_product + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';

-- DTF2 - Printdash   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_product, 0)) AS chua_lam,
        SUM(f.total_product) AS total_product
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 100 
      AND s.work_status = 0
      AND s.copy_job = 0
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND f.printer_default = 'MayHOTSHOTPD'
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec, tong_viec - con_lai as da_lam
FROM (
    SELECT 
        estimate_date,
        total_product + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';



-- =========================================
-- Description: Lấy số file hotshot (team cắt)
-- =========================================
-- Parameters:
-- :estimate_date (date) - thời gian bắt đầu ca làm

-- DTF1 - FLS
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS chua_lam,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 2 
      AND s.work_status = 0
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND f.printer_default = 'MayHOTSHOT'
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec, tong_viec - con_lai as da_lam
FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';

-- DTF2 - Printdash   
WITH daily_stats AS (
    SELECT 
        f.estimate_date,
        SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS chua_lam,
        SUM(f.total_file) AS total_file
    FROM fplatform.folder_manage f
    LEFT JOIN fplatform.user_group_scan s
      ON f.folder_code = s.folder_code 
      AND s.work_type = 2 
      AND s.work_status = 0
    WHERE f.estimate_date BETWEEN  ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND f.printer_default = 'MayHOTSHOTPD'
    GROUP BY f.estimate_date
)
SELECT estimate_date,
    tong_viec, tong_viec - con_lai as da_lam
FROM (
    SELECT 
        estimate_date,
        total_file + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_stats
) c
WHERE estimate_date = ':estimate_date';



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


-- =========================================
-- Description: Lấy số áo hotshot (team pack & ship)
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
SELECT tong_viec, tong_viec - con_lai as da_lam FROM (
	SELECT 
        estimate_date,
        total_shirt + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_aggregated
) result
WHERE estimate_date = ':estimate_date';

-- DTF - Printdash
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
        d.file_name_index_number
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
SELECT tong_viec, tong_viec - con_lai as da_lam FROM (
	SELECT 
        estimate_date,
        total_shirt + COALESCE(SUM(chua_lam) OVER (
            ORDER BY estimate_date 
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ), 0) AS tong_viec,
        SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
    FROM daily_aggregated
) result
WHERE estimate_date = ':estimate_date';