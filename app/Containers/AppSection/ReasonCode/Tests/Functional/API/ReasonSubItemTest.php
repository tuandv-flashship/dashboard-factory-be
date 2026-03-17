<?php

namespace App\Containers\AppSection\ReasonCode\Tests\Functional\API;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\CreateReasonSubItemController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\DeleteReasonSubItemController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ListReasonSubItemsController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ReorderReasonSubItemsController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\UpdateReasonSubItemController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListReasonSubItemsController::class)]
#[CoversClass(CreateReasonSubItemController::class)]
#[CoversClass(UpdateReasonSubItemController::class)]
#[CoversClass(DeleteReasonSubItemController::class)]
#[CoversClass(ReorderReasonSubItemsController::class)]
final class ReasonSubItemTest extends ApiTestCase
{
    private User $admin;
    private ReasonCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->createOne();
        $this->admin->givePermissionTo([
            'reason-codes.index',
            'reason-codes.create',
            'reason-codes.edit',
            'reason-codes.destroy',
        ]);
        $this->category = ReasonCategory::where('code', 'machine')->first();
    }

    // ── List ─────────────────────────────────────────────

    public function testListSubItemsReturnsOk(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonSubItemsController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->has('meta')
                ->etc(),
            );
    }

    public function testListSubItemsSearchByCategoryId(): void
    {
        $humanCat = ReasonCategory::where('code', 'human')->first();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonSubItemsController::class) . '?search=category_id:' . $humanCat->id);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // Verify by including category and checking the code
        $responseWithCat = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonSubItemsController::class) . '?search=category_id:' . $humanCat->id . '&include=category&limit=3');
        foreach ($responseWithCat->json('data') as $item) {
            $this->assertSame('human', $item['category']['data']['code']);
        }
    }

    public function testListSubItemsSearchByScopeDept(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonSubItemsController::class) . '?search=scope_dept:print');

        $response->assertOk();
        foreach ($response->json('data') as $item) {
            $this->assertSame('print', $item['scope_dept']);
        }
    }

    public function testListSubItemsWithIncludeCategory(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonSubItemsController::class) . '?include=category&limit=5');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.0.category.data')
                ->etc(),
            );
    }

    public function testListSubItemsUnauthenticatedReturns401(): void
    {
        $response = $this->getJson(URL::action(ListReasonSubItemsController::class));

        $response->assertUnauthorized();
    }

    // ── Create ───────────────────────────────────────────

    public function testCreateSubItemReturnsCreated(): void
    {
        $data = [
            'category_id' => $this->category->getHashedKey(),
            'code' => 'test-new-sub',
            'label' => 'Test Sub Item',
            'scope_type' => 'per_line_department',
            'scope_line' => 'dtf1',
            'scope_dept' => 'print',
            'sort_order' => 99,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateReasonSubItemController::class), $data);

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.code', 'test-new-sub')
                ->where('data.scope_type', 'per_line_department')
                ->where('data.scope_line', 'dtf1')
                ->where('data.scope_dept', 'print')
                ->etc(),
            );
        $this->assertDatabaseHas('reason_sub_items', ['code' => 'test-new-sub']);
    }

    public function testCreateSubItemValidationRequiresScopeType(): void
    {
        $data = [
            'category_id' => $this->category->getHashedKey(),
            'code' => 'test-no-scope',
            'label' => 'Test',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateReasonSubItemController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('scope_type');
    }

    // ── Update ───────────────────────────────────────────

    public function testUpdateSubItemReturnsOk(): void
    {
        $subItem = ReasonSubItem::where('category_id', $this->category->id)->first();

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateReasonSubItemController::class, ['id' => $subItem->getHashedKey()]), [
                'label' => 'Updated Label',
            ]);

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.label', 'Updated Label')
                ->etc(),
            );
    }

    // ── Delete ───────────────────────────────────────────

    public function testDeleteSubItemReturnsNoContent(): void
    {
        $subItem = ReasonSubItem::create([
            'category_id' => $this->category->id, 'code' => 'test-del-sub', 'label' => 'Delete Me',
            'scope_type' => 'global', 'sort_order' => 99,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteReasonSubItemController::class, ['id' => $subItem->getHashedKey()]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('reason_sub_items', ['id' => $subItem->id]);
    }

    // ── Reorder ──────────────────────────────────────────

    public function testReorderSubItemsReturnsOk(): void
    {
        $items = ReasonSubItem::where('category_id', $this->category->id)
            ->orderBy('sort_order')
            ->take(2)
            ->get();

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(ReorderReasonSubItemsController::class), [
                'items' => [
                    ['id' => $items[0]->id, 'sort_order' => 100],
                    ['id' => $items[1]->id, 'sort_order' => 50],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(100, $items[0]->fresh()->sort_order);
        $this->assertSame(50, $items[1]->fresh()->sort_order);
    }
}
