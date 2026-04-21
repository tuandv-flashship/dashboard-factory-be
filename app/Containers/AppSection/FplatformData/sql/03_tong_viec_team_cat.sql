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
