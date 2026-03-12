<?php

namespace App\Containers\AppSection\ReasonCode\Data\Seeders;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds all default KPI reason codes matching the FE departmentData.ts hardcoded data.
 *
 * Structure: Category → SubItems (scoped) + Errors (scoped)
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\ReasonCode\Data\Seeders\ReasonCodeSeeder_1"
 */
final class ReasonCodeSeeder_1 extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        ReasonError::query()->delete();
        ReasonSubItem::query()->delete();
        ReasonCategory::query()->delete();

        // ═══════════════════════════════════════════════════════
        // 1. CATEGORIES
        // ═══════════════════════════════════════════════════════
        $machine = ReasonCategory::create([
            'code' => 'machine', 'label' => 'Máy móc', 'label_en' => 'Machine',
            'icon' => 'Cog', 'color' => '#ef4444', 'sort_order' => 1,
        ]);

        $human = ReasonCategory::create([
            'code' => 'human', 'label' => 'Con người', 'label_en' => 'Human',
            'icon' => 'Users', 'color' => '#f59e0b', 'sort_order' => 2,
        ]);

        $material = ReasonCategory::create([
            'code' => 'material', 'label' => 'Nguyên vật liệu', 'label_en' => 'Material',
            'icon' => 'Package', 'color' => '#8b5cf6', 'sort_order' => 3,
        ]);

        $process = ReasonCategory::create([
            'code' => 'process', 'label' => 'Qui trình', 'label_en' => 'Process',
            'icon' => 'GitBranch', 'color' => '#14b8a6', 'sort_order' => 4,
        ]);

        // ═══════════════════════════════════════════════════════
        // 2. MACHINE SUB-ITEMS (per line + department)
        // ═══════════════════════════════════════════════════════
        $machinesByLineDept = [
            // DTF1
            ['dtf1', 'print', ['DTF-01', 'DTF-02', 'DTF-03', 'DTF-04', 'HP-01', 'HP-02']],
            ['dtf1', 'cut', ['CUT-01', 'CUT-02', 'CUT-03']],
            ['dtf1', 'mockup', ['SEW-01', 'SEW-02', 'SEW-03', 'SEW-04']],
            ['dtf1', 'pack_ship', ['PKG-01', 'PKG-02', 'LBL-01', 'LBL-02']],
            ['dtf1', 'pick', ['SCAN-01', 'SCAN-02', 'CART-01', 'CART-02']],
            // DTF2
            ['dtf2', 'print', ['DTF-01', 'DTF-02', 'DTF-03', 'HP-01']],
            ['dtf2', 'cut', ['CUT-01', 'CUT-02']],
            ['dtf2', 'mockup', ['SEW-01', 'SEW-02', 'SEW-03']],
            ['dtf2', 'pack_ship', ['PKG-01', 'PKG-02', 'LBL-01']],
            ['dtf2', 'pick', ['SCAN-03', 'CART-03']],
            // DTG
            ['dtg', 'print', ['Apollo', 'Atlas-01', 'Atlas-02']],
            ['dtg', 'pick', ['SCAN-04', 'CART-04']],
        ];

        $order = 1;
        foreach ($machinesByLineDept as [$line, $dept, $machines]) {
            foreach ($machines as $machineName) {
                ReasonSubItem::create([
                    'category_id' => $machine->id,
                    'code' => 'machine-' . $line . '-' . $dept . '-' . strtolower(str_replace(' ', '-', $machineName)),
                    'label' => $machineName,
                    'scope_type' => 'per_line_department',
                    'scope_line' => $line,
                    'scope_dept' => $dept,
                    'sort_order' => $order++,
                ]);
            }
        }

        // ═══════════════════════════════════════════════════════
        // 3. HUMAN SUB-ITEMS (global)
        // ═══════════════════════════════════════════════════════
        $humanSubItems = [
            ['human-absent', 'Vắng mặt / Nghỉ phép'],
            ['human-new', 'Nhân viên mới / Chưa thạo'],
            ['human-slow', 'Làm chậm / Mệt mỏi'],
            ['human-mistake', 'Sai sót thao tác'],
            ['human-shortage', 'Thiếu người'],
        ];

        foreach ($humanSubItems as $i => [$code, $label]) {
            ReasonSubItem::create([
                'category_id' => $human->id,
                'code' => $code,
                'label' => $label,
                'scope_type' => 'global',
                'sort_order' => $i + 1,
            ]);
        }

        // ═══════════════════════════════════════════════════════
        // 4. MATERIAL SUB-ITEMS (per department)
        // ═══════════════════════════════════════════════════════
        $materialsByDept = [
            ['print', [
                ['mat-ink-white', 'Mực trắng'],
                ['mat-ink-cmyk', 'Mực CMYK'],
                ['mat-film', 'Film DTF'],
                ['mat-powder', 'Bột keo (powder)'],
            ]],
            ['dtg_print', [
                ['mat-ink-white-dtg', 'Mực trắng'],
                ['mat-ink-cmyk-dtg', 'Mực CMYK'],
                ['mat-pretreat', 'Dung dịch pretreat'],
            ]],
            ['cut', [
                ['mat-blade', 'Lưỡi cắt'],
                ['mat-cutting-mat', 'Thảm cắt'],
            ]],
            ['mockup', [
                ['mat-garment', 'Áo trắng (blank)'],
                ['mat-press-paper', 'Giấy ép nhiệt'],
                ['mat-thread', 'Chỉ may'],
            ]],
            ['pack_ship', [
                ['mat-poly-bag', 'Túi poly'],
                ['mat-label', 'Nhãn vận chuyển'],
                ['mat-box', 'Thùng carton'],
                ['mat-tape', 'Băng keo'],
            ]],
            ['pick', [
                ['mat-label-pick', 'Nhãn pick'],
                ['mat-tote', 'Khay / Tote'],
            ]],
        ];

        foreach ($materialsByDept as [$dept, $items]) {
            foreach ($items as $i => [$code, $label]) {
                ReasonSubItem::create([
                    'category_id' => $material->id,
                    'code' => $code,
                    'label' => $label,
                    'scope_type' => 'per_department',
                    'scope_dept' => $dept,
                    'sort_order' => $i + 1,
                ]);
            }
        }

        // ═══════════════════════════════════════════════════════
        // 5. PROCESS SUB-ITEMS (global)
        // ═══════════════════════════════════════════════════════
        $processSubItems = [
            ['proc-queue', 'Tắc nghẽn hàng chờ'],
            ['proc-rework', 'Làm lại (rework)'],
            ['proc-waiting', 'Chờ bộ phận trước'],
            ['proc-changeover', 'Chuyển đổi sản phẩm'],
            ['proc-qc-hold', 'QC giữ hàng kiểm tra'],
            ['proc-system', 'Lỗi hệ thống / phần mềm'],
        ];

        foreach ($processSubItems as $i => [$code, $label]) {
            ReasonSubItem::create([
                'category_id' => $process->id,
                'code' => $code,
                'label' => $label,
                'scope_type' => 'global',
                'sort_order' => $i + 1,
            ]);
        }

        // ═══════════════════════════════════════════════════════
        // 6. MACHINE ERRORS
        // ═══════════════════════════════════════════════════════

        // Common machine errors (all departments)
        $commonMachineErrors = [
            ['err-breakdown', 'Hỏng máy / Ngừng hoạt động'],
            ['err-maintenance', 'Bảo trì định kỳ'],
            ['err-calibration', 'Cần hiệu chỉnh'],
            ['err-jam', 'Kẹt máy'],
            ['err-slow', 'Chạy chậm / Giảm tốc độ'],
        ];

        foreach ($commonMachineErrors as $i => [$code, $label]) {
            ReasonError::create([
                'category_id' => $machine->id,
                'code' => $code,
                'label' => $label,
                'scope_dept' => null, // all depts
                'sort_order' => $i + 1,
            ]);
        }

        // Dept-specific machine errors
        $deptMachineErrors = [
            ['print', [
                ['err-head-clog', 'Tắc đầu in'],
                ['err-color-shift', 'Lệch màu'],
                ['err-banding', 'Vạch ngang (banding)'],
            ]],
            ['dtg_print', [
                ['err-head-clog-dtg', 'Tắc đầu in'],
                ['err-color-shift-dtg', 'Lệch màu'],
                ['err-pretreat', 'Lỗi pretreat'],
            ]],
            ['cut', [
                ['err-blade-dull', 'Lưỡi cắt mòn'],
                ['err-misalign', 'Lệch vị trí cắt'],
            ]],
            ['mockup', [
                ['err-temp', 'Nhiệt độ ép không đúng'],
                ['err-press-fail', 'Máy ép lỗi'],
                ['err-needle-break', 'Gãy kim'],
            ]],
            ['pack_ship', [
                ['err-label-print', 'Máy in nhãn lỗi'],
                ['err-seal', 'Máy dán túi lỗi'],
            ]],
            ['pick', [
                ['err-scanner', 'Scanner không đọc được'],
                ['err-wrong-item', 'Pick sai sản phẩm'],
            ]],
        ];

        $baseOrder = count($commonMachineErrors) + 1;
        foreach ($deptMachineErrors as [$dept, $errors]) {
            foreach ($errors as $i => [$code, $label]) {
                ReasonError::create([
                    'category_id' => $machine->id,
                    'code' => $code,
                    'label' => $label,
                    'scope_dept' => $dept,
                    'sort_order' => $baseOrder + $i,
                ]);
            }
        }

        // "Khác (ghi chú)" for machine category
        ReasonError::create([
            'category_id' => $machine->id,
            'code' => 'err-other',
            'label' => 'Khác (ghi chú)',
            'scope_dept' => null,
            'sort_order' => 99,
        ]);

        // ═══════════════════════════════════════════════════════
        // 7. HUMAN ERRORS (global)
        // ═══════════════════════════════════════════════════════
        $humanErrors = [
            ['herr-late', 'Đi trễ'],
            ['herr-leave', 'Xin nghỉ đột xuất'],
            ['herr-untrained', 'Chưa được đào tạo'],
            ['herr-fatigue', 'Mệt mỏi / Giảm năng suất'],
            ['herr-conflict', 'Mâu thuẫn nội bộ'],
            ['herr-other', 'Khác (ghi chú)'],
        ];

        foreach ($humanErrors as $i => [$code, $label]) {
            ReasonError::create([
                'category_id' => $human->id,
                'code' => $code,
                'label' => $label,
                'scope_dept' => null,
                'sort_order' => $i + 1,
            ]);
        }

        // ═══════════════════════════════════════════════════════
        // 8. MATERIAL ERRORS (global)
        // ═══════════════════════════════════════════════════════
        $materialErrors = [
            ['merr-outstock', 'Hết hàng / Chưa nhập'],
            ['merr-defect', 'Lỗi chất lượng NVL'],
            ['merr-wrong', 'Nhập sai loại'],
            ['merr-delay', 'Giao hàng trễ'],
            ['merr-low', 'Sắp hết (< 20%)'],
            ['merr-other', 'Khác (ghi chú)'],
        ];

        foreach ($materialErrors as $i => [$code, $label]) {
            ReasonError::create([
                'category_id' => $material->id,
                'code' => $code,
                'label' => $label,
                'scope_dept' => null,
                'sort_order' => $i + 1,
            ]);
        }

        // ═══════════════════════════════════════════════════════
        // 9. PROCESS ERRORS (global)
        // ═══════════════════════════════════════════════════════
        $processErrors = [
            ['perr-bottleneck', 'Tắc nghẽn từ bộ phận trước'],
            ['perr-rework', 'Phải làm lại do lỗi'],
            ['perr-changeover', 'Thời gian chuyển đổi lâu'],
            ['perr-qc', 'QC giữ hàng kiểm tra'],
            ['perr-system', 'Lỗi phần mềm / hệ thống'],
            ['perr-sop', 'Không tuân thủ SOP'],
            ['perr-other', 'Khác (ghi chú)'],
        ];

        foreach ($processErrors as $i => [$code, $label]) {
            ReasonError::create([
                'category_id' => $process->id,
                'code' => $code,
                'label' => $label,
                'scope_dept' => null,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
