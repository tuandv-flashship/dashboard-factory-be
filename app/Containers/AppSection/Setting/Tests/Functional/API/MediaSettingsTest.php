<?php

namespace App\Containers\AppSection\Setting\Tests\Functional\API;

use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Containers\AppSection\Setting\Models\Setting;
use App\Containers\AppSection\Setting\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\Setting\UI\API\Controllers\GetMediaSettingsController;
use App\Containers\AppSection\Setting\UI\API\Controllers\UpdateMediaSettingsController;
use App\Containers\AppSection\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetMediaSettingsController::class)]
#[CoversClass(UpdateMediaSettingsController::class)]
final class MediaSettingsTest extends ApiTestCase
{
    public function testMediaSettingsMaskSecrets(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());

        Setting::query()->create([
            'key' => 'media_aws_secret_key',
            'value' => 'secret-value',
        ]);

        app(MediaSettingsStore::class)->clear();

        $response = $this->getJson(action(GetMediaSettingsController::class));

        $response->assertOk();
        $response->assertJsonPath('data.media_aws_secret_key', '********');
    }

    public function testMaskedSecretDoesNotOverwrite(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());

        Setting::query()->updateOrCreate(
            ['key' => 'media_aws_secret_key'],
            ['value' => 'original-secret']
        );

        app(MediaSettingsStore::class)->clear();

        $response = $this->patchJson(action(UpdateMediaSettingsController::class), [
            'media_aws_secret_key' => '********',
        ]);

        $response->assertOk();
        $this->assertSame(
            'original-secret',
            Setting::query()->where('key', 'media_aws_secret_key')->value('value')
        );
    }
}
