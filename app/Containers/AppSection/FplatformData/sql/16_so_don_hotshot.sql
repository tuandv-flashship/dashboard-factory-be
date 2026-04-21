-- ============================================================
-- @file    : 16_so_don_hotshot.sql
-- @version : v1.1.0
-- @updated : 2026-04-21
-- @desc    : Lấy số đơn hotshot (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Refactor: target_items/item_status CTE; output chua_lam+da_lam
-- ============================================================

-- =========================================
-- Description: Lấy số đơn hotshot
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH target_items AS (
    SELECT
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        d.file_name_index_number
    FROM fplatform.folder_manage f
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.printer_default = 'MayHOTSHOT'
      AND f.status_folder <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, d.file_name_index_number
),
item_status AS (
    SELECT
        ti.file_name_order_code,
        MIN(
            CASE
                WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= ti.estimate_date
                THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
            END
        ) AS ngay_lam
    FROM target_items ti
    LEFT JOIN fplatform.scan_label_history s
        ON s.barcode = ti.file_name_order_code COLLATE utf8mb4_0900_ai_ci
        AND s.index_num = ti.file_name_index_number
        AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
    GROUP BY ti.estimate_date, ti.folder, ti.file_name_order_code, ti.file_name_index_number
)
SELECT
    ':estimate_date' AS estimate_date,
    COUNT(DISTINCT IF(ngay_lam IS NULL OR ngay_lam >= ':estimate_date', file_name_order_code, NULL)) AS tong_viec,
    COUNT(DISTINCT IF(ngay_lam = ':estimate_date', file_name_order_code, NULL)) AS da_lam
FROM item_status;


-- DTF2 - PD
WITH target_items AS (
    SELECT
        f.estimate_date,
        f.folder,
        d.file_name_order_code,
        d.file_name_index_number
    FROM fplatform.folder_manage f
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.printer_default = 'MayHOTSHOTPD'
      AND f.status_folder <> 2
    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, d.file_name_index_number
),
item_status AS (
    SELECT
        ti.file_name_order_code,
        MIN(
            CASE
                WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= ti.estimate_date
                THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
            END
        ) AS ngay_lam
    FROM target_items ti
    LEFT JOIN fplatform.scan_label_history s
        ON s.barcode = ti.file_name_order_code COLLATE utf8mb4_0900_ai_ci
        AND s.index_num = ti.file_name_index_number
        AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
    GROUP BY ti.estimate_date, ti.folder, ti.file_name_order_code, ti.file_name_index_number
)
SELECT
    ':estimate_date' AS estimate_date,
    COUNT(DISTINCT IF(ngay_lam IS NULL OR ngay_lam >= ':estimate_date', file_name_order_code, NULL)) AS tong_viec,
    COUNT(DISTINCT IF(ngay_lam = ':estimate_date', file_name_order_code, NULL)) AS da_lam
FROM item_status;
