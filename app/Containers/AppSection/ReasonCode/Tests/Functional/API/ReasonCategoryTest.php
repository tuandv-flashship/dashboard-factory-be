<?php

namespace App\Containers\AppSection\ReasonCode\Tests\Functional\API;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\CreateReasonCategoryController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\DeleteReasonCategoryController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\FindReasonCategoryController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ListReasonCategoriesController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ReorderReasonCategoriesController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\UpdateReasonCategoryController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListReasonCategoriesController::class)]
#[CoversClass(FindReasonCategoryController::class)]
#[CoversClass(CreateReasonCategoryController::class)]
#[CoversClass(UpdateReasonCategoryController::class)]
#[CoversClass(DeleteReasonCategoryController::class)]
#[CoversClass(ReorderReasonCategoriesController::class)]
final class ReasonCategoryTest extends ApiTestCase
{
    private User $admin;

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
    }

    // ── List ─────────────────────────────────────────────

    public function testListCategoriesReturnsOk(): void
    {
        // Seeder already creates 4 categories (machine, human, material, process)
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonCategoriesController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->has('meta')
                ->etc(),
            );
    }

    public function testListCategoriesSearchByCode(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonCategoriesController::class) . '?search=code:machine');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 1)
                ->where('data.0.code', 'machine')
                ->etc(),
            );
    }

    public function testListCategoriesSearchByLabel(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonCategoriesController::class) . '?search=label:Máy');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 1)
                ->where('data.0.code', 'machine')
                ->etc(),
            );
    }

    public function testListCategoriesUnauthenticatedReturns401(): void
    {
        $response = $this->getJson(URL::action(ListReasonCategoriesController::class));

        $response->assertUnauthorized();
    }

    public function testListCategoriesWithIncludeSubItems(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonCategoriesController::class) . '?search=code:machine&include=sub_items');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.0.sub_items.data')
                ->etc(),
            );
    }

    public function testListCategoriesWithIncludeErrors(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonCategoriesController::class) . '?search=code:machine&include=errors');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.0.errors.data')
                ->etc(),
            );
    }

    public function testListCategoriesPagination(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonCategoriesController::class) . '?limit=2&page=1');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 2)
                ->where('meta.pagination.per_page', 2)
                ->etc(),
            );
    }

    public function testListCategoriesOrderBy(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonCategoriesController::class) . '?orderBy=code&sortedBy=desc');

        $response->assertOk();
        $data = $response->json('data');
        // Should sort codes descending: process, process, material, machine, human
        $this->assertTrue($data[0]['code'] >= $data[1]['code']);
    }

    // ── Find ─────────────────────────────────────────────

    public function testFindCategoryReturnsOk(): void
    {
        $category = ReasonCategory::where('code', 'machine')->first();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(FindReasonCategoryController::class, ['id' => $category->getHashedKey()]));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.code', 'machine')
                ->where('data.label', 'Máy móc')
                ->etc(),
            );
    }

    public function testFindCategoryWithInclude(): void
    {
        $category = ReasonCategory::where('code', 'machine')->first();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(FindReasonCategoryController::class, ['id' => $category->getHashedKey()])
                . '?include=sub_items,errors');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.sub_items.data')
                ->has('data.errors.data')
                ->etc(),
            );
    }


    // ── Create ───────────────────────────────────────────

    public function testCreateCategoryReturnsCreated(): void
    {
        $data = [
            'code' => 'test-new-cat',
            'label' => 'Test Category',
            'label_en' => 'Test Category EN',
            'icon' => 'Star',
            'color' => '#00ff00',
            'sort_order' => 99,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateReasonCategoryController::class), $data);

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.code', 'test-new-cat')
                ->where('data.label', 'Test Category')
                ->where('data.icon', 'Star')
                ->etc(),
            );
        $this->assertDatabaseHas('reason_categories', ['code' => 'test-new-cat']);
    }

    public function testCreateCategoryValidationRequiresCode(): void
    {
        $data = [
            'label' => 'No Code',
            'label_en' => 'No Code EN',
            'icon' => 'Star',
            'color' => '#00ff00',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateReasonCategoryController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function testCreateCategoryDuplicateCodeFails(): void
    {
        $data = [
            'code' => 'machine', // already exists from seeder
            'label' => 'Duplicate',
            'label_en' => 'Dup EN',
            'icon' => 'Star',
            'color' => '#00ff00',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateReasonCategoryController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    // ── Update ───────────────────────────────────────────

    public function testUpdateCategoryReturnsOk(): void
    {
        $category = ReasonCategory::where('code', 'machine')->first();

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateReasonCategoryController::class, ['id' => $category->getHashedKey()]), [
                'label' => 'Máy móc Updated',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.label', 'Máy móc Updated')
                ->where('data.is_active', false)
                ->where('data.code', 'machine') // unchanged
                ->etc(),
            );
    }

    // ── Delete ───────────────────────────────────────────

    public function testDeleteCategoryReturnsNoContent(): void
    {
        $category = ReasonCategory::create([
            'code' => 'test-delete', 'label' => 'Delete Me', 'label_en' => 'Delete EN',
            'icon' => 'Trash', 'color' => '#ff0000', 'sort_order' => 99,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteReasonCategoryController::class, ['id' => $category->getHashedKey()]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('reason_categories', ['id' => $category->id]);
    }

    public function testDeleteCategoryCascadesChildren(): void
    {
        $category = ReasonCategory::create([
            'code' => 'test-cascade', 'label' => 'Cascade', 'label_en' => 'Cascade EN',
            'icon' => 'Trash', 'color' => '#ff0000', 'sort_order' => 99,
        ]);
        ReasonSubItem::create([
            'category_id' => $category->id, 'code' => 'test-child-sub', 'label' => 'Child Sub',
            'scope_type' => 'global', 'sort_order' => 0,
        ]);
        ReasonError::create([
            'category_id' => $category->id, 'code' => 'test-child-err', 'label' => 'Child Err',
            'sort_order' => 0,
        ]);

        $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteReasonCategoryController::class, ['id' => $category->getHashedKey()]));

        $this->assertDatabaseMissing('reason_sub_items', ['code' => 'test-child-sub']);
        $this->assertDatabaseMissing('reason_errors', ['code' => 'test-child-err']);
    }

    // ── Reorder ──────────────────────────────────────────

    public function testReorderCategoriesReturnsOk(): void
    {
        $cat1 = ReasonCategory::where('code', 'machine')->first();
        $cat2 = ReasonCategory::where('code', 'human')->first();

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(ReorderReasonCategoriesController::class), [
                'items' => [
                    ['id' => $cat1->id, 'sort_order' => 10],
                    ['id' => $cat2->id, 'sort_order' => 20],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(10, $cat1->fresh()->sort_order);
        $this->assertSame(20, $cat2->fresh()->sort_order);
    }

    // ── Permission ───────────────────────────────────────

    public function testCreateWithoutPermissionReturns403(): void
    {
        $userNoPerms = User::factory()->createOne();

        $response = $this->actingAs($userNoPerms, 'api')
            ->postJson(URL::action(CreateReasonCategoryController::class), [
                'code' => 'test-unauth', 'label' => 'x', 'label_en' => 'x',
                'icon' => 'x', 'color' => '#000',
            ]);

        $response->assertForbidden();
    }
}
