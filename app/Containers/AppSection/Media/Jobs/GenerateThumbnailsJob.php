<?php

namespace App\Containers\AppSection\Media\Jobs;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Services\ThumbnailService;
use App\Ship\Parents\Jobs\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateThumbnailsJob extends Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public MediaFile $file
    ) {
    }

    public function handle(ThumbnailService $thumbnailService): void
    {
        // Don't process if file is soft deleted (might happen if user deletes right after upload)
        if ($this->file->deleted_at) {
            return;
        }

        // Just use the existing service.
        $thumbnailService->generate($this->file);
    }
}
