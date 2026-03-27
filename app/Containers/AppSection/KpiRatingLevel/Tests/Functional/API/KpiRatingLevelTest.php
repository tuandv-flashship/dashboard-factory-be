<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tests\Functional\API;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevelDetail;
use App\Containers\AppSection\KpiRatingLevel\Tasks\GetActiveKpiRatingLevelTask;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\CreateKpiRatingLevelController;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\DeleteKpiRatingLevelController;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\FindKpiRatingLevelController;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\GetActiveKpiRatingLevelController;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\ListKpiRatingLevelsController;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\UpdateKpiRatingLevelController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListKpiRatingLevelsController::class)]
#[CoversClass(FindKpiRatingLevelController::class)]
#[CoversClass(CreateKpiRatingLevelController::class)]
#[CoversClass(UpdateKpiRatingLevelController::class)]
#[CoversClass(DeleteKpiRatingLevelController::class)]
#[CoversClass(GetActiveKpiRatingLevelController::class)]
final class KpiRatingLevelTest extends ApiTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean slate for each test — prevent data leaking between tests
        KpiRatingLevelDetail::query()->delete();
        KpiRatingLevel::query()->delete();
        Cache::forget('kpi_rating_level_active');

        $this->admin = User::factory()->createOne();
        $this->admin->givePermissionTo([
            'kpi-rating-levels.index',
            'kpi-rating-levels.create',
            'kpi-rating-levels.edit',
            'kpi-rating-levels.destroy',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────

    private function createRatingLevel(array $overrides = []): KpiRatingLevel
    {
        $ratingLevel = KpiRatingLevel::create(array_merge([
            'name'           => 'Mức đánh giá Test',
            'effective_from' => '2025-01-01',
            'effective_until' => null,
            'description'    => 'Test description',
        ], $overrides));

        $defaults = [
            ['level_name' => 'Xuất sắc',   'bg_color' => '#006400', 'text_color' => '#FFFFFF', 'min_score' => 100, 'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 1],
            ['level_name' => 'Đạt',        'bg_color' => '#228B22', 'text_color' => '#FFFFFF', 'min_score' => 95,  'operator' => '>=', 'is_kpi_threshold' => true,  'is_staff_warning_threshold' => false, 'sort_order' => 2],
            ['level_name' => 'Trung bình', 'bg_color' => '#DAA520', 'text_color' => '#FFFFFF', 'min_score' => 90,  'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => true,  'sort_order' => 3],
            ['level_name' => 'Yếu',        'bg_color' => '#8B4513', 'text_color' => '#FFFFFF', 'min_score' => 85,  'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 4],
            ['level_name' => 'Chưa đạt',   'bg_color' => '#8B0000', 'text_color' => '#FFFFFF', 'min_score' => 85,  'operator' => '<',  'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 5],
        ];

        foreach ($defaults as $detail) {
            KpiRatingLevelDetail::create(array_merge($detail, ['rating_level_id' => $ratingLevel->id]));
        }

        return $ratingLevel->load('details');
    }

    private function validCreatePayload(): array
    {
        return [
            'name'           => 'Mức đánh giá 2026',
            'effective_from' => '2026-04-01',
            'effective_until' => null,
            'description'    => 'Áp dụng từ Q2/2026',
            'details'        => [
                ['level_name' => 'Xuất sắc',   'bg_color' => '#006400', 'text_color' => '#FFFFFF', 'min_score' => 100, 'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 1],
                ['level_name' => 'Đạt',        'bg_color' => '#228B22', 'text_color' => '#FFFFFF', 'min_score' => 95,  'operator' => '>=', 'is_kpi_threshold' => true,  'is_staff_warning_threshold' => false, 'sort_order' => 2],
                ['level_name' => 'Trung bình', 'bg_color' => '#DAA520', 'text_color' => '#FFFFFF', 'min_score' => 90,  'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => true,  'sort_order' => 3],
                ['level_name' => 'Yếu',        'bg_color' => '#8B4513', 'text_color' => '#FFFFFF', 'min_score' => 85,  'operator' => '>=', 'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 4],
                ['level_name' => 'Chưa đạt',   'bg_color' => '#8B0000', 'text_color' => '#FFFFFF', 'min_score' => 85,  'operator' => '<',  'is_kpi_threshold' => false, 'is_staff_warning_threshold' => false, 'sort_order' => 5],
            ],
        ];
    }

    // ── List ─────────────────────────────────────────────

    public function testListReturnsOk(): void
    {
        $this->createRatingLevel();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListKpiRatingLevelsController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->has('meta')
                ->etc(),
            );
    }

    public function testListOrdersByEffectiveFromDesc(): void
    {
        $this->createRatingLevel(['name' => 'Old', 'effective_from' => '2023-01-01']);
        $this->createRatingLevel(['name' => 'New', 'effective_from' => '2026-01-01']);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListKpiRatingLevelsController::class));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('New', $data[0]['name']);
        $this->assertSame('Old', $data[1]['name']);
    }

    public function testListIncludesDetails(): void
    {
        $this->createRatingLevel();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListKpiRatingLevelsController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.0.details.data', 5)
                ->etc(),
            );
    }

    public function testListSearchByName(): void
    {
        $this->createRatingLevel(['name' => 'Mức đánh giá 2026']);
        $this->createRatingLevel(['name' => 'Mức đánh giá 2024']);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListKpiRatingLevelsController::class) . '?search=name:2026');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 1)
                ->where('data.0.name', 'Mức đánh giá 2026')
                ->etc(),
            );
    }

    public function testListPagination(): void
    {
        $this->createRatingLevel(['name' => 'A']);
        $this->createRatingLevel(['name' => 'B']);
        $this->createRatingLevel(['name' => 'C']);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(ListKpiRatingLevelsController::class) . '?limit=2&page=1');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 2)
                ->where('meta.pagination.per_page', 2)
                ->etc(),
            );
    }

    public function testListUnauthenticatedReturns401(): void
    {
        $response = $this->getJson(URL::action(ListKpiRatingLevelsController::class));

        $response->assertUnauthorized();
    }

    // ── Find ─────────────────────────────────────────────

    public function testFindReturnsOk(): void
    {
        $ratingLevel = $this->createRatingLevel();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(FindKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.name', 'Mức đánh giá Test')
                ->has('data.details.data', 5)
                ->etc(),
            );
    }

    public function testFindReturnsStatus(): void
    {
        // Active: effective_from in the past, no expiry
        $active = $this->createRatingLevel(['effective_from' => '2024-01-01', 'effective_until' => null]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(FindKpiRatingLevelController::class, ['id' => $active->getHashedKey()]));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.status', 'active')
                ->etc(),
            );
    }

    public function testFindReturnsExpiredStatus(): void
    {
        $expired = $this->createRatingLevel([
            'effective_from' => '2020-01-01',
            'effective_until' => '2021-12-31',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(FindKpiRatingLevelController::class, ['id' => $expired->getHashedKey()]));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.status', 'expired')
                ->etc(),
            );
    }

    public function testFindReturnsPendingStatus(): void
    {
        $pending = $this->createRatingLevel(['effective_from' => '2099-01-01']);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(URL::action(FindKpiRatingLevelController::class, ['id' => $pending->getHashedKey()]));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.status', 'pending')
                ->etc(),
            );
    }

    // ── Create ───────────────────────────────────────────

    public function testCreateReturnsCreated(): void
    {
        $data = $this->validCreatePayload();

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateKpiRatingLevelController::class), $data);

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.name', 'Mức đánh giá 2026')
                ->where('data.effective_from', '2026-04-01')
                ->has('data.details.data', 5)
                ->etc(),
            );

        $this->assertDatabaseHas('kpi_rating_levels', ['name' => 'Mức đánh giá 2026']);
        $this->assertDatabaseCount('kpi_rating_level_details', 5);
    }

    public function testCreateValidationRequiresName(): void
    {
        $data = $this->validCreatePayload();
        unset($data['name']);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateKpiRatingLevelController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function testCreateValidationRequiresEffectiveFrom(): void
    {
        $data = $this->validCreatePayload();
        unset($data['effective_from']);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateKpiRatingLevelController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('effective_from');
    }

    public function testCreateValidationRequiresDetails(): void
    {
        $data = $this->validCreatePayload();
        unset($data['details']);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateKpiRatingLevelController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('details');
    }

    public function testCreateValidationRejectsDuplicateLevelNames(): void
    {
        $data = $this->validCreatePayload();
        $data['details'][1]['level_name'] = $data['details'][0]['level_name']; // Duplicate "Xuất sắc"

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateKpiRatingLevelController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('details.1.level_name');
    }

    public function testCreateValidationRejectsEffectiveUntilBeforeFrom(): void
    {
        $data = $this->validCreatePayload();
        $data['effective_until'] = '2025-01-01'; // before effective_from

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateKpiRatingLevelController::class), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('effective_until');
    }

    public function testCreateClearsCacheForGetActive(): void
    {
        Cache::put('kpi_rating_level_active', 'cached_value', 300);

        $this->actingAs($this->admin, 'api')
            ->postJson(URL::action(CreateKpiRatingLevelController::class), $this->validCreatePayload());

        $this->assertNull(Cache::get('kpi_rating_level_active'));
    }

    // ── Update ───────────────────────────────────────────

    public function testUpdateReturnsOk(): void
    {
        $ratingLevel = $this->createRatingLevel();

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]), [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', 'Updated Name')
                ->etc(),
            );
    }

    public function testUpdatePartialDoesNotWipeOtherFields(): void
    {
        $ratingLevel = $this->createRatingLevel([
            'name'           => 'Original',
            'effective_from' => '2025-01-01',
            'description'    => 'Keep this',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]), [
                'name' => 'Updated',
            ]);

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', 'Updated')
                ->where('data.effective_from', '2025-01-01')
                ->where('data.description', 'Keep this')
                ->etc(),
            );
    }

    public function testUpdateCanSetEffectiveUntilToNull(): void
    {
        $ratingLevel = $this->createRatingLevel(['effective_until' => '2025-12-31']);

        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]), [
                'effective_until' => null,
            ]);

        $response->assertOk();
        $this->assertNull($ratingLevel->fresh()->effective_until);
    }

    public function testUpdateSyncsDetails(): void
    {
        $ratingLevel = $this->createRatingLevel();
        $this->assertDatabaseCount('kpi_rating_level_details', 5);

        // Update with only 3 details — should replace all 5 old ones
        $response = $this->actingAs($this->admin, 'api')
            ->patchJson(URL::action(UpdateKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]), [
                'details' => [
                    ['level_name' => 'Tốt',      'bg_color' => '#006400', 'text_color' => '#FFFFFF', 'min_score' => 90, 'operator' => '>=', 'sort_order' => 1],
                    ['level_name' => 'Trung bình', 'bg_color' => '#DAA520', 'text_color' => '#FFFFFF', 'min_score' => 70, 'operator' => '>=', 'sort_order' => 2],
                    ['level_name' => 'Kém',       'bg_color' => '#8B0000', 'text_color' => '#FFFFFF', 'min_score' => 70, 'operator' => '<',  'sort_order' => 3],
                ],
            ]);

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.details.data', 3)
                ->etc(),
            );

        // Old details should be gone, new ones created
        $this->assertDatabaseCount('kpi_rating_level_details', 3);
    }

    // ── Delete ───────────────────────────────────────────

    public function testDeleteReturnsNoContent(): void
    {
        $ratingLevel = $this->createRatingLevel();

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('kpi_rating_levels', ['id' => $ratingLevel->id]);
    }

    public function testDeleteCascadesDetails(): void
    {
        $ratingLevel = $this->createRatingLevel();
        $this->assertDatabaseCount('kpi_rating_level_details', 5);

        $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]));

        $this->assertDatabaseCount('kpi_rating_level_details', 0);
    }

    public function testDeleteClearsCacheForGetActive(): void
    {
        $ratingLevel = $this->createRatingLevel();
        Cache::put('kpi_rating_level_active', 'cached_value', 300);

        $this->actingAs($this->admin, 'api')
            ->deleteJson(URL::action(DeleteKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]));

        $this->assertNull(Cache::get('kpi_rating_level_active'));
    }

    // ── GetActive (Public) ──────────────────────────────

    public function testGetActiveReturnsActiveLevelWithoutAuth(): void
    {
        $this->createRatingLevel([
            'name'           => 'Active Level',
            'effective_from' => '2024-01-01',
            'effective_until' => null,
        ]);

        $response = $this->getJson(URL::action(GetActiveKpiRatingLevelController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data')
                ->where('data.name', 'Active Level')
                ->where('data.status', 'active')
                ->has('data.details.data', 5)
                ->etc(),
            );
    }

    public function testGetActiveReturnsLatestWhenMultipleActive(): void
    {
        $this->createRatingLevel([
            'name'           => 'Old Active',
            'effective_from' => '2023-01-01',
            'effective_until' => null,
        ]);
        $this->createRatingLevel([
            'name'           => 'New Active',
            'effective_from' => '2025-01-01',
            'effective_until' => null,
        ]);

        GetActiveKpiRatingLevelTask::clearCache();

        $response = $this->getJson(URL::action(GetActiveKpiRatingLevelController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', 'New Active')
                ->etc(),
            );
    }

    public function testGetActiveReturnsDefaultWhenNoneActive(): void
    {
        // No records in DB at all
        $response = $this->getJson(URL::action(GetActiveKpiRatingLevelController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', 'Mặc định')
                ->has('data.details', 5)
                ->etc(),
            );
    }

    public function testGetActiveDoesNotReturnExpired(): void
    {
        $this->createRatingLevel([
            'name'           => 'Expired',
            'effective_from' => '2020-01-01',
            'effective_until' => '2021-12-31',
        ]);

        GetActiveKpiRatingLevelTask::clearCache();

        $response = $this->getJson(URL::action(GetActiveKpiRatingLevelController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', 'Mặc định') // fallback since expired
                ->etc(),
            );
    }

    public function testGetActiveDoesNotReturnPending(): void
    {
        $this->createRatingLevel([
            'name'           => 'Future',
            'effective_from' => '2099-01-01',
            'effective_until' => null,
        ]);

        GetActiveKpiRatingLevelTask::clearCache();

        $response = $this->getJson(URL::action(GetActiveKpiRatingLevelController::class));

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', 'Mặc định') // fallback since pending
                ->etc(),
            );
    }

    // ── Permission ───────────────────────────────────────

    public function testCreateWithoutPermissionReturns403(): void
    {
        $userNoPerms = User::factory()->createOne();

        $response = $this->actingAs($userNoPerms, 'api')
            ->postJson(URL::action(CreateKpiRatingLevelController::class), $this->validCreatePayload());

        $response->assertForbidden();
    }

    public function testDeleteWithoutPermissionReturns403(): void
    {
        $ratingLevel = $this->createRatingLevel();
        $userNoPerms = User::factory()->createOne();

        $response = $this->actingAs($userNoPerms, 'api')
            ->deleteJson(URL::action(DeleteKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]));

        $response->assertForbidden();
    }

    public function testUpdateWithoutPermissionReturns403(): void
    {
        $ratingLevel = $this->createRatingLevel();
        $userNoPerms = User::factory()->createOne();

        $response = $this->actingAs($userNoPerms, 'api')
            ->patchJson(URL::action(UpdateKpiRatingLevelController::class, ['id' => $ratingLevel->getHashedKey()]), [
                'name' => 'Should Fail',
            ]);

        $response->assertForbidden();
    }
}
