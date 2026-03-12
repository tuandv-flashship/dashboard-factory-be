<?php

namespace App\Ship\Responders;

use App\Ship\Values\ApiError;
use Illuminate\Http\JsonResponse;

final class ApiErrorResponder
{
    public function respond(ApiError $error): JsonResponse
    {
        return response()->json($error->payload(), $error->status);
    }
}

