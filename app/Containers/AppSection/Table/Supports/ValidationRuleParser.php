<?php

namespace App\Containers\AppSection\Table\Supports;

use App\Containers\AppSection\Table\Abstracts\FormFieldDefinition;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Illuminate\Validation\Rules\In;

/**
 * Parse Laravel Request validation rules into FormFieldDefinition objects
 * with react-hook-form compatible validation metadata.
 *
 * Supports: required, sometimes, string, integer, boolean, min, max,
 * Rule::in(), Rule::enum(), exists, regex, email, url, date.
 */
final class ValidationRuleParser
{
    /**
     * Parse a Request's rules() into FormFieldDefinition array.
     *
     * @param  array  $rules     ['field' => ['required', 'string', 'max:30'], ...]
     * @param  array  $messages  Custom messages from Request::messages() ['field.required' => 'Custom msg', ...]
     * @return FormFieldDefinition[]  keyed by field name
     */
    public static function parse(array $rules, array $messages = []): array
    {
        $fields = [];
        $orderIndex = 0;

        foreach ($rules as $fieldKey => $fieldRules) {
            // Skip nested array rules (e.g. 'gallery.*.img', 'items.*')
            if (str_contains($fieldKey, '.')) {
                continue;
            }

            $fieldRules = self::normalizeRules($fieldRules);
            $field = self::buildField($fieldKey, $fieldRules, $orderIndex++, $messages);

            if ($field !== null) {
                $fields[$fieldKey] = $field;
            }
        }

        return $fields;
    }

    /**
     * Normalize rules to array format.
     * Supports: array, pipe-separated string.
     */
    private static function normalizeRules(array|string $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        return $rules;
    }

    /**
     * Build a single FormFieldDefinition from Laravel rules.
     */
    private static function buildField(string $key, array $rules, int $order, array $messages = []): ?FormFieldDefinition
    {
        $type = self::detectType($key, $rules);
        $field = FormFieldDefinition::make($key, '', $type)->order($order);

        $validation = [];
        $isRequired = false;
        $enumClass = null;
        $inValues = null;

        foreach ($rules as $rule) {
            // Object rules (Rule::enum, Rule::in, etc.)
            if ($rule instanceof EnumRule) {
                $enumClass = self::extractEnumClass($rule);
                continue;
            }

            if ($rule instanceof In) {
                $inValues = self::extractInValues($rule);
                continue;
            }

            if (! is_string($rule)) {
                continue;
            }

            // String rules
            $rule = trim($rule);

            if ($rule === 'required') {
                $isRequired = true;
                $label = self::humanize($key);
                $validation['required'] = self::getMessage($key, 'required', $messages, "{$label} là bắt buộc");
            }

            if (Str::startsWith($rule, 'max:')) {
                $value = (int) Str::after($rule, 'max:');
                if (self::isStringType($rules)) {
                    $validation['maxLength'] = [
                        'value'   => $value,
                        'message' => self::getMessage($key, 'max', $messages, "Tối đa {$value} ký tự"),
                    ];
                } else {
                    $validation['max'] = [
                        'value'   => $value,
                        'message' => self::getMessage($key, 'max', $messages, "Tối đa {$value}"),
                    ];
                }
            }

            if (Str::startsWith($rule, 'min:')) {
                $value = (int) Str::after($rule, 'min:');
                if (self::isStringType($rules)) {
                    $validation['minLength'] = [
                        'value'   => $value,
                        'message' => self::getMessage($key, 'min', $messages, "Tối thiểu {$value} ký tự"),
                    ];
                } else {
                    $validation['min'] = [
                        'value'   => $value,
                        'message' => self::getMessage($key, 'min', $messages, "Tối thiểu {$value}"),
                    ];
                }
            }

            if (Str::startsWith($rule, 'regex:')) {
                $pattern = Str::after($rule, 'regex:');
                $pattern = trim($pattern, '/');
                $validation['pattern'] = [
                    'value'   => $pattern,
                    'message' => self::getMessage($key, 'regex', $messages, 'Định dạng không hợp lệ'),
                ];
            }

            if ($rule === 'email') {
                $validation['pattern'] = [
                    'value'   => '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$',
                    'message' => self::getMessage($key, 'email', $messages, 'Email không hợp lệ'),
                ];
            }
        }

        // Apply enum options
        if ($enumClass !== null) {
            $field->setType('select');
            $field->enum($enumClass);
        }

        // Apply Rule::in options
        if ($inValues !== null) {
            $field->setType('select');
            $field->options(array_combine($inValues, $inValues));
        }

        // Apply validation
        if (! empty($validation)) {
            $field->setValidation($validation);
        }

        return $field;
    }

    /**
     * Detect field type from rules.
     */
    private static function detectType(string $key, array $rules): string
    {
        foreach ($rules as $rule) {
            if ($rule instanceof EnumRule) {
                return 'select';
            }
            if ($rule instanceof In) {
                return 'select';
            }

            if (! is_string($rule)) {
                continue;
            }

            if ($rule === 'boolean') {
                return 'boolean';
            }
            if ($rule === 'integer' || $rule === 'numeric') {
                return 'number';
            }
            if ($rule === 'date') {
                return 'date';
            }
            if ($rule === 'email') {
                return 'text';
            }
            if ($rule === 'array') {
                return 'json';
            }
            if (Str::startsWith($rule, 'exists:')) {
                return 'relation';
            }
        }

        return 'text';
    }

    /**
     * Check if rules indicate a string type (for max/min → maxLength/minLength).
     */
    private static function isStringType(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && in_array($rule, ['string', 'email', 'url'], true)) {
                return true;
            }
            if (is_string($rule) && in_array($rule, ['integer', 'numeric'], true)) {
                return false;
            }
        }

        return true; // default to string
    }

    /**
     * Extract enum class from Rule::enum().
     */
    private static function extractEnumClass(EnumRule $rule): ?string
    {
        // Use reflection to access protected $type property
        $ref = new \ReflectionProperty($rule, 'type');
        $ref->setAccessible(true);

        return $ref->getValue($rule);
    }

    /**
     * Extract values from Rule::in().
     */
    private static function extractInValues(In $rule): array
    {
        // Rule::in stores values; cast to string to extract
        $str = (string) $rule; // "in:a,b,c"
        $csv = Str::after($str, 'in:');
        if (Str::startsWith($csv, '"')) {
            $csv = str_replace('"', '', $csv);
        }

        return array_filter(explode(',', $csv));
    }

    /**
     * Generate a human-readable label from field key.
     */
    private static function humanize(string $key): string
    {
        // Try translation first
        $transKey = "table::fields.{$key}";
        $translated = trans($transKey);

        if ($translated !== $transKey) {
            return $translated;
        }

        // Fallback: convert snake_case to Title Case
        return Str::title(str_replace('_', ' ', $key));
    }

    /**
     * Get custom message or fallback to default.
     * Supports Laravel message keys: 'field.rule' (e.g. 'code.required').
     */
    private static function getMessage(string $field, string $rule, array $messages, string $default): string
    {
        // Try 'field.rule' pattern (e.g. 'code.required')
        if (isset($messages["{$field}.{$rule}"])) {
            return $messages["{$field}.{$rule}"];
        }

        // Try generic rule pattern (e.g. 'required')
        if (isset($messages[$rule])) {
            return str_replace(':attribute', self::humanize($field), $messages[$rule]);
        }

        return $default;
    }
}
