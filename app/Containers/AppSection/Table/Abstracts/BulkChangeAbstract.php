<?php

namespace App\Containers\AppSection\Table\Abstracts;

/**
 * Base class for bulk field changes (status, name, is_featured, etc.).
 * Mirrors Botble's TableBulkChangeAbstract.
 */
abstract class BulkChangeAbstract
{
    protected string $name = '';
    protected string $title = '';
    protected string $type = 'text';
    protected array|string|null $validate = null;
    protected ?string $placeholder = null;
    protected ?string $tooltip = null;
    protected ?array $choices = null;

    // ─── Fluent Setters ────────────────────────────────────────────

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function validate(array|string $validate): static
    {
        if (is_array($validate)) {
            $validate = implode('|', $validate);
        }

        $this->validate = $validate;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function tooltip(string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function choices(array $choices): static
    {
        $this->choices = $choices;

        return $this;
    }

    // ─── Accessors ─────────────────────────────────────────────────

    public function getName(): string
    {
        return $this->name;
    }

    public function getValidationRules(): array
    {
        if ($this->validate === null) {
            return [];
        }

        return is_string($this->validate)
            ? explode('|', $this->validate)
            : (array) $this->validate;
    }

    // ─── Serialize for FE metadata ─────────────────────────────────

    public function toMeta(): array
    {
        $data = [
            'key' => $this->name,
            'title' => trans($this->title),
            'type' => $this->type,
        ];

        if ($this->choices !== null) {
            $data['choices'] = (object) array_map(fn ($v) => trans($v), $this->choices);
        }

        if ($this->placeholder !== null) {
            $data['placeholder'] = trans($this->placeholder);
        }

        if ($this->tooltip !== null) {
            $data['tooltip'] = trans($this->tooltip);
        }

        return $data;
    }
}
