<?php

namespace App\Containers\AppSection\Table\Traits;

use App\Containers\AppSection\Table\Abstracts\ActionDefinition;
use App\Containers\AppSection\Table\Abstracts\ColumnDefinition;
use Closure;

/**
 * Optional trait for models that need to override auto-detected table config.
 * Override any method to customize — otherwise auto-detection applies.
 */
trait HasTableConfig
{
    /** @return ColumnDefinition[] Extra columns to merge with auto-detected ones. */
    public function getTableColumns(): array
    {
        return [];
    }

    /** @return ActionDefinition[] Override header actions (replaces auto-generated). */
    public function getTableHeaderActions(): array
    {
        return [];
    }

    /** @return ActionDefinition[] Override row actions (replaces auto-generated). */
    public function getTableRowActions(): array
    {
        return [];
    }

    /** @return array<class-string, array> Override bulk actions. */
    public function getTableBulkActions(): array
    {
        return [];
    }

    /** @return array<class-string, array> Override bulk changes. */
    public function getTableBulkChanges(): array
    {
        return [];
    }

    /** Custom save callback for bulk changes (like Botble's onSavingBulkChangeItem). */
    public function getCustomSaveCallback(): ?Closure
    {
        return null;
    }
}
