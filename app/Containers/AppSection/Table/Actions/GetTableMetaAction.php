<?php

namespace App\Containers\AppSection\Table\Actions;

use App\Containers\AppSection\Table\Supports\BulkActionRegistry;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

final class GetTableMetaAction extends ParentAction
{
    public function __construct(
        private readonly BulkActionRegistry $registry,
    ) {
    }

    /** Get metadata for a specific model, or list all registered models. */
    public function run(?string $modelKey, Authenticatable&Authorizable $user): array
    {
        if ($modelKey === null) {
            return $this->registry->getRegisteredModels();
        }

        $meta = $this->registry->resolveTableMeta($modelKey, $user);
        abort_if(empty($meta), 422, "Invalid model key: {$modelKey}");

        return $meta;
    }
}
