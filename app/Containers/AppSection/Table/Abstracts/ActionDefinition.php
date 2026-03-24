<?php

namespace App\Containers\AppSection\Table\Abstracts;

/**
 * Fluent builder for action metadata (header actions, row actions).
 * Permission field is included in serialization so FE can check user capabilities.
 */
final class ActionDefinition
{
    private string $name;
    private string $label;
    private string $type; // 'link' or 'action'
    private string $method = 'GET';
    private string $urlPattern = '';
    private ?string $icon = null;
    private string $color = 'primary';
    private ?string $tooltip = null;
    private bool $disabled = false;
    private ?string $disabledReason = null;
    private bool $openInNewTab = false;
    private int $priority = 1;
    private ?string $permission = null;
    private ?array $confirmation = null;

    private function __construct(string $name, string $label, string $type)
    {
        $this->name = $name;
        $this->label = $label;
        $this->type = $type;
    }

    // ─── Factory ───────────────────────────────────────────────────

    /** Create a navigation link action (GET, no API call). */
    public static function link(string $name, string $label): self
    {
        return new self($name, $label, 'link');
    }

    /** Create an API action (POST/PUT/DELETE, triggers API call). */
    public static function action(string $name, string $label): self
    {
        return new self($name, $label, 'action');
    }

    // ─── Visual ────────────────────────────────────────────────────

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function tooltip(string $tooltip): self
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function disabled(bool $disabled = true, ?string $reason = null): self
    {
        $this->disabled = $disabled;
        $this->disabledReason = $reason;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    // ─── Behavior ──────────────────────────────────────────────────

    public function method(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function url(string $pattern): self
    {
        $this->urlPattern = $pattern;

        return $this;
    }

    public function openInNewTab(bool $value = true): self
    {
        $this->openInNewTab = $value;

        return $this;
    }

    public function permission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    // ─── Confirmation Modal ────────────────────────────────────────

    public function confirmation(string $title, string $message): self
    {
        $this->confirmation = [
            'title' => $title,
            'message' => $message,
            'confirm_button' => ['label' => 'table::actions.confirm', 'color' => 'danger', 'icon' => null],
            'cancel_button' => ['label' => 'table::actions.cancel', 'color' => 'secondary'],
        ];

        return $this;
    }

    public function confirmButton(string $label, string $color = 'danger', ?string $icon = null): self
    {
        if ($this->confirmation !== null) {
            $this->confirmation['confirm_button'] = compact('label', 'color', 'icon');
        }

        return $this;
    }

    public function cancelButton(string $label, string $color = 'secondary'): self
    {
        if ($this->confirmation !== null) {
            $this->confirmation['cancel_button'] = compact('label', 'color');
        }

        return $this;
    }

    // ─── Accessors ─────────────────────────────────────────────────

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    // ─── Serialize (strips permission — never sent to FE) ──────────

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'label' => trans($this->label),
            'icon' => $this->icon,
            'color' => $this->color,
            'type' => $this->type,
            'method' => $this->method,
            'url_pattern' => $this->urlPattern,
            'open_in_new_tab' => $this->openInNewTab,
            'tooltip' => $this->tooltip ? trans($this->tooltip) : null,
            'disabled' => $this->disabled,
            'priority' => $this->priority,
            'permission' => $this->permission,
        ];

        if ($this->disabled && $this->disabledReason) {
            $data['disabled_reason'] = trans($this->disabledReason);
        }

        if ($this->confirmation !== null) {
            $data['confirmation'] = [
                'title' => trans($this->confirmation['title']),
                'message' => trans($this->confirmation['message']),
                'confirm_button' => [
                    'label' => trans($this->confirmation['confirm_button']['label']),
                    'color' => $this->confirmation['confirm_button']['color'],
                    'icon' => $this->confirmation['confirm_button']['icon'],
                ],
                'cancel_button' => [
                    'label' => trans($this->confirmation['cancel_button']['label']),
                    'color' => $this->confirmation['cancel_button']['color'],
                ],
            ];
        } else {
            $data['confirmation'] = null;
        }

        return $data;
    }
}
