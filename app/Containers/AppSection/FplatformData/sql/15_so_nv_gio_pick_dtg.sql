-- =========================================
-- Description: Lấy số nhân viên từng giờ của team pick áo DTG
-- =========================================
-- Parameters:
-- :start_shift (datetime) - thời gian bắt đầu ca làm
-- :end_shift (datetime) - thời gian kết thúc ca làm

SELECT 
    LEFT(CONVERT_TZ(s.done_at,'+7:00','US/Central'),13) AS date_hour,
    COUNT(distinct s.done_by) AS total_staff
FROM fplatform.dtg_item_detail f
JOIN fplatform.dtg_folder_detail s
    ON s.folder_key = f.folder_key
WHERE 
    s.done_at >= CONVERT_TZ(':start_shift','US/Central','+7:00')
    AND s.done_at <  CONVERT_TZ(':end_shift','US/Central','+7:00')
GROUP BY 1
ORDER BY 1;
