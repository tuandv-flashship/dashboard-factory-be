<?php

namespace App\Containers\AppSection\User\Tests\Unit\UI\API\Requests;

use App\Containers\AppSection\User\Enums\Gender;
use App\Containers\AppSection\User\Enums\UserStatus;
use App\Containers\AppSection\User\Tests\UnitTestCase;
use App\Containers\AppSection\User\UI\API\Requests\CreateUserRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CreateUserRequest::class)]
final class CreateUserRequestTest extends UnitTestCase
{
    private CreateUserRequest $request;

    public function testDecode(): void
    {
        $this->assertSame([
            'role_ids.*',
        ], $this->request->getDecode());
    }

    public function testValidationRules(): void
    {
        $rules = $this->request->rules();

        $this->assertEquals([
            'name' => 'required|min:2|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                Password::default(),
                'confirmed',
            ],
            'gender' => [Rule::enum(Gender::class), 'nullable'],
            'birth' => ['date', 'nullable'],
            'phone' => ['string', 'max:20', 'nullable'],
            'description' => ['string', 'max:500', 'nullable'],
            'status' => [Rule::enum(UserStatus::class), 'nullable'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['required', 'exists:roles,id'],
        ], $rules);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new CreateUserRequest();
    }
}
