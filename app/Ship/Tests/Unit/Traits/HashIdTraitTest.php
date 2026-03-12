<?php

namespace App\Ship\Tests\Unit\Traits;

use App\Ship\Tests\ShipTestCase;
use App\Ship\Traits\HashIdTrait;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HashIdTrait::class)]
final class HashIdTraitTest extends ShipTestCase
{
    private bool $originalHashIdEnabled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalHashIdEnabled = (bool) config('apiato.hash-id');
    }

    protected function tearDown(): void
    {
        config(['apiato.hash-id' => $this->originalHashIdEnabled]);
        parent::tearDown();
    }

    public function testHashIdReturnsNullForNullInput(): void
    {
        $probe = $this->probe();

        $this->assertNull($probe->transform(null));
    }

    public function testHashIdReturnsNumericIdAsIntegerWhenHashIdDisabled(): void
    {
        config(['apiato.hash-id' => false]);
        $probe = $this->probe();

        $this->assertSame(123, $probe->transform(123));
        $this->assertSame(123, $probe->transform('123'));
    }

    public function testHashIdEncodesIntegerAndNumericStringWhenHashIdEnabled(): void
    {
        config(['apiato.hash-id' => true]);
        $probe = $this->probe();
        $expected = hashids()->encodeOrFail(123);

        $this->assertSame($expected, $probe->transform(123));
        $this->assertSame($expected, $probe->transform('123'));
    }

    public function testHashIdKeepsNonIntegerStringsUntouched(): void
    {
        config(['apiato.hash-id' => true]);
        $probe = $this->probe();

        $this->assertSame('12.5', $probe->transform('12.5'));
        $this->assertSame('abc123', $probe->transform('abc123'));
    }

    private function probe(): object
    {
        return new class {
            use HashIdTrait;

            public function transform(int|string|null $id): int|string|null
            {
                return $this->hashId($id);
            }
        };
    }
}
