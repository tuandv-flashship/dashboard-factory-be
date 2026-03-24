<?php

namespace App\Containers\AppSection\Table\Abstracts;

/**
 * Fluent builder for form field metadata.
 * Supports: text, textarea, number, select, boolean, relation, color, icon, date, datetime, hidden
 *
 * Properties map directly to react-hook-form rules for zero-config FE integration.
 */
final class FormFieldDefinition
{
    private string $key;
    private string $label;
    private string $type;
    private ?string $group = null;
    private int $order = 0;
    private int $colSpan = 1;
    private mixed $default = null;
    private ?string $placeholder = null;
    private ?string $help = null;
    private bool $disabled = false;
    private ?array $options = null;
    private ?string $endpoint = null;
    private ?string $labelField = null;
    private ?string $valueField = null;
    private ?string $dependsOn = null;
    private ?array $queryParam = null;
    private ?array $showWhen = null;
    private array $validation = [];

    private function __construct(string $key, string $type, string $label = '')
    {
        $this->key = $key;
        $this->type = $type;
        $this->label = $label ?: "table::fields.{$key}";
    }

    // ─── Factory Methods ──────────────────────────────────────────

    public static function make(string $key, string $label = '', string $type = 'text'): self
    {
        return new self($key, $type, $label);
    }

    public static function text(string $key, string $label = ''): self
    {
        return new self($key, 'text', $label);
    }

    public static function textarea(string $key, string $label = ''): self
    {
        return new self($key, 'textarea', $label);
    }

    public static function number(string $key, string $label = ''): self
    {
        return new self($key, 'number', $label);
    }

    public static function select(string $key, string $label = ''): self
    {
        return new self($key, 'select', $label);
    }

    public static function boolean(string $key, string $label = ''): self
    {
        return new self($key, 'boolean', $label);
    }

    public static function relation(string $key, string $label = ''): self
    {
        return new self($key, 'relation', $label);
    }

    public static function color(string $key, string $label = ''): self
    {
        return new self($key, 'color', $label);
    }

    public static function icon(string $key, string $label = ''): self
    {
        return new self($key, 'icon', $label);
    }

    public static function date(string $key, string $label = ''): self
    {
        return new self($key, 'date', $label);
    }

    public static function datetime(string $key, string $label = ''): self
    {
        return new self($key, 'datetime', $label);
    }

    public static function hidden(string $key, string $label = ''): self
    {
        return new self($key, 'hidden', $label);
    }

    // ─── Layout ───────────────────────────────────────────────────

    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function colSpan(int $span): self
    {
        $this->colSpan = $span;

        return $this;
    }

    // ─── Value ────────────────────────────────────────────────────

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function placeholder(string $text): self
    {
        $this->placeholder = $text;

        return $this;
    }

    public function help(string $text): self
    {
        $this->help = $text;

        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    // ─── Select / Enum ────────────────────────────────────────────

    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /** Auto-fill options from a PHP backed Enum. */
    public function enum(string $enumClass): self
    {
        if (enum_exists($enumClass) && method_exists($enumClass, 'options')) {
            $this->options = $enumClass::options();
        } elseif (enum_exists($enumClass)) {
            $this->options = array_combine(
                array_column($enumClass::cases(), 'value'),
                array_column($enumClass::cases(), 'value'),
            );
        }

        return $this;
    }

    // ─── Relation ─────────────────────────────────────────────────

    public function endpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function labelField(string $field): self
    {
        $this->labelField = $field;

        return $this;
    }

    public function valueField(string $field): self
    {
        $this->valueField = $field;

        return $this;
    }

    // ─── Dependencies ─────────────────────────────────────────────

    public function dependsOn(string $field): self
    {
        $this->dependsOn = $field;

        return $this;
    }

    public function queryParam(string $key, string $template): self
    {
        $this->queryParam = [$key => $template];

        return $this;
    }

    public function showWhen(string $field, array $values): self
    {
        $this->showWhen = ['field' => $field, 'values' => $values];

        return $this;
    }

    // ─── Validation (react-hook-form compatible) ──────────────────

    public function required(string $message = ''): self
    {
        $this->validation['required'] = $message ?: true;

        return $this;
    }

    public function maxLength(int $value, string $message = ''): self
    {
        $this->validation['maxLength'] = ['value' => $value, 'message' => $message];

        return $this;
    }

    public function minLength(int $value, string $message = ''): self
    {
        $this->validation['minLength'] = ['value' => $value, 'message' => $message];

        return $this;
    }

    public function min(int $value, string $message = ''): self
    {
        $this->validation['min'] = ['value' => $value, 'message' => $message];

        return $this;
    }

    public function max(int $value, string $message = ''): self
    {
        $this->validation['max'] = ['value' => $value, 'message' => $message];

        return $this;
    }

    public function pattern(string $regex, string $message = ''): self
    {
        $this->validation['pattern'] = ['value' => $regex, 'message' => $message];

        return $this;
    }

    public function setValidation(array $validation): self
    {
        $this->validation = $validation;

        return $this;
    }

    // ─── Accessors ────────────────────────────────────────────────

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function setOptions(?array $options): self
    {
        $this->options = $options;

        return $this;
    }

    // ─── Serialize ────────────────────────────────────────────────

    public function toArray(): array
    {
        $data = [
            'key'   => $this->key,
            'label' => trans($this->label),
            'type'  => $this->type,
        ];

        // Layout
        if ($this->group !== null) {
            $data['group'] = $this->group;
        }
        $data['order']    = $this->order;
        $data['col_span'] = $this->colSpan;

        // Value
        if ($this->default !== null) {
            $data['default'] = $this->default;
        }
        if ($this->placeholder !== null) {
            $data['placeholder'] = trans($this->placeholder);
        }
        if ($this->help !== null) {
            $data['help'] = trans($this->help);
        }
        if ($this->disabled) {
            $data['disabled'] = true;
        }

        // Select / Enum options
        if ($this->options !== null) {
            $data['options'] = $this->options;
        }

        // Relation
        if ($this->endpoint !== null) {
            $data['endpoint'] = $this->endpoint;
        }
        if ($this->labelField !== null) {
            $data['label_field'] = $this->labelField;
        }
        if ($this->valueField !== null) {
            $data['value_field'] = $this->valueField;
        }

        // Dependencies
        if ($this->dependsOn !== null) {
            $data['depends_on'] = $this->dependsOn;
        }
        if ($this->queryParam !== null) {
            $data['query_param'] = $this->queryParam;
        }
        if ($this->showWhen !== null) {
            $data['show_when'] = $this->showWhen;
        }

        // Validation (react-hook-form format)
        if (! empty($this->validation)) {
            $data['validation'] = $this->validation;
        }

        return $data;
    }
}
