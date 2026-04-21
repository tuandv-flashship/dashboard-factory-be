<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\GetReasonCodesForContextTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Collection;

final class GetReasonCodesAction extends ParentAction
{
    public function __construct(
        private readonly GetReasonCodesForContextTask $getReasonCodesTask,
    ) {
    }

    public function run(
        ?string $line         = null,
        ?string $dept         = null,
        ?string $scopeType    = null,
        ?bool   $isActive     = true,
        ?string $search       = null,
        ?string $categoryCode = null,
    ): Collection {
        return $this->getReasonCodesTask->run(
            line:         $line,
            dept:         $dept,
            scopeType:    $scopeType,
            isActive:     $isActive,
            search:       $search,
            categoryCode: $categoryCode,
        );
    }
}
