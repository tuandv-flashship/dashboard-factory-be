-- =========================================
-- Description: Lấy số file hotshot (team mockup)
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - Flasship
WITH target_folders AS (
    SELECT 
        fm.folder, 
        fm.estimate_date,
        fm.created_at AS mark_time,
        d.file_name_order_code,
        d.file_name_index_number,
        COUNT(*) AS num_file
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2 
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 9 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOT'
    GROUP BY fm.estimate_date, fm.created_at, fm.folder, d.file_name_order_code, d.file_name_index_number
)
, order_status AS (
    SELECT tf.*, o.id
    FROM target_folders tf
    JOIN fplatform.orders o ON o.order_code = tf.file_name_order_code 
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY 
                          AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, item_scan_status AS (
    SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number,num_file,
    MIN(CONVERT_TZ(s.created, '+7:00', 'US/Central')) as firsr_scan
    FROM order_status fg
	LEFT JOIN fplatform.log_check_mockup s ON s.created >= fg.mark_time
    AND s.order_id = fg.id AND s.index_number = fg.file_name_index_number
    GROUP BY fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number,num_file
)
SELECT 
    ':estimate_date' AS estimate_date,
SUM(CASE 
        WHEN date(firsr_scan) IS NULL OR date(firsr_scan) >= ':estimate_date'
        THEN num_file
    END) AS tong_viec,
SUM(CASE 
        WHEN date(firsr_scan) = ':estimate_date' 
        THEN num_file
    END) AS da_lam
FROM item_scan_status
;


-- DTF2 - Printdash
WITH target_folders AS (
    SELECT 
        fm.folder, 
        fm.estimate_date,
        fm.created_at AS mark_time,
        d.file_name_order_code,
        d.file_name_index_number,
        COUNT(*) AS num_file
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d 
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2 
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 9 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOTPD'
    GROUP BY fm.estimate_date, fm.created_at, fm.folder, d.file_name_order_code, d.file_name_index_number
)
, order_status AS (
    SELECT tf.*, o.id
    FROM target_folders tf
    JOIN fplatform.orders o ON o.order_code = tf.file_name_order_code 
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY 
                          AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
)
, item_scan_status AS (
    SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number,num_file,
    MIN(CONVERT_TZ(s.created, '+7:00', 'US/Central')) as firsr_scan
    FROM order_status fg
	LEFT JOIN fplatform.log_check_mockup s ON s.created >= fg.mark_time
    AND s.order_id = fg.id AND s.index_number = fg.file_name_index_number
    GROUP BY fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number,num_file
)
SELECT 
    ':estimate_date' AS estimate_date,
SUM(CASE 
        WHEN date(firsr_scan) IS NULL OR date(firsr_scan) >= ':estimate_date'
        THEN num_file
    END) AS tong_viec,
SUM(CASE 
        WHEN date(firsr_scan) = ':estimate_date' 
        THEN num_file
    END) AS da_lam
FROM item_scan_status
;