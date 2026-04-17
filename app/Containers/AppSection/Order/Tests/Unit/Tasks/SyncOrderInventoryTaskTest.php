<?php

namespace App\Containers\AppSection\Order\Tests\Unit\Tasks;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgOrderInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHotshotOrderInventoryTask;
use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Containers\AppSection\Order\Tasks\SyncOrderInventoryTask;
use App\Containers\AppSection\Order\Tests\UnitTestCase;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for SyncOrderInventoryTask.
 *
 * Each test uses Carbon::setTestNow to freeze a unique date and creates its
 * own shift. Records are queried using OrderSummary::latest() since date-based
 * queries behave differently in SQLite (test) vs MySQL (production).
 */
#[CoversClass(SyncOrderInventoryTask::class)]
final class SyncOrderInventoryTaskTest extends UnitTestCase
{
    private Department $dept;
    private string $testDate;

    protected function setUp(): void
    {
        parent::setUp();

        config(['factory.current' => 'FLS']);

        $line = ProductionLine::firstOrCreate(
            ['code' => 'dtf'],
            ['label' => 'DTF', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true],
        );

        $this->dept = Department::firstOrCreate(
            ['code' => 'print', 'production_line_id' => $line->id],
            [
                'label' => 'In ấn', 'label_en' => 'Print',
                'icon' => 'Printer', 'unit' => 'file', 'sort_order' => 1, 'is_active' => true,
                'kpi_per_hour' => 130,
                'productivity_type' => ProductivityType::PerPerson->value,
            ],
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Create shift and freeze time for a given date.
     */
    private function setupShiftAndTime(string $date): void
    {
        $this->testDate = $date;
        Carbon::setTestNow(Carbon::parse("{$date} 10:00:00"));

        Shift::create([
            'date'         => $date,
            'shift_number' => 1,
            'start_time'   => '06:00',
            'end_time'     => '14:00',
            'is_active'    => true,
        ]);

        ShiftDetail::create([
            'shift_id'       => Shift::latest('id')->first()->id,
            'department_id'  => $this->dept->id,
            'shift_number'   => 1,
            'headcount'      => 8,
            'start_time'     => '06:00:00',
            'work_hours'     => 8,
        ]);
    }

    /**
     * Build a SyncOrderInventoryTask with stub dependencies.
     *
     * $dtf data is wrapped into allInventory cache format since
     * SyncOrderInventoryTask now reads from GetAllTeamsInventoryTask.
     */
    private function buildSyncTask(?array $dtf, ?array $dtg, ?array $hotshot): SyncOrderInventoryTask
    {
        // Wrap DTF data in allInventory format (matching GetAllTeamsInventoryTask output)
        $allInventory = [
            'date'  => $this->testDate ?? '2099-12-01',
            'teams' => $dtf ? ['order_inventory' => $dtf] : [],
        ];

        $allTeamsStub = new class($allInventory) {
            public function __construct(private readonly array $r) {}
            public function run(string $d): array { return $this->r; }
        };
        $dtgStub = new class($dtg) {
            public function __construct(private readonly ?array $r) {}
            public function run(string $d): ?array { return $this->r; }
        };
        $hotshotStub = new class($hotshot) {
            public function __construct(private readonly ?array $r) {}
            public function run(string $d, mixed $f): ?array { return $this->r; }
        };

        $ref = new \ReflectionClass(SyncOrderInventoryTask::class);
        $task = $ref->newInstanceWithoutConstructor();

        \Closure::bind(function () use ($allTeamsStub, $dtgStub, $hotshotStub) {
            $this->allTeamsInventoryTask = $allTeamsStub;
            $this->dtgTask = $dtgStub;
            $this->hotshotTask = $hotshotStub;
        }, $task, SyncOrderInventoryTask::class)();

        return $task;
    }

    /**
     * Count OrderSummary records created AFTER setup.
     */
    private function countNewRecords(): int
    {
        // Use shift_number=1 + the unique line to scope
        return OrderSummary::where('shift_number', 1)
            ->where('line_label', 'DTF')
            ->orWhere('line_label', 'DTG')
            ->count();
    }

    // ── Test: No Shift ──────────────────────────────────

    public function testSkipsWhenNoShiftExists(): void
    {
        Carbon::setTestNow(Carbon::parse('2099-12-01 10:00:00'));

        $countBefore = OrderSummary::count();

        // No shift created for this date
        $task = $this->buildSyncTask(null, null, null);
        $task->run('2099-12-01');

        // No new records should be created
        $this->assertSame($countBefore, OrderSummary::count());
    }

    // ── Test: DTF Only (FLS) ────────────────────────────

    public function testSyncsDtfOnlyForFls(): void
    {
        $this->setupShiftAndTime('2099-12-02');
        config(['factory.current' => 'FLS']);

        $task = $this->buildSyncTask(
            dtf: ['estimate_date' => $this->testDate, 'tong_viec' => 1200, 'da_lam' => 480],
            dtg: null,
            hotshot: null,
        );

        $task->run($this->testDate);

        $record = OrderSummary::latest('id')->first();
        $this->assertNotNull($record);
        $this->assertSame('dtf', $record->line);
        $this->assertSame('DTF', $record->line_label);
        $this->assertSame(1200, $record->total);
        $this->assertSame(480, $record->completed);
        $this->assertSame(720, $record->remaining);
        $this->assertSame(40.0, $record->progress);
    }

    // ── Test: DTF + DTG (PD) ────────────────────────────

    public function testSyncsDtfAndDtgForPd(): void
    {
        $this->setupShiftAndTime('2099-12-03');
        config(['factory.current' => 'PD']);

        $task = $this->buildSyncTask(
            dtf: ['estimate_date' => $this->testDate, 'tong_viec' => 1000, 'da_lam' => 300],
            dtg: ['estimate_date' => $this->testDate, 'tong_viec' => 500, 'da_lam' => 200],
            hotshot: null,
        );

        $task->run($this->testDate);

        // Should have 2 new records
        $records = OrderSummary::latest('id')->take(2)->get();
        $this->assertCount(2, $records);

        $dtg = $records->firstWhere('line', 'dtg');
        $dtf = $records->firstWhere('line', 'dtf');

        $this->assertNotNull($dtf);
        $this->assertSame(1000, $dtf->total);
        $this->assertSame(300, $dtf->completed);

        $this->assertNotNull($dtg);
        $this->assertSame(500, $dtg->total);
        $this->assertSame(200, $dtg->completed);
        $this->assertSame(300, $dtg->remaining);
    }

    // ── Test: Hotshot Rush ──────────────────────────────

    public function testIncludesHotshotInDtfLine(): void
    {
        $this->setupShiftAndTime('2099-12-04');
        config(['factory.current' => 'FLS']);

        $task = $this->buildSyncTask(
            dtf: ['estimate_date' => $this->testDate, 'tong_viec' => 800, 'da_lam' => 300],
            dtg: null,
            hotshot: ['estimate_date' => $this->testDate, 'tong_viec' => 120, 'da_lam' => 45],
        );

        $task->run($this->testDate);

        $record = OrderSummary::latest('id')->first();
        $this->assertNotNull($record);
        $this->assertSame('dtf', $record->line);
        $this->assertSame(120, $record->rush_total);
        $this->assertSame(45, $record->rush_completed);
    }

    // ── Test: Upsert ────────────────────────────────────

    public function testUpsertsExistingRecords(): void
    {
        $this->setupShiftAndTime('2099-12-05');
        config(['factory.current' => 'FLS']);

        // First sync
        $task1 = $this->buildSyncTask(
            dtf: ['estimate_date' => $this->testDate, 'tong_viec' => 500, 'da_lam' => 100],
            dtg: null,
            hotshot: null,
        );
        $task1->run($this->testDate);

        $firstRecord = OrderSummary::latest('id')->first();
        $this->assertNotNull($firstRecord);
        $this->assertSame(500, $firstRecord->total);
        $this->assertSame(100, $firstRecord->completed);

        $firstId = $firstRecord->id;

        // Second sync with updated data
        $task2 = $this->buildSyncTask(
            dtf: ['estimate_date' => $this->testDate, 'tong_viec' => 500, 'da_lam' => 250],
            dtg: null,
            hotshot: null,
        );
        $task2->run($this->testDate);

        // Refresh the same record — should be upserted
        $updatedRecord = OrderSummary::find($firstId);
        $this->assertNotNull($updatedRecord);
        $this->assertSame(250, $updatedRecord->completed);
        $this->assertSame(250, $updatedRecord->remaining);
        $this->assertSame(50.0, $updatedRecord->progress);
    }

    // ── Test: No FPlatform Data ─────────────────────────

    public function testSkipsWhenNoFplatformData(): void
    {
        $this->setupShiftAndTime('2099-12-06');

        $countBefore = OrderSummary::count();

        $task = $this->buildSyncTask(null, null, null);
        $task->run($this->testDate);

        $this->assertSame($countBefore, OrderSummary::count());
    }
}
