-- =========================================
-- Description: Lấy số áo hotshot (team pack & ship)
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
		case when fm.printer_default = 'MayHOTSHOT' AND fm.folder like '%DON UU TIEN_DON GUI LAI%' then 'DON UU TIEN GUI LAI'
        when fm.printer_default = 'MayHOTSHOT' AND fm.folder like '%DON GUI LAI%' then 'DON GUI LAI'
        else 'IN' end folder_status
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2 
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOT'
 GROUP BY fm.estimate_date, fm.folder, d.file_name_order_code, d.file_name_index_number
)
, order_status as (
SELECT tf.*, o.id
FROM target_folders tf
JOIN orders o ON o.order_code = tf.file_name_order_code 
	AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
	AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, total_per_date AS (
    SELECT estimate_date, COUNT(*) AS total_product
    FROM order_status
    GROUP BY estimate_date
)
, item_status AS (
SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.folder_status,
    DATE(CONVERT_TZ(r.first_get_label_at, '+7:00', 'US/Central')) AS first_get,
	DATE(CONVERT_TZ(r.last_get_label_at, '+7:00', 'US/Central')) AS last_get
    FROM order_status fg
	left JOIN report.report_orders r on r.id = fg.id
)
SELECT 
    ':estimate_date' AS estimate_date,
SUM(CASE 
        WHEN last_get IS NULL OR last_get >= ':estimate_date'
        THEN 1
    END) AS tong_viec,
SUM(CASE 
        WHEN last_get = ':estimate_date' 
        THEN 1
    END) AS da_lam
FROM item_status
where folder_status <> 'DON GUI LAI';


-- DTF2 - PD
WITH target_folders AS (
    SELECT 
        fm.folder, 
        fm.estimate_date,
        d.file_name_order_code,
        d.file_name_index_number,
		case when fm.printer_default = 'MayHOTSHOTPD' AND fm.folder like '%DON UU TIEN_DON GUI LAI%' then 'DON UU TIEN GUI LAI'
        when fm.printer_default = 'MayHOTSHOTPD' AND fm.folder like '%DON GUI LAI%' then 'DON GUI LAI'
        else 'IN' end folder_status
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2 
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOTPD'
 GROUP BY fm.estimate_date, fm.folder, d.file_name_order_code, d.file_name_index_number
)
, order_status as (
SELECT tf.*, o.id
FROM target_folders tf
JOIN orders o ON o.order_code = tf.file_name_order_code 
	AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
	AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, total_per_date AS (
    SELECT estimate_date, COUNT(*) AS total_product
    FROM order_status
    GROUP BY estimate_date
)
, item_status AS (
SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.folder_status,
    DATE(CONVERT_TZ(r.first_get_label_at, '+7:00', 'US/Central')) AS first_get,
	DATE(CONVERT_TZ(r.last_get_label_at, '+7:00', 'US/Central')) AS last_get
    FROM order_status fg
	left JOIN report.report_orders r on r.id = fg.id
)
SELECT 
    ':estimate_date' AS estimate_date,
SUM(CASE 
        WHEN last_get IS NULL OR last_get >= ':estimate_date'
        THEN 1
    END) AS tong_viec,
SUM(CASE 
        WHEN last_get = ':estimate_date' 
        THEN 1
    END) AS da_lam
FROM item_status
where folder_status <> 'DON GUI LAI';