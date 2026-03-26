<?php

namespace App\Containers\AppSection\Media\UI\API\Responders;

use App\Containers\AppSection\Media\Values\MediaFileOperationResult;
use Illuminate\Http\JsonResponse;

final class MediaFileOperationResponder
{
    public function respond(MediaFileOperationResult $result): JsonResponse
    {
        return response()->json($result->responseBody(), $result->status());
    }
}

