-- ============================================================
-- @file    : 22_hotshot_file_team_in.sql
-- @version : v1.0.0
-- @updated : 2026-04-21
-- @desc    : Lấy số file hotshot - team in (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
-- ============================================================

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
