<?php

namespace App\Containers\AppSection\Table\Abstracts;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for bulk actions (delete, archive, etc.).
 * Mirrors Botble's TableBulkActionAbstract dispatch pattern.
 */
abstract class BulkActionAbstract
{
    protected ?Closure $beforeDispatch = null;
    protected ?Closure $afterDispatch = null;

    /**
     * Execute the bulk action on a set of record IDs.
     *
     * @return array{success: int, failed: int, errors: array}
     */
    abstract public function dispatch(Model $model, array $ids): array;

    /** Get a human-readable action key (e.g. 'delete'). */
    abstract public function getActionKey(): string;

    /** Get translated label for this action. */
    abstract public function getLabel(): string;

    /** Get icon class. */
    public function getIcon(): ?string
    {
        return null;
    }

    /** Get color semantic. */
    public function getColor(): string
    {
        return 'danger';
    }

    /** Get priority for ordering. */
    public function getPriority(): int
    {
        return 1;
    }

    /** Get confirmation object, or null if no confirmation needed. */
    public function getConfirmation(): ?array
    {
        return null;
    }

    // ─── Lifecycle Hooks ───────────────────────────────────────────

    public function beforeDispatch(Closure $callback): static
    {
        $this->beforeDispatch = $callback;

        return $this;
    }

    public function afterDispatch(Closure $callback): static
    {
        $this->afterDispatch = $callback;

        return $this;
    }

    public function handleBeforeDispatch(Model $model, array $ids): void
    {
        if ($this->beforeDispatch !== null) {
            call_user_func($this->beforeDispatch, $model, $ids);
        }
    }

    public function handleAfterDispatch(Model $model, array $ids): void
    {
        if ($this->afterDispatch !== null) {
            call_user_func($this->afterDispatch, $model, $ids);
        }
    }

    // ─── Serialize for FE metadata ─────────────────────────────────

    public function toMeta(): array
    {
        $data = [
            'action' => $this->getActionKey(),
            'label' => $this->getLabel(),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'priority' => $this->getPriority(),
        ];

        $confirmation = $this->getConfirmation();
        $data['confirmation'] = $confirmation;

        return $data;
    }
}
