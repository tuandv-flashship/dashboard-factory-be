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
