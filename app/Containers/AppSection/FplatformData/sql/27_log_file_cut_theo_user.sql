-- =========================================
-- Description: Lấy log thời gian file CUT theo user
-- =========================================
-- Parameters:
-- :estimate_date (DAT) - Ngày estimate

-- DTF1 - Flashship
SELECT u.username, CONVERT_TZ(s.created_at, '+7:00', 'US/Central') as created_at, s.total_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage fm on s.folder = fm.folder
JOIN fplatform.user u on u.id = s.user_id
WHERE s.created_at  BETWEEN CONVERT_TZ('start_log', 'US/Central', '+7:00') AND CONVERT_TZ('end_log', 'US/Central', '+7:00')
      AND fm.status_folder <> 2
      AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May') 
          FROM fplatform.printer_manage 
          WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
AND work_status = 0 AND work_type = 2;

-- DTF2 - Printdash
SELECT u.username, CONVERT_TZ(s.created_at, '+7:00', 'US/Central') as created_at, s.total_file
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage fm on s.folder = fm.folder
JOIN fplatform.user u on u.id = s.user_id
WHERE s.created_at  BETWEEN CONVERT_TZ('2026-05-12 07:00:00', 'US/Central', '+7:00') AND CONVERT_TZ('2026-05-12 07:30:00', 'US/Central', '+7:00')
      AND fm.status_folder <> 2
      AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May') 
          FROM fplatform.printer_manage 
          WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
AND work_status = 0 AND work_type = 2;