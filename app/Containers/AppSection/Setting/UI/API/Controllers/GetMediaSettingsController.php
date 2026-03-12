<?php

namespace App\Containers\AppSection\Setting\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Setting\Actions\GetMediaSettingsAction;
use App\Containers\AppSection\Setting\UI\API\Requests\GetMediaSettingsRequest;
use App\Containers\AppSection\Setting\UI\API\Transformers\MediaSettingsTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class GetMediaSettingsController extends ApiController
{
    public function __invoke(GetMediaSettingsRequest $request, GetMediaSettingsAction $action): JsonResponse
    {
        $settings = (object) $action->run();

        return Response::create()
            ->item($settings, MediaSettingsTransformer::class)
            ->addMeta([
                'options' => $this->buildOptions(),
            ])
            ->ok();
    }

    private function buildOptions(): array
    {
        $sizes = (array) config('media.sizes', []);
        $sizeOptions = [];

        foreach ($sizes as $name => $default) {
            $sizeOptions[] = [
                'name' => $name,
                'label' => $this->resolveSizeLabel((string) $name),
                'default' => $default,
            ];
        }

        return [
            'drivers' => [
                ['value' => 'public', 'label' => __('media.options.drivers.public')],
                ['value' => 'local', 'label' => __('media.options.drivers.local')],
                ['value' => 's3', 'label' => __('media.options.drivers.s3')],
                ['value' => 'r2', 'label' => __('media.options.drivers.r2')],
                ['value' => 'do_spaces', 'label' => __('media.options.drivers.do_spaces')],
                ['value' => 'wasabi', 'label' => __('media.options.drivers.wasabi')],
                ['value' => 'bunnycdn', 'label' => __('media.options.drivers.bunnycdn')],
                ['value' => 'backblaze', 'label' => __('media.options.drivers.backblaze')],
            ],
            'thumbnail_crop_positions' => [
                ['value' => 'left', 'label' => __('media.options.thumbnail_crop_positions.left')],
                ['value' => 'right', 'label' => __('media.options.thumbnail_crop_positions.right')],
                ['value' => 'top', 'label' => __('media.options.thumbnail_crop_positions.top')],
                ['value' => 'bottom', 'label' => __('media.options.thumbnail_crop_positions.bottom')],
                ['value' => 'center', 'label' => __('media.options.thumbnail_crop_positions.center')],
            ],
            'watermark_positions' => [
                ['value' => 'top-left', 'label' => __('media.options.watermark_positions.top_left')],
                ['value' => 'top-right', 'label' => __('media.options.watermark_positions.top_right')],
                ['value' => 'bottom-left', 'label' => __('media.options.watermark_positions.bottom_left')],
                ['value' => 'bottom-right', 'label' => __('media.options.watermark_positions.bottom_right')],
                ['value' => 'center', 'label' => __('media.options.watermark_positions.center')],
            ],
            'image_processing_libraries' => [
                ['value' => 'gd', 'label' => __('media.options.image_processing_libraries.gd')],
            ],
            'boolean' => [
                ['value' => 1, 'label' => __('media.options.boolean.on')],
                ['value' => 0, 'label' => __('media.options.boolean.off')],
            ],
            'sizes' => $sizeOptions,
        ];
    }

    private function resolveSizeLabel(string $name): string
    {
        $key = 'media.options.sizes.' . $name;
        $label = __($key);

        if ($label === $key) {
            $label = Str::headline(str_replace(['_', '-'], ' ', $name));
        }

        return $label;
    }
}
