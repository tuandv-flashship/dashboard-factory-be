<?php

namespace App\Containers\AppSection\Shift\Tests\Functional\API;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Containers\AppSection\Shift\UI\API\Controllers\CopyShiftTemplateController;
use App\Containers\AppSection\Shift\UI\API\Controllers\CreateShiftTemplateController;
use App\Containers\AppSection\Shift\UI\API\Controllers\DeleteShiftTemplateController;
use App\Containers\AppSection\Shift\UI\API\Controllers\FindShiftTemplateController;
use App\Containers\AppSection\Shift\UI\API\Controllers\ListShiftTemplatesController;
use App\Containers\AppSection\Shift\UI\API\Controllers\ReorderShiftTemplatesController;
use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateShiftTemplateController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListShiftTemplatesController::class)]
#[CoversClass(FindShiftTemplateController::class)]
#[CoversClass(CreateShiftTemplateController::class)]
#[CoversClass(UpdateShiftTemplateController::class)]
#[CoversClass(DeleteShiftTemplateController::class)]
#[CoversClass(CopyShiftTemplateController::class)]
#[CoversClass(ReorderShiftTemplatesController::class)]
final class ShiftTemplateTest extends ApiTestCase
{
    private User $admin;
    private ProductionLine $line;
    private Department $dept1;
    private Department $dept2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->createOne();
        $this->admin->givePermissionTo([
            'shift-templates.index',
            'shift-templates.create',
            'shift-templates.edit',
            'shift-templates.destroy',
        ]);

        // Seed production line + departments for detail tests
        $this->line = ProductionLine::create([
            'code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b',
            'sort_order' => 1, 'is_active' => true,
        ]);
        $this->dept1 = Department::create([
            'production_line_id' => $this->line->id,
            'code' => 'print', 'label' => 'Print', 'label_en' => 'Print',
            'icon' => 'Printer', 'unit' => 'files', 'sort_order' => 1, 'is_active' => true,
        ]);
        $this->dept2 = Department::create([
            'production_line_id' => $this->line->id,
            'code' => 'cut', 'label' => 'Cut', 'label_en' => 'Cut',
            'icon' => 'Scissors', 'unit' => 'files', 'sort_order' => 2, 'is_active' => true,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────

    private function createTemplate(array $overrides = []): ShiftTemplate
    {
        $template = ShiftTemplate::create(array_merge([
            'name'                => 'Ca chuẩn - bình thường',
            'color'               => '#0000FF',
            'description'         => 'Dành cho ngày làm việc bình thường',
            'sort_order'          => 1,
            'status'              => 'active',
            'applies_to_shift_1'  => true,
            'applies_to_shift_2'  => false,
        ], $overrides));

        // Add details for dept1 and dept2
        ShiftTemplateDetail::create([
            'shift_template_id' => $template->id,
            'department_id'     => $this->dept1->id,
            'shift_number'      => 1,
            'headcount'         => 8,
            'start_time'        => '06:30',
            'work_hours'        => 8.5,
            'prep_minutes'      => 23,
            'break1_start'      => '09:00',
            'break1_minutes'    => 30,
            'meal_break_start'  => '11:30',
            'meal_break_minutes'=> 15,
            'break2_start'      => '14:00',
            'break2_minutes'    => 15,
            'break3_start'      => '16:30',
            'break3_minutes'    => 15,
        ]);

        ShiftTemplateDetail::create([
            'shift_template_id' => $template->id,
            'department_id'     => $this->dept2->id,
            'shift_number'      => 1,
            'headcount'         => 5,
            'start_time'        => '07:00',
            'work_hours'        => 8.5,
            'prep_minutes'      => 0,
            'break1_start'      => '09:30',
            'break1_minutes'    => 30,
            'meal_break_start'  => '12:00',
            'meal_break_minutes'=> 15,
            'break2_start'      => '14:30',
            'break2_minutes'    => 15,
            'break3_start'      => '17:00',
            'break3_minutes'    => 15,
        ]);

        return $template->load('details.department');
    }

    private function validCreatePayload(): array
    {
        return [
            'name'                => 'Ca chuẩn - tăng ca',
            'color'               => '#FF0000',
            'description'         => 'Dành cho các ngày sự kiện nhiều đơn',
            'sort_order'          => 2,
            'status'              => 'active',
            'applies_to_shift_1'  => true,
            'applies_to_shift_2'  => false,
            'details' => [
                [
                    'department_id'     => $this->dept1->id,
                    'shift_number'      => 1,
                    'headcount'         => 8,
                    'start_time'        => '06:30',
                    'work_hours'        => 8.5,
                    'prep_minutes'      => 23,
                    'break1_start'      => '09:00',
                    'break1_minutes'    => 30,
                    'meal_break_start'  => '11:30',
                    'meal_break_minutes'=> 15,
                    'break2_start'      => '14:00',
                    'break2_minutes'    => 15,
                    'break3_start'      => '16:30',
                    'break3_minutes'    => 15,
                ],
                [
                    'department_id'     => $this->dept2->id,
                    'shift_number'      => 1,
                    'headcount'         => 5,
                    'start_time'        => '07:00',
                    'work_hours'        => 8.5,
                    'prep_minutes'      => 0,
                    'break1_start'      => '09:30',
                    'break1_minutes'    => 30,
                    'meal_break_start'  => '12:00',
                    'meal_break_minutes'=> 15,
                    'break2_start'      => '14:30',
                    'break2_minutes'    => 15,
                    'break3_start'      => '17:00',
                    'break3_minutes'    => 15,
                ],
            ],
        ];
    }

    // ── List ─────────────────────────────────────────────

    public function testListReturnsOk(): void
    {
        $this->createTemplate();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListShiftTemplatesController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->has('meta')
                ->etc(),
            );
    }

    public function testListIncludesDetails(): void
    {
        $this->createTemplate();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListShiftTemplatesController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.0.details.data', 2)
                ->etc(),
            );
    }

    public function testListSearchByName(): void
    {
        $this->createTemplate(['name' => 'Ca chuẩn - tăng ca']);
        $this->createTemplate(['name' => 'Ca chuẩn - ngày lễ']);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListShiftTemplatesController::class) . '?search=name:tăng ca');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 1)
                ->where('data.0.name', 'Ca chuẩn - tăng ca')
                ->etc(),
            );
    }

    public function testListUnauthenticatedReturns401(): void
    {
        $response = $this->getJson(URL::action(ListShiftTemplatesController::class));

        $response->assertUnauthorized();
    }

    // ── Find ─────────────────────────────────────────────

    public function testFindReturnsOk(): void
    {
        $template = $this->createTemplate();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(FindShiftTemplateController::class, ['id' => $template->getHashedKey()]));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.name', 'Ca chuẩn - bình thường')
                ->has('data.details.data', 2)
                ->etc(),
            );
    }

    public function testFindIncludesEndTime(): void
    {
        $template = $this->createTemplate();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(FindShiftTemplateController::class, ['id' => $template->getHashedKey()]));

        $response->assertOk();
        $details = $response->json('data.details.data');
        // dept1: start 06:30 + 8.5h = 15:00
        $this->assertSame('15:00', $details[0]['end_time']);
    }

    // ── Create ───────────────────────────────────────────

    public function testCreateReturnsCreated(): void
    {
        $data = $this->validCreatePayload();

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateShiftTemplateController::class), $data);

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.name', 'Ca chuẩn - tăng ca')
                ->where('data.color', '#FF0000')
                ->where('data.status', 'active')
                ->has('data.details.data', 2)
                ->etc(),
            );

        $this->assertDatabaseHas('shift_templates', ['name' => 'Ca chuẩn - tăng ca']);
        $this->assertDatabaseCount('shift_template_details', 2);
    }

    public function testCreateValidationRequiresName(): void
    {
        $data = $this->validCreatePayload();
        unset($data['name']);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateShiftTemplateController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function testCreateValidationRequiresStartTime(): void
    {
        $data = $this->validCreatePayload();
        unset($data['details'][0]['start_time']);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateShiftTemplateController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('details.0.start_time');
    }

    // ── Update ───────────────────────────────────────────

    public function testUpdateReturnsOk(): void
    {
        $template = $this->createTemplate();

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateShiftTemplateController::class, ['id' => $template->getHashedKey()]), [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', 'Updated Name')
                ->etc(),
            );
    }

    public function testUpdateSyncsDetails(): void
    {
        $template = $this->createTemplate();
        $this->assertDatabaseCount('shift_template_details', 2);

        // Update with only 1 detail — should replace all
        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateShiftTemplateController::class, ['id' => $template->getHashedKey()]), [
                'details' => [
                    [
                        'department_id' => $this->dept1->id,
                        'shift_number'  => 1,
                        'headcount'     => 10,
                        'start_time'    => '06:00',
                        'work_hours'    => 8,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.details.data', 1)
                ->etc(),
            );

        $this->assertDatabaseCount('shift_template_details', 1);
    }

    // ── Delete ───────────────────────────────────────────

    public function testDeleteReturnsNoContent(): void
    {
        $template = $this->createTemplate();

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteShiftTemplateController::class, ['id' => $template->getHashedKey()]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('shift_templates', ['id' => $template->id]);
    }

    public function testDeleteCascadesDetails(): void
    {
        $template = $this->createTemplate();
        $this->assertDatabaseCount('shift_template_details', 2);

        $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteShiftTemplateController::class, ['id' => $template->getHashedKey()]));

        $this->assertDatabaseCount('shift_template_details', 0);
    }

    // ── Copy ─────────────────────────────────────────────

    public function testCopyReturnsCreated(): void
    {
        $template = $this->createTemplate();

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CopyShiftTemplateController::class, ['id' => $template->getHashedKey()]));

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', 'Ca chuẩn - bình thường (Copy)')
                ->has('data.details.data', 2)
                ->etc(),
            );

        $this->assertDatabaseCount('shift_templates', 2);
        $this->assertDatabaseCount('shift_template_details', 4); // 2 original + 2 copied
    }

    // ── Reorder ──────────────────────────────────────────

    public function testReorderUpdatesOrders(): void
    {
        $t1 = $this->createTemplate(['name' => 'A', 'sort_order' => 1]);
        $t2 = $this->createTemplate(['name' => 'B', 'sort_order' => 2]);

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(ReorderShiftTemplatesController::class), [
                'items' => [
                    ['id' => $t1->id, 'sort_order' => 2],
                    ['id' => $t2->id, 'sort_order' => 1],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(2, $t1->fresh()->sort_order);
        $this->assertSame(1, $t2->fresh()->sort_order);
    }

    // ── Permission ───────────────────────────────────────

    public function testCreateWithoutPermissionReturns403(): void
    {
        $userNoPerms = User::factory()->createOne();

        $response = $this->actingAs($userNoPerms, 'api')
            ->postJson(URL::action(CreateShiftTemplateController::class), $this->validCreatePayload());

        $response->assertForbidden();
    }

    public function testDeleteWithoutPermissionReturns403(): void
    {
        $template = $this->createTemplate();
        $userNoPerms = User::factory()->createOne();

        $response = $this->actingAs($userNoPerms, 'api')
            ->deleteJson(URL::action(DeleteShiftTemplateController::class, ['id' => $template->getHashedKey()]));

        $response->assertForbidden();
    }
}
