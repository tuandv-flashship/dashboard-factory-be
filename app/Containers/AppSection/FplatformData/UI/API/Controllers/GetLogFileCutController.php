<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Controllers;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Tasks\GetLogFileCutTask;
use App\Containers\AppSection\FplatformData\UI\API\Requests\GetLogFileCutRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Get log file cut data grouped by user from FPlatform.
 *
 * Private — requires auth (shifts.index permission or admin role).
 * Cache: 5 minutes.
 */
final class GetLogFileCutController extends ApiController
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly GetLogFileCutTask $task,
    ) {
    }

    public function __invoke(GetLogFileCutRequest $request): JsonResponse
    {
        $startLog = $request->input('start_log');
        $endLog = $request->input('end_log');
        $factory = FactoryLine::current();

        $cacheKey = "fplatform:log-file-cut:{$factory->value}:{$startLog}:{$endLog}";

        $result = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->task->run($startLog, $endLog, $factory),
        );

        return response()->json(['data' => $result]);
    }
}
