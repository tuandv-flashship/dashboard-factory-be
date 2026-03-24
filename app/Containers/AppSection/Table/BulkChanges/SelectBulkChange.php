<?php

namespace App\Containers\AppSection\Table\BulkChanges;

use App\Containers\AppSection\Table\Abstracts\BulkChangeAbstract;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Generic select/dropdown bulk change. Base for StatusBulkChange, IsFeaturedBulkChange.
 * Supports: static choices, callback-generated choices, searchable dropdown.
 */
class SelectBulkChange extends BulkChangeAbstract
{
    protected string $type = 'select';
    protected bool $searchable = false;
    protected ?Closure $choicesCallback = null;

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Set choices via array or callback (for lazy/dynamic choices like categories).
     */
    public function choices(Closure|array $choices): static
    {
        if ($choices instanceof Closure) {
            $this->choicesCallback = $choices;
            $this->choices = null;
        } else {
            $this->choices = $choices;
            $this->choicesCallback = null;
        }

        return $this;
    }

    public function getValidationRules(): array
    {
        if ($this->validate !== null) {
            return parent::getValidationRules();
        }

        // Auto-generate validation from choices
        $resolvedChoices = $this->resolveChoices();

        return ['required', Rule::in(array_keys($resolvedChoices))];
    }

    public function toMeta(): array
    {
        $data = parent::toMeta();

        $data['choices'] = (object) array_map(fn ($v) => trans($v), $this->resolveChoices());

        if ($this->searchable) {
            $data['type'] = 'select-search';
        }

        return $data;
    }

    private function resolveChoices(): array
    {
        if ($this->choicesCallback !== null) {
            return call_user_func($this->choicesCallback);
        }

        return $this->choices ?? [];
    }
}
