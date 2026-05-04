<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

/**
 * Generic batch status polling endpoint.
 *
 * Returns progress information for any Bus::batch() job batch.
 * Placed in Shift container as its first consumer, but works for
 * any batch_id from any container (FPlatform sync, Production, etc.).
 */
final class GetBatchStatusController extends ApiController
{
    public function __invoke(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return response()->json(['message' => 'Batch not found.'], 404);
        }

        return response()->json([
            'id'             => $batch->id,
            'name'           => $batch->name,
            'total_jobs'     => $batch->totalJobs,
            'pending_jobs'   => $batch->pendingJobs,
            'failed_jobs'    => $batch->failedJobs,
            'processed_jobs' => $batch->processedJobs(),
            'progress'       => $batch->progress(),
            'finished'       => $batch->finished(),
            'has_failures'   => $batch->hasFailures(),
            'created_at'     => $batch->createdAt->toIso8601String(),
            'finished_at'    => $batch->finishedAt?->toIso8601String(),
        ]);
    }
}
