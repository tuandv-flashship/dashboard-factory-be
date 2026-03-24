<?php

namespace App\Containers\AppSection\Table\BulkChanges;

/**
 * Bulk change for status field — auto-populates choices from model's enum cast.
 */
final class StatusBulkChange extends SelectBulkChange
{
    protected string $name = 'status';
    protected string $title = 'table::columns.status';
    protected ?string $placeholder = 'table::bulk_changes.select_status';

    /**
     * Populate choices from the model's status enum cast.
     */
    public function withEnum(string $enumClass): static
    {
        if (enum_exists($enumClass)) {
            $choices = [];
            foreach ($enumClass::cases() as $case) {
                $choices[$case->value] = "table::statuses.{$case->value}";
            }
            $this->choices($choices);
        }

        return $this;
    }
}
