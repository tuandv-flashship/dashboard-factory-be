-- =========================================
-- Description: Lấy tổng việc theo đơn
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH target_items AS (
    SELECT 
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        d.file_name_index_number,
		case when f.printer_default = 'MayHOTSHOT' AND f.folder like '%DON UU TIEN_DON GUI LAI%' then 'DON UU TIEN GUI LAI'
        when f.printer_default = 'MayHOTSHOT' AND f.folder like '%DON GUI LAI%' then 'DON GUI LAI'
        when f.printer_default = 'MayREPRINT' then 'IN LAI'
        else 'IN' end folder_status
    FROM fplatform.folder_manage f
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE f.estimate_date BETWEEN ':estimate' - INTERVAL 10 DAY AND ':estimate'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May') 
          FROM fplatform.printer_manage 
          WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, d.file_name_index_number
)
, order_status as (
	SELECT t.*, o.id
    FROM target_items t
    JOIN orders o ON o.order_code = t.file_name_order_code 
	AND o.created BETWEEN CONVERT_TZ(':estimate 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate 23:59:59', 'US/Central', '+7:00')
	AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, item_status AS (
SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.folder_status,
    DATE(CONVERT_TZ(r.first_get_label_at, '+7:00', 'US/Central')) AS first_get,
	DATE(CONVERT_TZ(r.last_get_label_at, '+7:00', 'US/Central')) AS last_get
    FROM order_status fg
	left JOIN report.report_orders r on r.id = fg.id
)
SELECT 
    ':estimate' AS estimate_date,
COUNT(DISTINCT CASE 
        WHEN folder_status <> 'DON GUI LAI' 
             AND (last_get IS NULL OR last_get >= ':estimate') 
        THEN file_name_order_code 
    END) AS tong_don,
COUNT(DISTINCT CASE 
        WHEN last_get = ':estimate' OR (estimate_date = ':estimate' AND folder_status = 'DON GUI LAI' )
        THEN file_name_order_code 
    END) AS da_lam
FROM item_status;


-- DTF2 - PD
WITH target_items AS (
    SELECT 
        f.estimate_date,
        f.folder,
        f.printer_default,
        d.file_name_order_code,
        d.file_name_index_number,
		case when f.printer_default = 'MayHOTSHOTPD' AND f.folder like '%DON UU TIEN_DON GUI LAI%' then 'DON UU TIEN GUI LAI'
        when f.printer_default = 'MayHOTSHOTPD' AND f.folder like '%DON GUI LAI%' then 'DON GUI LAI'
        when f.printer_default = 'MayREPRINTPD' then 'IN LAI'
        else 'IN' end folder_status
    FROM fplatform.folder_manage f
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May') 
          FROM fplatform.printer_manage 
          WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
    GROUP BY f.estimate_date, f.folder, f.printer_default, d.file_name_order_code, d.file_name_index_number
)
, order_status as (
	SELECT t.*, o.id
    FROM target_items t
    JOIN orders o ON o.order_code = t.file_name_order_code 
	AND o.created BETWEEN CONVERT_TZ(':estimate 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate 23:59:59', 'US/Central', '+7:00')
	AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, item_status AS (
SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.folder_status,
    DATE(CONVERT_TZ(r.first_get_label_at, '+7:00', 'US/Central')) AS first_get,
	DATE(CONVERT_TZ(r.last_get_label_at, '+7:00', 'US/Central')) AS last_get
    FROM order_status fg
	left JOIN report.report_orders r on r.id = fg.id
)
SELECT 
    ':estimate' AS estimate_date,
COUNT(DISTINCT CASE 
        WHEN folder_status <> 'DON GUI LAI' 
             AND (last_get IS NULL OR last_get >= ':estimate') 
        THEN file_name_order_code 
    END) AS tong_don,
COUNT(DISTINCT CASE 
        WHEN last_get = ':estimate' OR (estimate_date = ':estimate' AND folder_status = 'DON GUI LAI' )
        THEN file_name_order_code 
    END) AS da_lam
FROM item_status;



-- DTG - PD
WITH target_items AS (
    SELECT 
        estimate_folder_date AS estimate_date, 
        folder_key AS folder, 
        IF(folder_key LIKE 'REPRINT%', 'REPRINT', 'IN') AS folder_status,
        order_code AS file_name_order_code, 
        index_num AS file_name_index_number
    FROM fplatform.dtg_item_detail
    WHERE estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
    AND active = 1
    GROUP BY estimate_folder_date, folder_key, order_code, index_num 
)
, order_status as (
	SELECT t.*, o.id
    FROM target_items t
    JOIN orders o ON o.order_code = t.file_name_order_code 
	AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
	AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, item_status AS (
SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.folder_status,
    DATE(CONVERT_TZ(r.first_get_label_at, '+7:00', 'US/Central')) AS first_get,
	DATE(CONVERT_TZ(r.last_get_label_at, '+7:00', 'US/Central')) AS last_get
    FROM order_status fg
	LEFT JOIN report.report_orders r on r.id = fg.id
)
SELECT 
    ':estimate' AS estimate_date,
COUNT(DISTINCT CASE 
        WHEN last_get IS NULL OR last_get >= ':estimate'
        THEN file_name_order_code 
    END) AS tong_don,
COUNT(DISTINCT CASE 
        WHEN last_get = ':estimate'
        THEN file_name_order_code 
    END) AS da_lam
FROM item_status;