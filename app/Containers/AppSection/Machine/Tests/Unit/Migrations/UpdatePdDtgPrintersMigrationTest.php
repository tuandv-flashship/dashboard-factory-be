<?php

namespace App\Containers\AppSection\Machine\Tests\Unit\Migrations;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Machine\Tests\UnitTestCase;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Enums\ShiftTemplateStatus;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration 2026_11_07_100001_update_pd_dtg_printers:
 *   - recode atlas_01/atlas_02 → atlas_1/atlas_2 (match fplatform printed_by)
 *   - rename Apollo/Atlas-01/Atlas-02 → Apollo 1/Atlas 2/Atlas 3
 *   - add atlas4 (Matrix 4), atlas5 (Matrix 5), apollo6 (Apollo 6)
 *   - attach new machines to dtg_print shift_details of active shifts
 */
final class UpdatePdDtgPrintersMigrationTest extends UnitTestCase
{
    private const MIGRATION = 'app/Containers/AppSection/Machine/Data/Migrations/2026_11_07_100001_update_pd_dtg_printers.php';

    private Department $dtgPrint;
    private Machine $apollo;
    private Machine $atlas01;
    private Machine $atlas02;
    private ShiftDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        config(['factory.current' => 'PD']);

        $line = ProductionLine::firstOrCreate(
            ['code' => 'dtg'],
            ['label' => 'DTG', 'color' => '#8b5cf6', 'sort_order' => 2, 'is_active' => true],
        );

        $this->dtgPrint = Department::firstOrCreate(
            ['code' => 'dtg_print', 'production_line_id' => $line->id],
            [
                'label' => 'DTG Print', 'label_en' => 'DTG Print',
                'icon' => 'Printer', 'unit' => 'print', 'sort_order' => 1, 'is_active' => true,
                'kpi_per_hour' => 0,
                'productivity_type' => ProductivityType::PerMachineDtg->value,
            ],
        );

        $this->apollo  = $this->machine('apollo',   'Apollo',   280, 1);
        $this->atlas01 = $this->machine('atlas_01', 'Atlas-01', 90,  2);
        $this->atlas02 = $this->machine('atlas_02', 'Atlas-02', 98,  3);

        ShiftTemplate::query()->update(['status' => ShiftTemplateStatus::INACTIVE]);
        $template = ShiftTemplate::create([
            'name' => 'Ca test', 'color' => '#0000FF', 'sort_order' => 1,
            'status' => ShiftTemplateStatus::ACTIVE,
            'applies_to_shift_1' => true, 'applies_to_shift_2' => false,
        ]);

        $shift = Shift::create([
            'date' => '2099-11-07', 'shift_number' => 1,
            'start_time' => '06:00', 'end_time' => '14:30',
            'is_active' => true, 'shift_template_id' => $template->id,
        ]);

        $this->detail = ShiftDetail::create([
            'shift_id' => $shift->id, 'department_id' => $this->dtgPrint->id, 'shift_number' => 1,
            'headcount' => 0, 'machine_count' => 3, 'kpi_per_hour' => 468,
            'start_time' => '06:00', 'work_hours' => 8,
        ]);

        foreach ([$this->apollo, $this->atlas01, $this->atlas02] as $m) {
            ShiftDetailMachine::create([
                'shift_detail_id' => $this->detail->id,
                'machine_id'      => $m->id,
                'kpi_per_hour'    => $m->kpi_per_hour,
            ]);
        }
    }

    private function machine(string $code, string $name, int $kpi, int $sort): Machine
    {
        return Machine::create([
            'department_id' => $this->dtgPrint->id, 'code' => $code, 'name' => $name,
            'status' => 'online', 'unit' => 'print', 'kpi_per_hour' => $kpi,
            'sort_order' => $sort, 'is_active' => true,
        ]);
    }

    private function migration(): Migration
    {
        return require base_path(self::MIGRATION);
    }

    public function testRecodesRenamesAndAddsMachines(): void
    {
        $this->migration()->up();

        $machines = Machine::where('department_id', $this->dtgPrint->id)->orderBy('sort_order')->get();

        $this->assertSame(
            ['apollo', 'atlas_1', 'atlas_2', 'atlas4', 'atlas5', 'apollo6'],
            $machines->pluck('code')->all(),
        );
        $this->assertSame(
            ['Apollo 1', 'Atlas 2', 'Atlas 3', 'Matrix 4', 'Matrix 5', 'Apollo 6'],
            $machines->pluck('name')->all(),
        );
        $this->assertSame([1, 2, 3, 4, 5, 6], $machines->pluck('sort_order')->all());
        $this->assertSame([280, 90, 98, 90, 90, 280], $machines->pluck('kpi_per_hour')->all());

        // Existing machine ids are preserved (history links stay valid)
        $this->assertSame($this->atlas01->id, $machines[1]->id);
        $this->assertSame($this->atlas02->id, $machines[2]->id);
        $this->assertTrue($machines->every(fn ($m) => $m->is_active));
    }

    public function testAttachesNewMachinesToActiveShiftAndRecomputesKpi(): void
    {
        $this->migration()->up();

        $pivots = ShiftDetailMachine::where('shift_detail_id', $this->detail->id)->get();
        $this->assertCount(6, $pivots);

        $this->detail->refresh();
        $this->assertSame(280 + 90 + 98 + 90 + 90 + 280, $this->detail->kpi_per_hour);
        $this->assertSame(6, $this->detail->machine_count);
    }

    public function testDoesNotTouchPastShifts(): void
    {
        $past = Shift::create([
            'date' => '2020-01-01', 'shift_number' => 1,
            'start_time' => '06:00', 'end_time' => '14:30',
            'is_active' => true, 'shift_template_id' => $this->detail->shift->shift_template_id,
        ]);
        $pastDetail = ShiftDetail::create([
            'shift_id' => $past->id, 'department_id' => $this->dtgPrint->id, 'shift_number' => 1,
            'headcount' => 0, 'machine_count' => 1, 'kpi_per_hour' => 280,
            'start_time' => '06:00', 'work_hours' => 8,
        ]);
        ShiftDetailMachine::create([
            'shift_detail_id' => $pastDetail->id, 'machine_id' => $this->apollo->id, 'kpi_per_hour' => 280,
        ]);

        $this->migration()->up();

        $this->assertSame(1, ShiftDetailMachine::where('shift_detail_id', $pastDetail->id)->count());
        $pastDetail->refresh();
        $this->assertSame(280, $pastDetail->kpi_per_hour);
        $this->assertSame(1, $pastDetail->machine_count);
    }

    public function testIdempotent(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(6, Machine::where('department_id', $this->dtgPrint->id)->count());
        $this->assertSame(6, ShiftDetailMachine::where('shift_detail_id', $this->detail->id)->count());
    }

    public function testNoopWhenNotPd(): void
    {
        config(['factory.current' => 'FLS']);

        $this->migration()->up();

        $this->assertSame(3, Machine::where('department_id', $this->dtgPrint->id)->count());
        $this->assertSame('atlas_01', $this->atlas01->fresh()->code);
        $this->assertSame(3, ShiftDetailMachine::where('shift_detail_id', $this->detail->id)->count());
    }

    public function testDownRestoresOriginalState(): void
    {
        $this->migration()->up();
        $this->migration()->down();

        $machines = Machine::where('department_id', $this->dtgPrint->id)->orderBy('sort_order')->get();
        $this->assertSame(['apollo', 'atlas_01', 'atlas_02'], $machines->pluck('code')->all());
        $this->assertSame(['Apollo', 'Atlas-01', 'Atlas-02'], $machines->pluck('name')->all());
        $this->assertSame(3, ShiftDetailMachine::where('shift_detail_id', $this->detail->id)->count());
        $this->assertSame(468, $this->detail->fresh()->kpi_per_hour);
    }
}
