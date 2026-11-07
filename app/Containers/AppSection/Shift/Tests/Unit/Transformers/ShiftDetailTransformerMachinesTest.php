<?php

namespace App\Containers\AppSection\Shift\Tests\Unit\Transformers;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Enums\ShiftTemplateStatus;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Tests\UnitTestCase;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftDetailTransformer;

final class ShiftDetailTransformerMachinesTest extends UnitTestCase
{
    public function testMachinesIncludeSortOrderCodeNameAndSnapshotKpi(): void
    {
        $line = ProductionLine::firstOrCreate(
            ['code' => 'dtg'],
            ['label' => 'DTG', 'color' => '#8b5cf6', 'sort_order' => 2, 'is_active' => true],
        );
        $dept = Department::firstOrCreate(
            ['code' => 'dtg_print', 'production_line_id' => $line->id],
            [
                'label' => 'DTG Print', 'label_en' => 'DTG Print', 'icon' => 'Printer', 'unit' => 'print',
                'sort_order' => 1, 'is_active' => true, 'kpi_per_hour' => 0,
                'productivity_type' => ProductivityType::PerMachineDtg->value,
            ],
        );
        $apollo6 = Machine::create([
            'department_id' => $dept->id, 'code' => 'apollo6', 'name' => 'Apollo 6', 'status' => 'online',
            'unit' => 'print', 'kpi_per_hour' => 280, 'sort_order' => 6, 'is_active' => true,
        ]);

        ShiftTemplate::query()->update(['status' => ShiftTemplateStatus::INACTIVE]);
        $template = ShiftTemplate::create([
            'name' => 'Ca test', 'color' => '#0000FF', 'sort_order' => 1,
            'status' => ShiftTemplateStatus::ACTIVE, 'applies_to_shift_1' => true, 'applies_to_shift_2' => false,
        ]);
        $shift = Shift::create([
            'date' => '2099-11-08', 'shift_number' => 1, 'start_time' => '06:00', 'end_time' => '14:30',
            'is_active' => true, 'shift_template_id' => $template->id,
        ]);
        $detail = ShiftDetail::create([
            'shift_id' => $shift->id, 'department_id' => $dept->id, 'shift_number' => 1,
            'headcount' => 0, 'machine_count' => 1, 'kpi_per_hour' => 300,
            'start_time' => '06:00:00', 'work_hours' => 8,
        ]);
        ShiftDetailMachine::create([
            'shift_detail_id' => $detail->id, 'machine_id' => $apollo6->id, 'kpi_per_hour' => 300, // snapshot ≠ machine KPI
        ]);

        $detail->load(['department.productionLine', 'machines.machine']);
        $data = (new ShiftDetailTransformer())->transform($detail);

        $this->assertCount(1, $data['machines']);
        $this->assertSame('apollo6', $data['machines'][0]['code']);
        $this->assertSame('Apollo 6', $data['machines'][0]['name']);
        $this->assertSame(6, $data['machines'][0]['sort_order']);
        $this->assertSame(300, $data['machines'][0]['kpi_per_hour']);
    }
}
