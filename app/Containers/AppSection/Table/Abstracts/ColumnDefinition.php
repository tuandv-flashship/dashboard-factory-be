<?php

namespace App\Containers\AppSection\Table\Abstracts;

/**
 * Fluent builder for column metadata, served to FE via table-meta API.
 * Auto-detected from model casts/fillable, or declared explicitly in config.
 *
 * Inspired by Botble CMS Columns/ (FormattedColumn, LinkableColumn, Concerns/*).
 */
final class ColumnDefinition
{
    // ─── Core Properties ───────────────────────────────────────────
    private string $key;
    private string $title;
    private string $type = 'text';
    private bool $sortable = true;
    private bool $searchable = false;
    private ?string $searchOperator = null;
    private bool $visible = true;
    private ?int $width = null;
    private string $align = 'left';
    private ?array $options = null;
    private int $priority = 0;

    // ─── Display Properties (from Botble Concerns) ─────────────────
    private bool $copyable = false;
    private bool $maskable = false;
    private ?string $maskChar = null;
    private ?int $maskLength = null;
    private ?string $emptyState = null;
    private ?string $urlPattern = null;
    private bool $openInNewTab = false;
    private ?string $dateFormat = null;
    private ?int $limit = null;
    private ?string $icon = null;
    private ?string $iconPosition = null;  // 'before' | 'after'
    private ?string $color = null;

    private function __construct(string $key, string $title)
    {
        $this->key = $key;
        $this->title = $title;
    }

    // ─── Factory Methods ───────────────────────────────────────────

    public static function make(string $key, string $title): self
    {
        return new self($key, $title);
    }

    public static function id(): self
    {
        return self::make('id', 'table::columns.id')
            ->type('id')->sortable()->width(70)->align('center')
            ->priority(0);
    }

    public static function checkbox(): self
    {
        return self::make('checkbox', '')
            ->type('checkbox')->sortable(false)->searchable(false)
            ->width(40)->align('center')
            ->priority(-1);
    }

    public static function image(): self
    {
        return self::make('image', 'table::columns.image')
            ->type('image')->sortable(false)->searchable(false)
            ->width(70)->align('center')
            ->priority(1);
    }

    public static function status(string $enumClass): self
    {
        $options = [];
        if (enum_exists($enumClass)) {
            foreach ($enumClass::cases() as $case) {
                $options[$case->value] = "table::statuses.{$case->value}";
            }
        }

        return self::make('status', 'table::columns.status')
            ->type('status')->sortable()->width(120)->align('center')
            ->options($options);
    }

    public static function boolean(string $key, string $title): self
    {
        return self::make($key, $title)
            ->type('boolean')->sortable()->width(100)->align('center');
    }

    public static function date(string $key, string $title): self
    {
        return self::make($key, $title)
            ->type('date')->sortable()->width(160)->align('center');
    }

    public static function dateTime(string $key, string $title): self
    {
        return self::make($key, $title)
            ->type('datetime')->sortable()->width(180)->align('center');
    }

    public static function number(string $key, string $title): self
    {
        return self::make($key, $title)
            ->type('number')->sortable()->align('right');
    }

    public static function email(string $key = 'email', string $title = 'table::columns.email'): self
    {
        return self::make($key, $title)
            ->type('email')->searchable()
            ->linkable("mailto:{value}");
    }

    public static function phone(string $key = 'phone', string $title = 'table::bulk_changes.phone'): self
    {
        return self::make($key, $title)
            ->type('phone')->searchable()
            ->linkable("tel:{value}");
    }

    /** Name column — linkable to edit page, searchable. */
    public static function name(string $urlPattern = ''): self
    {
        $col = self::make('name', 'table::columns.name')
            ->type('text')->searchable();

        if ($urlPattern) {
            $col->linkable($urlPattern);
        }

        return $col;
    }

    /** Row actions column — FE renders action buttons. */
    public static function rowActions(): self
    {
        return self::make('actions', '')
            ->type('actions')->sortable(false)->searchable(false)
            ->width(80)->align('center');
    }

    // ─── Fluent Setters: Core ──────────────────────────────────────

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function sortable(bool $value = true): self
    {
        $this->sortable = $value;

        return $this;
    }

    public function searchable(bool $value = true): self
    {
        $this->searchable = $value;

        return $this;
    }

    public function searchOperator(string $operator): self
    {
        $this->searchOperator = $operator;

        return $this;
    }

    public function visible(bool $value = true): self
    {
        $this->visible = $value;

        return $this;
    }

    public function width(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function align(string $align): self
    {
        $this->align = $align;

        return $this;
    }

    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /** Auto-fill options from a PHP backed Enum (same pattern as FormFieldDefinition). */
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

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    // ─── Fluent Setters: Display (Botble Concerns) ─────────────────

    /** FE renders a copy-to-clipboard button. */
    public function copyable(bool $value = true): self
    {
        $this->copyable = $value;

        return $this;
    }

    /** FE masks sensitive data (e.g. ****1234). */
    public function maskable(string $char = '*', int $length = 4): self
    {
        $this->maskable = true;
        $this->maskChar = $char;
        $this->maskLength = $length;

        return $this;
    }

    /** FE shows this text when value is null/empty. Default: "—". */
    public function emptyState(string $text = '—'): self
    {
        $this->emptyState = $text;

        return $this;
    }

    /** FE renders cell value as a clickable link. Pattern supports {id}, {value}. */
    public function linkable(string $urlPattern, bool $openInNewTab = false): self
    {
        $this->urlPattern = $urlPattern;
        $this->openInNewTab = $openInNewTab;

        return $this;
    }

    /** FE formats date using this pattern (e.g. 'DD/MM/YYYY HH:mm'). */
    public function dateFormat(string $format): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    /** FE truncates text to this many characters, showing ellipsis. */
    public function limit(int $chars): self
    {
        $this->limit = $chars;

        return $this;
    }

    /** FE renders icon before or after cell text. */
    public function icon(string $icon, string $position = 'before'): self
    {
        $this->icon = $icon;
        $this->iconPosition = $position;

        return $this;
    }

    /** FE applies color class to cell text (primary, danger, success, etc.) */
    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    // ─── Accessors ─────────────────────────────────────────────────

    public function getKey(): string
    {
        return $this->key;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    // ─── Serialize ─────────────────────────────────────────────────

    public function toArray(): array
    {
        $data = [
            'key' => $this->key,
            'title' => trans($this->title),
            'type' => $this->type,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'visible' => $this->visible,
            'width' => $this->width,
            'align' => $this->align,
            'priority' => $this->priority,
        ];

        if ($this->searchable && $this->searchOperator !== null) {
            $data['search_operator'] = $this->searchOperator;
        }

        // Conditional fields — only sent when set (keeps response slim)
        if ($this->options !== null) {
            // Translate values only if they look like translation keys (contain '::')
            $data['options'] = (object) array_map(
                fn ($v) => is_string($v) && str_contains($v, '::') ? trans($v) : $v,
                $this->options,
            );
        }

        if ($this->copyable) {
            $data['copyable'] = true;
        }

        if ($this->maskable) {
            $data['mask'] = [
                'char' => $this->maskChar,
                'length' => $this->maskLength,
            ];
        }

        if ($this->emptyState !== null) {
            $data['empty_state'] = $this->emptyState;
        }

        if ($this->urlPattern !== null) {
            $data['link'] = [
                'url_pattern' => $this->urlPattern,
                'open_in_new_tab' => $this->openInNewTab,
            ];
        }

        if ($this->dateFormat !== null) {
            $data['date_format'] = $this->dateFormat;
        }

        if ($this->limit !== null) {
            $data['limit'] = $this->limit;
        }

        if ($this->icon !== null) {
            $data['icon'] = [
                'name' => $this->icon,
                'position' => $this->iconPosition ?? 'before',
            ];
        }

        if ($this->color !== null) {
            $data['color'] = $this->color;
        }

        return $data;
    }
}
