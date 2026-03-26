<?php

namespace App\Containers\AppSection\Media\Supports;

use App\Containers\AppSection\Media\Services\ImageProcessingService;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Containers\AppSection\Media\Services\MediaValidationService;
use App\Containers\AppSection\Media\Services\ThumbnailService;

final class MediaRuntimeServices
{
    private static MediaSettingsStore|null $settingsStore = null;
    private static ThumbnailService|null $thumbnailService = null;
    private static ImageProcessingService|null $imageProcessingService = null;
    private static MediaValidationService|null $validationService = null;
    private static MediaService|null $mediaService = null;

    public static function settingsStore(): MediaSettingsStore
    {
        if (self::$settingsStore === null) {
            self::$settingsStore = new MediaSettingsStore();
        }

        return self::$settingsStore;
    }

    public static function thumbnailService(): ThumbnailService
    {
        if (self::$thumbnailService === null) {
            self::$thumbnailService = new ThumbnailService(self::settingsStore());
        }

        return self::$thumbnailService;
    }

    public static function imageProcessingService(): ImageProcessingService
    {
        if (self::$imageProcessingService === null) {
            self::$imageProcessingService = new ImageProcessingService(self::settingsStore());
        }

        return self::$imageProcessingService;
    }

    public static function validationService(): MediaValidationService
    {
        if (self::$validationService === null) {
            self::$validationService = new MediaValidationService(self::settingsStore());
        }

        return self::$validationService;
    }

    public static function mediaService(): MediaService
    {
        if (self::$mediaService === null) {
            self::$mediaService = new MediaService(
                self::settingsStore(),
                self::thumbnailService(),
                self::imageProcessingService(),
                self::validationService(),
            );
        }

        return self::$mediaService;
    }

    public static function reset(): void
    {
        self::$mediaService = null;
        self::$thumbnailService = null;
        self::$imageProcessingService = null;
        self::$validationService = null;
        self::$settingsStore = null;
    }
}
