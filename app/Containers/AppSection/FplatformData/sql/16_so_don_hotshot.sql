-- =========================================
-- Description: Lấy số đơn hotshot
-- =========================================
-- Parameters:
-- :estimate_date (DAT) - Ngày estimate

-- DTF1 - FLS
WITH target_folders_dtf AS (
    SELECT 
		fm.folder COLLATE utf8mb4_unicode_ci as folder,
        fm.created_at AS mark_time,
        fm.estimate_date,
        d.file_name_order_code COLLATE utf8mb4_unicode_ci AS file_name_order_code,
        d.file_name_index_number,
        CASE WHEN printer_default = 'MayHOTSHOT' AND fm.folder LIKE '%DON UU TIEN_DON GUI LAI%' THEN 'DON UU TIEN GUI LAI'
        WHEN printer_default = 'MayHOTSHOT' AND fm.folder LIKE '%DON GUI LAI%' THEN 'DON GUI LAI'
        ELSE 'IN' END AS status_folder
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2 
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 9 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOT'
    GROUP BY fm.folder, fm.created_at,fm.estimate_date, d.file_name_order_code, d.file_name_index_number
)
, order_status AS (
    SELECT tf.*, o.id
    FROM target_folders_dtf tf
    JOIN fplatform.orders o ON o.order_code = tf.file_name_order_code 
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY 
                          AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, item_scan_status AS (
    SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.status_folder,
    MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) as firsr_scan
    FROM order_status fg
	LEFT JOIN fplatform.scan_label_history s ON s.created_at >= fg.mark_time
    AND s.order_id = fg.id AND s.index_num = fg.file_name_index_number
    group by 1,2,3,4,5
)
, order_summary AS (
    SELECT 
        file_name_order_code,
        MAX(estimate_date) as estimate_date,
        COUNT(*) AS total_items,                
        COUNT(firsr_scan) AS scanned_items,     
        MAX(DATE(firsr_scan)) AS last_scan_date, 
        MIN(DATE(firsr_scan)) AS first_scan_date
    FROM item_scan_status
    WHERE status_folder <> 'DON GUI LAI'
    GROUP BY file_name_order_code
)
SELECT 
    ':estimate_date' AS estimate_date,
    COUNT(DISTINCT CASE 
        WHEN first_scan_date IS NULL OR last_scan_date >= ':estimate_date' THEN file_name_order_code 
    END) AS tong_don,
    COUNT(DISTINCT CASE 
        WHEN total_items = scanned_items AND last_scan_date = ':estimate_date' THEN file_name_order_code 
    END) AS da_lam
FROM order_summary;


-- DTF2 - PD
WITH target_folders_dtf AS (
    SELECT 
		fm.folder COLLATE utf8mb4_unicode_ci as folder,
        fm.created_at AS mark_time,
        fm.estimate_date,
        d.file_name_order_code COLLATE utf8mb4_unicode_ci AS file_name_order_code,
        d.file_name_index_number,
        CASE WHEN printer_default = 'MayHOTSHOTPD' AND fm.folder LIKE '%DON UU TIEN_DON GUI LAI%' THEN 'DON UU TIEN GUI LAI'
        WHEN printer_default = 'MayHOTSHOTPD' AND fm.folder LIKE '%DON GUI LAI%' THEN 'DON GUI LAI'
        ELSE 'IN' END AS status_folder
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2 
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 9 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOTPD'
    GROUP BY fm.folder, fm.created_at,fm.estimate_date, d.file_name_order_code, d.file_name_index_number
)
, order_status AS (
    SELECT tf.*, o.id
    FROM target_folders_dtf tf
    JOIN fplatform.orders o ON o.order_code = tf.file_name_order_code 
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY 
                          AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, item_scan_status AS (
    SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.status_folder,
    MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) as firsr_scan
    FROM order_status fg
	LEFT JOIN fplatform.scan_label_history s ON s.created_at >= fg.mark_time
    AND s.order_id = fg.id AND s.index_num = fg.file_name_index_number
    group by 1,2,3,4,5
)
, order_summary AS (
    SELECT 
        file_name_order_code,
        MAX(estimate_date) as estimate_date,
        COUNT(*) AS total_items,                
        COUNT(firsr_scan) AS scanned_items,     
        MAX(DATE(firsr_scan)) AS last_scan_date, 
        MIN(DATE(firsr_scan)) AS first_scan_date
    FROM item_scan_status
    WHERE status_folder <> 'DON GUI LAI'
    GROUP BY file_name_order_code
)
SELECT 
    ':estimate_date' AS estimate_date,
    COUNT(DISTINCT CASE 
        WHEN first_scan_date IS NULL OR last_scan_date >= ':estimate_date' THEN file_name_order_code 
    END) AS tong_don,
    COUNT(DISTINCT CASE 
        WHEN total_items = scanned_items AND last_scan_date = ':estimate_date' THEN file_name_order_code 
    END) AS da_lam
FROM order_summary;