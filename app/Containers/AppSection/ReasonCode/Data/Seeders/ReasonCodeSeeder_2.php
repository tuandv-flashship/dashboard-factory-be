<?php

namespace App\Containers\AppSection\ReasonCode\Data\Seeders;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Ship\Parents\Seeders\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds reason_sub_items and reason_errors from the official Google Sheets spec.
 *
 * Structure: Category → SubItems (Đối Tượng / Bước 2) → Errors (Lỗi Cụ Thể / Bước 3)
 * Departments: print, cut, mockup, pack_ship, pick
 *
 * ⚠ TRUNCATES reason_sub_items and reason_errors before seeding.
 *    reason_categories are kept intact (must exist before running this seeder).
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\ReasonCode\Data\Seeders\ReasonCodeSeeder_2"
 */
final class ReasonCodeSeeder_2 extends Seeder
{
    public function run(): void
    {
        // Ensure categories exist (gracefully skip if ReasonCodeSeeder_1 hasn't run)
        $machine  = ReasonCategory::where('code', 'machine')->first();
        $human    = ReasonCategory::where('code', 'human')->first();
        $material = ReasonCategory::where('code', 'material')->first();
        $process  = ReasonCategory::where('code', 'process')->first();

        if (! $machine || ! $human || ! $material || ! $process) {
            return; // Categories not seeded yet — skip gracefully
        }

        // Truncate dependent tables (errors first, then sub_items due to possible FK)
        $isMysql = DB::getDriverName() === 'mysql';
        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
        ReasonError::truncate();
        ReasonSubItem::truncate();
        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // ═══════════════════════════════════════════════════════════════════
        // PRINT (scope_dept = 'print')
        // ═══════════════════════════════════════════════════════════════════
        $this->seedDept(
            dept: 'print',
            machine: $machine,
            human: $human,
            material: $material,
            process: $process,
            subItems: [
                // [category_var_key, code_suffix, label]
                ['machine',  'may-in-dtf',         'Máy in DTF'],
                ['machine',  'may-say',             'Máy sấy'],
                ['human',    'operator',            'Operator máy in'],
                ['human',    'nv-qc',               'NV kiểm tra chất lượng'],
                ['human',    'nv-phu-tro',          'Nhân viên phụ trợ'],
                ['material', 'muc-in',              'Mực in (Ink)'],
                ['material', 'film-dtf',            'Film DTF'],
                ['material', 'bot-keo',             'Bột keo (Adhesive powder)'],
                ['process',  'chuan-bi-file',       'Chuẩn bị file in'],
                ['process',  'cai-dat-may',         'Cài đặt máy in'],
                ['process',  'kiem-soat-moi-truong','Kiểm soát môi trường'],
                ['process',  'cho-doi',             'Chờ đợi / Phụ thuộc'],
            ],
            errors: [
                // [sub_item_code_suffix, error_label]
                ['may-in-dtf',          'Tắc đầu phun (clogged printhead)'],
                ['may-in-dtf',          'Lệch đầu phun (printhead misalignment)'],
                ['may-in-dtf',          'Lỗi mực trắng (white ink issue)'],
                ['may-in-dtf',          'Mực bị nhòe / smudging'],
                ['may-in-dtf',          'Màu sai / lệch màu (color mismatch)'],
                ['may-in-dtf',          'Hình bị mờ / blur'],
                ['may-in-dtf',          'Lỗi phần mềm RIP'],
                ['may-in-dtf',          'Máy báo lỗi / error code'],
                ['may-in-dtf',          'Máy dừng đột ngột / treo'],
                ['may-in-dtf',          'Bảo trì định kỳ'],
                ['may-in-dtf',          'Khác'],
                ['may-say',             'Nhiệt độ không đạt / không ổn định'],
                ['may-say',             'Bột keo rải không đều'],
                ['may-say',             'Băng tải bị kẹt'],
                ['may-say',             'Máy kẹt film'],
                ['may-say',             'Máy dừng đột ngột / treo'],
                ['may-say',             'Bảo trì định kỳ'],
                ['may-say',             'Khác'],
                ['operator',            'Vắng mặt / nghỉ phép'],
                ['operator',            'Đi trễ'],
                ['operator',            'Thao tác sai quy trình'],
                ['operator',            'Thiếu kinh nghiệm / chưa được đào tạo'],
                ['operator',            'Làm chậm / năng suất thấp'],
                ['operator',            'Khác'],
                ['nv-qc',               'Vắng mặt / nghỉ phép'],
                ['nv-qc',               'Kiểm tra sai / bỏ sót lỗi'],
                ['nv-qc',               'Khác'],
                ['nv-phu-tro',          'Vắng mặt / nghỉ phép'],
                ['nv-phu-tro',          'Chuẩn bị file chậm'],
                ['nv-phu-tro',          'Khác'],
                ['muc-in',              'Hết mực'],
                ['muc-in',              'Mực kém chất lượng / hết hạn'],
                ['muc-in',              'Mực bị đông / kết tủa'],
                ['muc-in',              'Sai loại mực'],
                ['muc-in',              'Khác'],
                ['film-dtf',            'Hết film'],
                ['film-dtf',            'Film kém chất lượng'],
                ['film-dtf',            'Film bị ẩm / cong'],
                ['film-dtf',            'Sai kích thước film'],
                ['film-dtf',            'Khác'],
                ['bot-keo',             'Hết bột keo'],
                ['bot-keo',             'Bột keo kém chất lượng'],
                ['bot-keo',             'Bột keo bị ẩm / vón cục'],
                ['bot-keo',             'Sai loại bột keo'],
                ['bot-keo',             'Khác'],
                ['chuan-bi-file',       'File thiết kế sai / lỗi'],
                ['chuan-bi-file',       'File chưa được duyệt'],
                ['chuan-bi-file',       'Sai kích thước / resolution'],
                ['chuan-bi-file',       'Thiếu file'],
                ['chuan-bi-file',       'Khác'],
                ['cai-dat-may',         'Sai profile màu (ICC profile)'],
                ['cai-dat-may',         'Sai cài đặt RIP'],
                ['cai-dat-may',         'Sai chế độ in (tốc độ / chất lượng)'],
                ['cai-dat-may',         'Khác'],
                ['kiem-soat-moi-truong','Độ ẩm quá cao / thấp'],
                ['kiem-soat-moi-truong','Nhiệt độ phòng không đạt'],
                ['kiem-soat-moi-truong','Bụi / ô nhiễm'],
                ['kiem-soat-moi-truong','Khác'],
                ['cho-doi',             'Chờ file từ bộ phận khác'],
                ['cho-doi',             'Chờ nguyên vật liệu'],
                ['cho-doi',             'Chờ sửa máy'],
                ['cho-doi',             'Chờ xác nhận từ quản lý'],
                ['cho-doi',             'Hết việc'],
                ['cho-doi',             'Khác'],
            ],
            categoryMap: compact('machine', 'human', 'material', 'process'),
        );

        // ═══════════════════════════════════════════════════════════════════
        // CUT (scope_dept = 'cut')
        // ═══════════════════════════════════════════════════════════════════
        $this->seedDept(
            dept: 'cut',
            machine: $machine,
            human: $human,
            material: $material,
            process: $process,
            subItems: [
                ['human',    'nv-cat',            'Nhân viên cắt'],
                ['material', 'film-da-in',        'Film đã in'],
                ['material', 'keo',               'Kéo'],
                ['process',  'thao-tac-cat',      'Thao tác cắt'],
                ['process',  'cho-doi',           'Chờ đợi / Phụ thuộc'],
                ['process',  'sap-xep-phan-loai', 'Sắp xếp / Phân loại'],
            ],
            errors: [
                ['nv-cat',            'Vắng mặt / nghỉ phép'],
                ['nv-cat',            'Đi trễ'],
                ['nv-cat',            'Cắt sai vị trí / lệch đường cắt'],
                ['nv-cat',            'Cắt rách / hỏng film'],
                ['nv-cat',            'Cắt nhầm đơn hàng'],
                ['nv-cat',            'Làm chậm / năng suất thấp'],
                ['nv-cat',            'Thiếu kinh nghiệm / chưa được đào tạo'],
                ['nv-cat',            'Mệt mỏi / mất tập trung'],
                ['nv-cat',            'Khác'],
                ['film-da-in',        'Film bị lỗi từ bộ phận Print'],
                ['film-da-in',        'Film bị nhăn / cong'],
                ['film-da-in',        'Film bị ẩm'],
                ['film-da-in',        'Khác'],
                ['keo',               'Kéo cùn / hỏng'],
                ['keo',               'Hết kéo thay thế'],
                ['keo',               'Khác'],
                ['thao-tac-cat',      'Cắt sai theo mẫu'],
                ['thao-tac-cat',      'Không theo đúng thứ tự đơn hàng'],
                ['thao-tac-cat',      'Khác'],
                ['cho-doi',           'Chờ film từ bộ phận Print'],
                ['cho-doi',           'Chờ kéo / dụng cụ'],
                ['cho-doi',           'Hết việc'],
                ['cho-doi',           'Khác'],
                ['sap-xep-phan-loai', 'Sắp xếp sai thứ tự đơn hàng'],
                ['sap-xep-phan-loai', 'Nhầm lẫn đơn hàng'],
                ['sap-xep-phan-loai', 'Khác'],
            ],
            categoryMap: compact('machine', 'human', 'material', 'process'),
        );

        // ═══════════════════════════════════════════════════════════════════
        // MOCK UP (scope_dept = 'mockup')
        // ═══════════════════════════════════════════════════════════════════
        $this->seedDept(
            dept: 'mockup',
            machine: $machine,
            human: $human,
            material: $material,
            process: $process,
            subItems: [
                ['machine',  'may-tinh',        'Máy tính'],
                ['machine',  'may-scan',         'Máy scan'],
                ['human',    'nv-mockup',        'Nhân viên Mock Up'],
                ['material', 'film-da-cat',      'Film đã cắt'],
                ['material', 'ao-garment',       'Áo (Garment)'],
                ['material', 'bang-keo-dung-cu', 'Băng keo / Dụng cụ cố định'],
                ['process',  'scan-tra-cuu',     'Scan & tra cứu'],
                ['process',  'dan-film',         'Dán film'],
                ['process',  'cho-doi',          'Chờ đợi / Phụ thuộc'],
            ],
            errors: [
                ['may-tinh',        'Máy tính bị treo / chậm'],
                ['may-tinh',        'Phần mềm bị lỗi / crash'],
                ['may-tinh',        'Màn hình hiển thị sai màu'],
                ['may-tinh',        'Mất kết nối mạng / hệ thống'],
                ['may-tinh',        'Khác'],
                ['may-scan',        'Scan không nhận'],
                ['may-scan',        'Scan sai mã / barcode'],
                ['may-scan',        'Lỗi kết nối'],
                ['may-scan',        'Hết pin / sạc'],
                ['may-scan',        'Khác'],
                ['nv-mockup',       'Vắng mặt / nghỉ phép'],
                ['nv-mockup',       'Đi trễ'],
                ['nv-mockup',       'Dán film sai vị trí'],
                ['nv-mockup',       'Dán film bị lệch / nghiêng'],
                ['nv-mockup',       'Dán nhầm film (sai đơn hàng)'],
                ['nv-mockup',       'Dán film lên sai áo (sai size / màu)'],
                ['nv-mockup',       'Làm rách / hỏng film khi dán'],
                ['nv-mockup',       'Làm chậm / năng suất thấp'],
                ['nv-mockup',       'Thiếu kinh nghiệm / chưa được đào tạo'],
                ['nv-mockup',       'Mệt mỏi / mất tập trung'],
                ['nv-mockup',       'Khác'],
                ['film-da-cat',     'Film bị lỗi từ bước trước (in / cắt)'],
                ['film-da-cat',     'Film bị rách / hỏng'],
                ['film-da-cat',     'Film bị cong / nhăn'],
                ['film-da-cat',     'Khác'],
                ['ao-garment',      'Áo sai size / màu'],
                ['ao-garment',      'Áo bị bẩn / lỗi vải'],
                ['ao-garment',      'Áo bị nhăn'],
                ['ao-garment',      'Hết áo / thiếu hàng'],
                ['ao-garment',      'Khác'],
                ['bang-keo-dung-cu','Hết băng keo'],
                ['bang-keo-dung-cu','Băng keo kém chất lượng'],
                ['bang-keo-dung-cu','Khác'],
                ['scan-tra-cuu',    'Scan sai mã đơn hàng'],
                ['scan-tra-cuu',    'Hệ thống không hiển thị mock up'],
                ['scan-tra-cuu',    'Mock up trên hệ thống sai / chưa cập nhật'],
                ['scan-tra-cuu',    'Khác'],
                ['dan-film',        'Dán sai vị trí so với mock up'],
                ['dan-film',        'Film không cố định được (bị tuột)'],
                ['dan-film',        'Khác'],
                ['cho-doi',         'Chờ film từ bộ phận Cut'],
                ['cho-doi',         'Chờ áo từ bộ phận Pick'],
                ['cho-doi',         'Chờ hệ thống / phần mềm'],
                ['cho-doi',         'Chờ xác nhận từ quản lý'],
                ['cho-doi',         'Hết việc'],
                ['cho-doi',         'Khác'],
            ],
            categoryMap: compact('machine', 'human', 'material', 'process'),
        );

        // ═══════════════════════════════════════════════════════════════════
        // PACK & SHIP (scope_dept = 'pack_ship')
        // ═══════════════════════════════════════════════════════════════════
        $this->seedDept(
            dept: 'pack_ship',
            machine: $machine,
            human: $human,
            material: $material,
            process: $process,
            subItems: [
                ['machine',  'may-in-nhan',     'Máy in nhãn / label'],
                ['machine',  'may-dong-goi',    'Máy đóng gói / seal'],
                ['machine',  'may-scan',        'Máy scan / barcode'],
                ['human',    'nv-dong-goi',     'Nhân viên đóng gói'],
                ['human',    'nv-giao-hang',    'NV giao hàng / vận chuyển'],
                ['material', 'bao-bi',          'Bao bì / túi đóng gói'],
                ['material', 'nhan-label',      'Nhãn / Label / Sticker'],
                ['material', 'thung-carton',    'Thùng carton'],
                ['material', 'phu-kien',        'Phụ kiện đóng gói'],
                ['process',  'kiem-tra-cl',     'Kiểm tra chất lượng cuối'],
                ['process',  'xu-ly-don-hang',  'Xử lý đơn hàng'],
                ['process',  'cho-doi',         'Chờ đợi / Phụ thuộc'],
                ['process',  'van-chuyen',      'Vận chuyển'],
            ],
            errors: [
                ['may-in-nhan',    'Máy kẹt giấy'],
                ['may-in-nhan',    'Hết mực / ribbon'],
                ['may-in-nhan',    'In sai thông tin'],
                ['may-in-nhan',    'Máy dừng / treo'],
                ['may-in-nhan',    'Khác'],
                ['may-dong-goi',   'Máy seal không kín'],
                ['may-dong-goi',   'Nhiệt độ seal sai'],
                ['may-dong-goi',   'Máy kẹt / dừng'],
                ['may-dong-goi',   'Khác'],
                ['may-scan',       'Scan không nhận'],
                ['may-scan',       'Lỗi kết nối'],
                ['may-scan',       'Khác'],
                ['nv-dong-goi',    'Vắng mặt / nghỉ phép'],
                ['nv-dong-goi',    'Đi trễ'],
                ['nv-dong-goi',    'Đóng gói sai đơn hàng'],
                ['nv-dong-goi',    'Đóng gói sai size / màu'],
                ['nv-dong-goi',    'Thiếu sản phẩm trong đơn'],
                ['nv-dong-goi',    'Làm chậm / năng suất thấp'],
                ['nv-dong-goi',    'Thiếu kinh nghiệm / chưa được đào tạo'],
                ['nv-dong-goi',    'Khác'],
                ['nv-giao-hang',   'Vắng mặt / nghỉ phép'],
                ['nv-giao-hang',   'Giao sai địa chỉ'],
                ['nv-giao-hang',   'Giao trễ'],
                ['nv-giao-hang',   'Khác'],
                ['bao-bi',         'Hết bao bì'],
                ['bao-bi',         'Bao bì sai kích thước'],
                ['bao-bi',         'Bao bì bị rách / hỏng'],
                ['bao-bi',         'Khác'],
                ['nhan-label',     'Hết nhãn'],
                ['nhan-label',     'Nhãn in sai thông tin'],
                ['nhan-label',     'Khác'],
                ['thung-carton',   'Hết thùng'],
                ['thung-carton',   'Thùng sai kích thước'],
                ['thung-carton',   'Khác'],
                ['phu-kien',       'Hết phụ kiện'],
                ['phu-kien',       'Khác'],
                ['kiem-tra-cl',    'Phát hiện lỗi in / ép'],
                ['kiem-tra-cl',    'Phát hiện sai size / màu'],
                ['kiem-tra-cl',    'Phải làm lại (rework)'],
                ['kiem-tra-cl',    'Khác'],
                ['xu-ly-don-hang', 'Đơn hàng bị thiếu thông tin'],
                ['xu-ly-don-hang', 'Đơn hàng bị hủy / thay đổi'],
                ['xu-ly-don-hang', 'Đơn hàng ưu tiên chen ngang'],
                ['xu-ly-don-hang', 'Khác'],
                ['cho-doi',        'Chờ sản phẩm từ Mock Up'],
                ['cho-doi',        'Chờ nhãn / label'],
                ['cho-doi',        'Chờ xe vận chuyển'],
                ['cho-doi',        'Chờ xác nhận từ quản lý'],
                ['cho-doi',        'Hết việc'],
                ['cho-doi',        'Khác'],
                ['van-chuyen',     'Carrier đến trễ'],
                ['van-chuyen',     'Sai lịch pickup'],
                ['van-chuyen',     'Khác'],
            ],
            categoryMap: compact('machine', 'human', 'material', 'process'),
        );

        // ═══════════════════════════════════════════════════════════════════
        // PICK (scope_dept = 'pick')
        // ═══════════════════════════════════════════════════════════════════
        $this->seedDept(
            dept: 'pick',
            machine: $machine,
            human: $human,
            material: $material,
            process: $process,
            subItems: [
                ['machine',  'may-scan',             'Máy scan / barcode'],
                ['machine',  'xe-day',               'Xe đẩy / trolley'],
                ['human',    'nv-pick',              'Nhân viên Pick'],
                ['material', 'ao-trang',             'Áo trắng (Blank garment)'],
                ['material', 'nhan-tag',             'Nhãn / Tag đơn hàng'],
                ['process',  'he-thong-don-hang',    'Hệ thống đơn hàng'],
                ['process',  'sap-xep-kho',          'Sắp xếp kho'],
                ['process',  'cho-doi',              'Chờ đợi / Phụ thuộc'],
                ['process',  'phan-phoi',            'Phân phối'],
            ],
            errors: [
                ['may-scan',          'Scan không nhận'],
                ['may-scan',          'Lỗi kết nối hệ thống'],
                ['may-scan',          'Hết pin / sạc'],
                ['may-scan',          'Khác'],
                ['xe-day',            'Xe hỏng / kẹt bánh'],
                ['xe-day',            'Không đủ xe'],
                ['xe-day',            'Khác'],
                ['nv-pick',           'Vắng mặt / nghỉ phép'],
                ['nv-pick',           'Đi trễ'],
                ['nv-pick',           'Pick sai size áo'],
                ['nv-pick',           'Pick sai màu áo'],
                ['nv-pick',           'Pick sai loại áo (brand / style)'],
                ['nv-pick',           'Pick sai số lượng'],
                ['nv-pick',           'Pick nhầm đơn hàng'],
                ['nv-pick',           'Làm chậm / năng suất thấp'],
                ['nv-pick',           'Thiếu kinh nghiệm / chưa được đào tạo'],
                ['nv-pick',           'Khác'],
                ['ao-trang',          'Hết hàng tồn kho (out of stock)'],
                ['ao-trang',          'Hết size cụ thể'],
                ['ao-trang',          'Hết màu cụ thể'],
                ['ao-trang',          'Áo bị lỗi / bẩn từ nhà cung cấp'],
                ['ao-trang',          'Áo sai lô / sai batch'],
                ['ao-trang',          'Khác'],
                ['nhan-tag',          'Hết nhãn'],
                ['nhan-tag',          'Nhãn in sai'],
                ['nhan-tag',          'Khác'],
                ['he-thong-don-hang', 'Đơn hàng chưa được phân bổ'],
                ['he-thong-don-hang', 'Thông tin đơn hàng sai / thiếu'],
                ['he-thong-don-hang', 'Hệ thống bị lỗi / chậm'],
                ['he-thong-don-hang', 'Khác'],
                ['sap-xep-kho',       'Áo để sai vị trí'],
                ['sap-xep-kho',       'Kho lộn xộn / khó tìm'],
                ['sap-xep-kho',       'Chưa nhập kho kịp (hàng mới về)'],
                ['sap-xep-kho',       'Khác'],
                ['cho-doi',           'Chờ hàng nhập kho'],
                ['cho-doi',           'Chờ đơn hàng từ hệ thống'],
                ['cho-doi',           'Chờ xác nhận từ quản lý'],
                ['cho-doi',           'Hết việc'],
                ['cho-doi',           'Khác'],
                ['phan-phoi',         'Giao sai line (DTF1 / DTF2 / DTG)'],
                ['phan-phoi',         'Giao thiếu số lượng'],
                ['phan-phoi',         'Giao trễ cho line sản xuất'],
                ['phan-phoi',         'Khác'],
            ],
            categoryMap: compact('machine', 'human', 'material', 'process'),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Seed sub_items + errors for one department.
     *
     * @param  array<int, array{0:string, 1:string, 2:string}> $subItems  [category_key, slug, label]
     * @param  array<int, array{0:string, 1:string}>           $errors    [sub_item_slug, label]
     * @param  array<string, ReasonCategory>                   $categoryMap
     */
    private function seedDept(
        string $dept,
        ReasonCategory $machine,
        ReasonCategory $human,
        ReasonCategory $material,
        ReasonCategory $process,
        array $subItems,
        array $errors,
        array $categoryMap,
    ): void {
        // Build sub_item slug → DB id map
        $subItemIds = [];

        foreach ($subItems as $i => [$catKey, $slug, $label]) {
            /** @var ReasonCategory $category */
            $category = $categoryMap[$catKey];

            $code = "sub-{$dept}-{$catKey}-{$slug}";

            $record = ReasonSubItem::create([
                'category_id' => $category->id,
                'code'        => $code,
                'label'       => $label,
                'scope_type'  => 'per_department',
                'scope_line'  => null,
                'scope_dept'  => $dept,
                'sort_order'  => $i + 1,
            ]);

            $subItemIds[$slug] = $record->id;
        }

        // Build category key index (slug → category_id) for error creation
        $subItemCatMap = [];
        foreach ($subItems as [$catKey, $slug]) {
            $subItemCatMap[$slug] = $categoryMap[$catKey]->id;
        }

        // Group errors by slug for sort_order numbering
        $slugCounter = [];

        foreach ($errors as [$subItemSlug, $errorLabel]) {
            $slugCounter[$subItemSlug] = ($slugCounter[$subItemSlug] ?? 0) + 1;
            $order = $slugCounter[$subItemSlug];

            $categoryId  = $subItemCatMap[$subItemSlug] ?? $categoryMap['process']->id;
            $subItemId   = $subItemIds[$subItemSlug] ?? null;  // FK → level 2

            $errCode = "err-{$dept}-{$subItemSlug}-" . $this->slugify($errorLabel);

            ReasonError::create([
                'category_id' => $categoryId,
                'sub_item_id' => $subItemId,
                'code'        => mb_substr($errCode, 0, 100),
                'label'       => $errorLabel,
                'sort_order'  => $order,
            ]);
        }
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[\s\/\(\)\[\]]+/', '-', $text);
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);
        $text = preg_replace('/-+/', '-', $text);

        return trim($text, '-');
    }
}
