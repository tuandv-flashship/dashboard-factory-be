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
