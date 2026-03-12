<?php

namespace App\Containers\AppSection\Icon\UI\API\Controllers;

use App\Containers\AppSection\Icon\Actions\ListIconsAction;
use App\Containers\AppSection\Icon\UI\API\Requests\ListIconsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListIconsController extends ApiController
{
    public function __invoke(ListIconsRequest $request, ListIconsAction $action): JsonResponse
    {
        $perPage = $request->integer('limit', (int) config('icon.per_page', 100));
        $page = $request->integer('page', 1);
        $search = $request->input('search');

        $result = $action->run($search, $page, $perPage);

        return response()->json($result);
    }
}
