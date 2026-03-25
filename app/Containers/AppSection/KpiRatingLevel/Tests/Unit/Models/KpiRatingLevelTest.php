<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tests\Unit\Models;

use App\Containers\AppSection\KpiRatingLevel\Enums\KpiRatingLevelStatus;
use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\Tests\UnitTestCase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(KpiRatingLevel::class)]
final class KpiRatingLevelTest extends UnitTestCase
{
    #[DataProvider('statusProvider')]
    public function testComputedStatus(string $effectiveFrom, ?string $effectiveUntil, KpiRatingLevelStatus $expected): void
    {
        $ratingLevel = KpiRatingLevel::create([
            'name'            => 'Test Status',
            'effective_from'  => $effectiveFrom,
            'effective_until' => $effectiveUntil,
        ]);

        $this->assertSame($expected, $ratingLevel->status);
    }

    public static function statusProvider(): array
    {
        return [
            'future start → pending' => [
                Carbon::tomorrow()->format('Y-m-d'),
                null,
                KpiRatingLevelStatus::PENDING,
            ],
            'past start, no end → active' => [
                Carbon::yesterday()->format('Y-m-d'),
                null,
                KpiRatingLevelStatus::ACTIVE,
            ],
            'today start, no end → active' => [
                Carbon::today()->format('Y-m-d'),
                null,
                KpiRatingLevelStatus::ACTIVE,
            ],
            'past start, future end → active' => [
                Carbon::yesterday()->format('Y-m-d'),
                Carbon::tomorrow()->format('Y-m-d'),
                KpiRatingLevelStatus::ACTIVE,
            ],
            'past start, today end → active' => [
                Carbon::yesterday()->format('Y-m-d'),
                Carbon::today()->format('Y-m-d'),
                KpiRatingLevelStatus::ACTIVE,
            ],
            'past start, past end → expired' => [
                '2020-01-01',
                '2021-12-31',
                KpiRatingLevelStatus::EXPIRED,
            ],
        ];
    }

    public function testDetailsRelationship(): void
    {
        $ratingLevel = KpiRatingLevel::create([
            'name'           => 'Relationship Test',
            'effective_from' => '2025-01-01',
        ]);

        $this->assertCount(0, $ratingLevel->details);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $ratingLevel->details());
    }
}
