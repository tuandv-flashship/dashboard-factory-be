<?php

namespace App\Containers\AppSection\User\Models;

use App\Containers\AppSection\Authorization\Enums\Role as RoleEnum;
use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\User\Data\Collections\UserCollection;
use App\Containers\AppSection\User\Enums\Gender;
use App\Containers\AppSection\User\Enums\UserStatus;
use App\Ship\Parents\Models\UserModel as ParentUserModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class User extends ParentUserModel
{
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'gender',
        'birth',
        'avatar_id',
        'phone',
        'description',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'immutable_datetime',
        'password' => 'hashed',
        'gender' => Gender::class,
        'birth' => 'immutable_date',
        'status' => UserStatus::class,
    ];

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'avatar_id');
    }

    public function newCollection(array $models = []): UserCollection
    {
        return new UserCollection($models);
    }

    /**
     * Allows Passport to find the user by email or username (case-insensitive).
     * Uses filter_var to detect email format, then queries the appropriate column first.
     */
    public function findForPassport(string $username): self|null
    {
        $identifier = strtolower($username);
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

        // Query the most likely column first for optimal index usage
        return self::query()->where($isEmail ? 'email' : 'username', $identifier)->first()
            ?? self::query()->where($isEmail ? 'username' : 'email', $identifier)->first();
    }

    public function isSuperAdmin(): bool
    {
        if ($this->email && in_array($this->email, config('appSection-authorization.super_admins', []))) {
            return true;
        }

        if (!$this->hasRole(RoleEnum::SUPER_ADMIN)) {
            return false;
        }

        return true;
    }

    protected function email(): Attribute
    {
        return new Attribute(
            get: static fn (string|null $value): string|null => is_null($value) ? null : strtolower($value),
            set: static fn (string|null $value): string|null => is_null($value) ? null : strtolower($value),
        );
    }

    protected function username(): Attribute
    {
        return new Attribute(
            get: static fn (string|null $value): string|null => is_null($value) ? null : strtolower($value),
            set: static fn (string|null $value): string|null => is_null($value) ? null : strtolower($value),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->username) && $user->email) {
                $user->username = self::generateUniqueUsername($user->email);
            }
        });
    }

    /**
     * Derive a unique username from an email address.
     * e.g. "nguyen.van.a@fls.local" → "nguyen.van.a"
     * If taken, appends numeric suffix: "nguyen.van.a.2"
     */
    public static function generateUniqueUsername(string $email): string
    {
        $base = strtolower(explode('@', $email)[0]);
        $candidate = $base;
        $suffix = 2;

        while (self::query()->where('username', $candidate)->exists()) {
            $candidate = "{$base}.{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
