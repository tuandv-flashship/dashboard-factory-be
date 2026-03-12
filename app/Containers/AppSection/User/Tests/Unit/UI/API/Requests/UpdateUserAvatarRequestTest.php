<?php

namespace App\Containers\AppSection\User\Tests\Unit\UI\API\Requests;

use App\Containers\AppSection\User\Tests\UnitTestCase;
use App\Containers\AppSection\User\UI\API\Requests\UpdateUserAvatarRequest;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateUserAvatarRequest::class)]
final class UpdateUserAvatarRequestTest extends UnitTestCase
{
    private UpdateUserAvatarRequest $request;

    public function testDecode(): void
    {
        $this->assertSame([
            'user_id',
        ], $this->request->getDecode());
    }

    public function testValidationRules(): void
    {
        $rules = $this->request->rules();

        $this->assertSame([
            'avatar' => ['required', 'image', 'max:5120'],
        ], $rules);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new UpdateUserAvatarRequest();
    }
}
