<?php

namespace App\Containers\AppSection\Table\Actions;

use App\Containers\AppSection\Table\Abstracts\FormFieldDefinition;
use App\Containers\AppSection\Table\Supports\ValidationRuleParser;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves form metadata for a given model + action.
 * Auto-detects fields from Request::rules(), merges config overrides.
 */
final class GetFormMetaAction extends ParentAction
{
    private const CONFIG_PREFIX = 'appSection-table';

    public function run(string $modelKey, string $action, Authenticatable&Authorizable $user): array
    {
        $config = $this->resolveModelConfig($modelKey);
        abort_if(empty($config), 422, "Invalid model key: {$modelKey}");

        $forms = $config['forms'] ?? [];
        abort_if(! isset($forms[$action]), 422, "Invalid action: {$action} for model: {$modelKey}");

        $formConfig = $forms[$action];

        // Permission check
        $permission = $formConfig['permission'] ?? null;
        if ($permission && ! $user->can($permission)) {
            abort(403, 'Unauthorized');
        }

        // Cache key per model+action (permission checked above, cache is model-level)
        $cacheKey = "form_meta:{$modelKey}:{$action}";
        $cacheTtl = config(self::CONFIG_PREFIX . '.cache_ttl', 3600); // 1 hour default

        return cache()->remember($cacheKey, $cacheTtl, function () use ($modelKey, $action, $formConfig, $config) {
            return $this->resolveFormMeta($modelKey, $action, $formConfig, $config);
        });
    }

    private function resolveFormMeta(string $modelKey, string $action, array $formConfig, array $config): array
    {
        // Resolve fields from Request class
        $requestClass = $formConfig['request'] ?? null;
        abort_if(! $requestClass || ! class_exists($requestClass), 422, "Request class not configured for {$modelKey}.{$action}");

        // Instantiate without container to avoid triggering FormRequest validation.
        // Wrap in try-catch: some requests access $this->input() in rules() which
        // fails when instantiated outside HTTP context.
        $request = new $requestClass();

        try {
            $rules = method_exists($request, 'rules') ? $request->rules() : [];
        } catch (\Throwable) {
            $rules = [];
        }

        $messages = method_exists($request, 'messages') ? $request->messages() : [];

        // Step 1: Auto-detect from rules + custom messages
        $fields = ValidationRuleParser::parse($rules, $messages);

        // Step 2: Merge config overrides into auto-detected fields.
        $overrides = $formConfig['overrides'] ?? [];
        foreach ($overrides as $override) {
            if (! $override instanceof FormFieldDefinition) {
                continue;
            }

            $key = $override->getKey();
            if (isset($fields[$key])) {
                $autoArray = $fields[$key]->toArray();
                $overrideArray = $override->toArray();

                $merged = clone $override;
                if (empty($overrideArray['validation']) && ! empty($autoArray['validation'])) {
                    $merged->setValidation($autoArray['validation']);
                }
                if (empty($overrideArray['options']) && ! empty($autoArray['options'])) {
                    $merged->setOptions((array) $autoArray['options']);
                }
                if ($overrideArray['type'] === 'text' && $autoArray['type'] !== 'text') {
                    $merged->setType($autoArray['type']);
                }

                $fields[$key] = $merged;
            } else {
                $fields[$key] = $override;
            }
        }

        // Step 3: Resolve groups
        $groups = $this->resolveGroups($formConfig);

        // Step 4: Serialize
        return [
            'model'  => $modelKey,
            'action' => $action,
            'groups' => $groups,
            'fields' => collect($fields)
                ->map(fn (FormFieldDefinition $f) => $f->toArray())
                ->sortBy('order')
                ->values()
                ->all(),
            'submit' => $formConfig['submit'] ?? $this->defaultSubmit($config, $action),
        ];
    }

    private function resolveGroups(array $formConfig): array
    {
        $groups = $formConfig['groups'] ?? [];

        return collect($groups)
            ->map(fn (array $g) => [
                'key'   => $g['key'],
                'label' => trans($g['label'] ?? "table::groups.{$g['key']}"),
                'order' => $g['order'] ?? 0,
            ])
            ->sortBy('order')
            ->values()
            ->all();
    }

    private function defaultSubmit(array $config, string $action): array
    {
        $apiPrefix = $config['api_prefix'] ?? '/v1/admin';

        return match ($action) {
            'create' => ['method' => 'POST', 'url' => $apiPrefix],
            'update' => ['method' => 'PATCH', 'url' => "{$apiPrefix}/{id}"],
            default  => ['method' => 'POST', 'url' => $apiPrefix],
        };
    }

    /**
     * Auto-discover model config from all container `table-models` configs.
     */
    private function resolveModelConfig(string $modelKey): ?array
    {
        // Try centralized config first (backward compat)
        $config = config(self::CONFIG_PREFIX . ".models.{$modelKey}");
        if (! empty($config)) {
            return $config;
        }

        // Auto-discover from container configs
        foreach (config()->all() as $key => $value) {
            if (str_ends_with($key, 'table-models') && is_array($value) && isset($value[$modelKey])) {
                return $value[$modelKey];
            }
        }

        return null;
    }
}
