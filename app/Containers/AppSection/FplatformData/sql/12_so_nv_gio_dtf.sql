-- =========================================
-- Description: Lấy tổng số nhân viên theo từng giờ của team in, cắt, pick theo line DTF - PD, DTF -FLS
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

-- DTF1 - FLS
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+07:00', 'US/Central'), 13) AS date_hour,
    COUNT(DISTINCT s.user_id) AS num_staff
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f 
    ON s.folder_code = f.folder_code
JOIN (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer
    FROM fplatform.printer_manage
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
) p 
    ON p.printer = COALESCE(f.printer_share, f.printer_run, f.printer_default)
WHERE s.work_type = 0			--Work_type = 0 (in), work_type = 2 (cắt), work_type = 100 (pick)
    AND s.created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
    AND s.created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
    AND f.status_folder <> 2
GROUP BY 1
ORDER BY 1;

-- DTF2 - Printdash
SELECT 
    LEFT(CONVERT_TZ(s.created_at, '+07:00', 'US/Central'), 13) AS date_hour,
    COUNT(DISTINCT s.user_id) AS num_staff
FROM fplatform.user_group_scan s
JOIN fplatform.folder_manage f 
    ON s.folder_code = f.folder_code
JOIN (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer
    FROM fplatform.printer_manage
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
) p 
    ON p.printer = COALESCE(f.printer_share, f.printer_run, f.printer_default)
WHERE s.work_type = 0			--work_type = 0 (in), work_type = 2 (cắt), work_type = 100 (pick)
    AND f.status_folder <> 2
    AND s.created_at >= CONVERT_TZ(':start_shift', 'US/Central', '+07:00')
    AND s.created_at <  CONVERT_TZ(':end_shift', 'US/Central', '+07:00')
GROUP BY 1
ORDER BY 1;
