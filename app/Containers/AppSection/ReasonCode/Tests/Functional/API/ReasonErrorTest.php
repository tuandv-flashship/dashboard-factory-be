<?php

namespace App\Containers\AppSection\ReasonCode\Tests\Functional\API;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\CreateReasonErrorController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\DeleteReasonErrorController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ListReasonErrorsController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ReorderReasonErrorsController;
use App\Containers\AppSection\ReasonCode\UI\API\Controllers\UpdateReasonErrorController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListReasonErrorsController::class)]
#[CoversClass(CreateReasonErrorController::class)]
#[CoversClass(UpdateReasonErrorController::class)]
#[CoversClass(DeleteReasonErrorController::class)]
#[CoversClass(ReorderReasonErrorsController::class)]
final class ReasonErrorTest extends ApiTestCase
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

    public function testListErrorsReturnsOk(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonErrorsController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->has('meta')
                ->etc(),
            );
    }

    public function testListErrorsSearchByCategoryId(): void
    {
        $humanCat = ReasonCategory::where('code', 'human')->first();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonErrorsController::class) . '?search=category_id:' . $humanCat->id);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // Verify by including category and checking the code
        $responseWithCat = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonErrorsController::class) . '?search=category_id:' . $humanCat->id . '&include=category&limit=3');
        foreach ($responseWithCat->json('data') as $item) {
            $this->assertSame('human', $item['category']['data']['code']);
        }
    }

    public function testListErrorsSearchByScopeDept(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonErrorsController::class) . '?search=scope_dept:print');

        $response->assertOk();
        foreach ($response->json('data') as $item) {
            $this->assertSame('print', $item['scope_dept']);
        }
    }

    public function testListErrorsWithIncludeCategory(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListReasonErrorsController::class) . '?include=category&limit=5');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.0.category.data')
                ->etc(),
            );
    }

    public function testListErrorsUnauthenticatedReturns401(): void
    {
        $response = $this->getJson(URL::action(ListReasonErrorsController::class));

        $response->assertUnauthorized();
    }

    // ── Create ───────────────────────────────────────────

    public function testCreateErrorReturnsCreated(): void
    {
        $data = [
            'category_id' => $this->category->getHashedKey(),
            'code' => 'test-new-err',
            'label' => 'Test Error',
            'scope_dept' => null,
            'sort_order' => 99,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateReasonErrorController::class), $data);

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.code', 'test-new-err')
                ->where('data.label', 'Test Error')
                ->etc(),
            );
        $this->assertDatabaseHas('reason_errors', ['code' => 'test-new-err']);
    }

    public function testCreateErrorWithScopeDept(): void
    {
        $data = [
            'category_id' => $this->category->getHashedKey(),
            'code' => 'test-err-print',
            'label' => 'Print-specific Error',
            'scope_dept' => 'print',
            'sort_order' => 99,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateReasonErrorController::class), $data);

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.scope_dept', 'print')
                ->etc(),
            );
    }

    public function testCreateErrorValidationRequiresCode(): void
    {
        $data = [
            'category_id' => $this->category->getHashedKey(),
            'label' => 'No Code',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateReasonErrorController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    // ── Update ───────────────────────────────────────────

    public function testUpdateErrorReturnsOk(): void
    {
        $error = ReasonError::where('category_id', $this->category->id)->first();

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateReasonErrorController::class, ['id' => $error->getHashedKey()]), [
                'label' => 'Updated Error Label',
                'scope_dept' => 'cut',
            ]);

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.label', 'Updated Error Label')
                ->where('data.scope_dept', 'cut')
                ->etc(),
            );
    }

    // ── Delete ───────────────────────────────────────────

    public function testDeleteErrorReturnsNoContent(): void
    {
        $error = ReasonError::create([
            'category_id' => $this->category->id, 'code' => 'test-del-err', 'label' => 'Delete Me',
            'sort_order' => 99,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteReasonErrorController::class, ['id' => $error->getHashedKey()]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('reason_errors', ['id' => $error->id]);
    }

    // ── Reorder ──────────────────────────────────────────

    public function testReorderErrorsReturnsOk(): void
    {
        $errors = ReasonError::where('category_id', $this->category->id)
            ->orderBy('sort_order')
            ->take(2)
            ->get();

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(ReorderReasonErrorsController::class), [
                'items' => [
                    ['id' => $errors[0]->id, 'sort_order' => 100],
                    ['id' => $errors[1]->id, 'sort_order' => 50],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(100, $errors[0]->fresh()->sort_order);
        $this->assertSame(50, $errors[1]->fresh()->sort_order);
    }

    // ── Permission ───────────────────────────────────────

    public function testDeleteWithoutPermissionReturns403(): void
    {
        $userNoPerms = User::factory()->createOne();
        $error = ReasonError::where('category_id', $this->category->id)->first();

        $response = $this->actingAs($userNoPerms, 'api')
            ->deleteJson(URL::action(DeleteReasonErrorController::class, ['id' => $error->getHashedKey()]));

        $response->assertForbidden();
    }
}
