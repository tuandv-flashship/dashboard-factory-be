<?php

namespace App\Containers\AppSection\Table\Supports;

use App\Containers\AppSection\Table\Abstracts\ActionDefinition;
use App\Containers\AppSection\Table\Abstracts\BulkActionAbstract;
use App\Containers\AppSection\Table\Abstracts\BulkChangeAbstract;
use App\Containers\AppSection\Table\Abstracts\ColumnDefinition;
use App\Containers\AppSection\Table\BulkActions\DeleteBulkAction;
use App\Containers\AppSection\Table\BulkChanges\CreatedAtBulkChange;
use App\Containers\AppSection\Table\BulkChanges\EmailBulkChange;
use App\Containers\AppSection\Table\BulkChanges\IsFeaturedBulkChange;
use App\Containers\AppSection\Table\BulkChanges\NameBulkChange;
use App\Containers\AppSection\Table\BulkChanges\PhoneBulkChange;
use App\Containers\AppSection\Table\BulkChanges\StatusBulkChange;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Resolves table configuration from config + model auto-detection.
 * Priority: Model trait method > Config key > Auto-detection.
 *
 * Bound as singleton in TableServiceProvider for consistent caching.
 *
 * Config path: appSection-table.models.{modelKey}
 * Global settings: appSection-table.max_bulk_items
 *
 * Design note: Bulk operations use per-record processing WITHOUT DB::transaction()
 * intentionally — partial failure is expected and reported via { success, failed, errors[] }.
 */
final class BulkActionRegistry
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CONFIG_PREFIX = 'appSection-table';

    // ─── Public API ────────────────────────────────────────────────

    /** Get all registered model keys. */
    public function getRegisteredModels(): array
    {
        return array_keys($this->getModelsConfig());
    }

    /** Resolve the model class for a given model key. */
    public function resolveModel(string $modelKey): ?Model
    {
        $class = $this->modelConfig($modelKey, 'model');
        if (! $class || ! class_exists($class)) {
            return null;
        }

        return app($class);
    }

    /** Build full table metadata, filtered by user permissions. */
    public function resolveTableMeta(string $modelKey, Authenticatable&Authorizable $user): array
    {
        $cacheKey = $this->cacheKey($modelKey, $user);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($modelKey, $user) {
            return $this->buildTableMeta($modelKey, $user);
        });
    }

    /** Invalidate cache for a user+model. */
    public function invalidateCache(string $modelKey, Authenticatable&Authorizable $user): void
    {
        Cache::forget($this->cacheKey($modelKey, $user));
    }

    /** Resolve a BulkActionAbstract instance by action key. */
    public function resolveBulkAction(string $modelKey, string $actionKey): ?BulkActionAbstract
    {
        $bulkActions = $this->resolveBulkActionsConfig($modelKey);

        foreach ($bulkActions as $class => $meta) {
            /** @var BulkActionAbstract $instance */
            $instance = app($class);
            if ($instance->getActionKey() === $actionKey) {
                return $instance;
            }
        }

        return null;
    }

    /** Resolve a BulkChangeAbstract instance by field key. */
    public function resolveBulkChange(string $modelKey, string $fieldKey): ?BulkChangeAbstract
    {
        $bulkChanges = $this->resolveBulkChangesConfig($modelKey);

        foreach ($bulkChanges as $class => $meta) {
            /** @var BulkChangeAbstract $instance */
            $instance = app($class);
            $this->configureBulkChange($instance, $modelKey);
            if ($instance->getName() === $fieldKey) {
                return $instance;
            }
        }

        return null;
    }

    /** Resolve custom_save callback for a model. */
    public function resolveCustomSave(string $modelKey): ?\Closure
    {
        $model = $this->resolveModel($modelKey);

        // Priority 1: Model trait
        if ($model && method_exists($model, 'getCustomSaveCallback')) {
            $callback = $model->getCustomSaveCallback();
            if ($callback !== null) {
                return $callback;
            }
        }

        // Priority 2: Config
        return $this->modelConfig($modelKey, 'custom_save');
    }

    // ─── Config Helpers ────────────────────────────────────────────

    /** Cached merged models from all container configs. */
    private ?array $mergedModels = null;

    /**
     * Get all model configs by auto-discovering from container `table-models` configs.
     *
     * Convention: any config file named `table-models` is merged.
     * Also merges legacy `appSection-table.models` for backward compatibility.
     */
    private function getModelsConfig(): array
    {
        if ($this->mergedModels !== null) {
            return $this->mergedModels;
        }

        $models = config(self::CONFIG_PREFIX . '.models', []);

        // Auto-discover: scan all loaded config keys for `table-models`
        foreach (config()->all() as $key => $value) {
            if (str_ends_with($key, 'table-models') && is_array($value)) {
                $models = array_merge($models, $value);
            }
        }

        return $this->mergedModels = $models;
    }

    /** Get full config for a specific model key. */
    private function getModelFullConfig(string $modelKey): array
    {
        return $this->getModelsConfig()[$modelKey] ?? [];
    }

    /** Get a specific config value for a model. */
    private function modelConfig(string $modelKey, string $key, mixed $default = null): mixed
    {
        return $this->getModelsConfig()[$modelKey][$key] ?? $default;
    }

    /** Get a global (non-model) config value. */
    private function globalConfig(string $key, mixed $default = null): mixed
    {
        return config(self::CONFIG_PREFIX . ".{$key}", $default);
    }

    // ─── Build Methods ─────────────────────────────────────────────

    private function buildTableMeta(string $modelKey, Authenticatable&Authorizable $user): array
    {
        $config = $this->getModelFullConfig($modelKey);
        $model = $this->resolveModel($modelKey);

        if (! $model) {
            return [];
        }

        // Permission check (null/empty = bypass)
        $permission = $config['permission'] ?? null;
        if ($permission && ! $user->can($permission)) {
            abort(403, 'Unauthorized');
        }

        // Cache per user+model (actions/bulk depend on user permissions)
        $userId = $user->getAuthIdentifier();
        $cacheKey = "table_meta:{$modelKey}:user:{$userId}";
        $cacheTtl = $this->globalConfig('cache_ttl', 3600);

        return cache()->remember($cacheKey, $cacheTtl, function () use ($modelKey, $model, $config, $user) {
            $prefix = $config['permission_prefix'] ?? $modelKey;
            $apiPrefix = $config['api_prefix'] ?? "/v1/{$prefix}";
            $fePrefix = $config['fe_prefix'] ?? "/{$prefix}";

            return [
                'model' => $modelKey,
                'columns' => $this->resolveColumns($model, $config, $user, $modelKey),
                'header_actions' => $this->resolveHeaderActions($model, $config, $prefix, $fePrefix, $user),
                'row_actions' => $this->resolveRowActions($model, $config, $prefix, $apiPrefix, $fePrefix, $user),
                'bulk_actions' => $this->resolveBulkActionsForFe($model, $config, $prefix, $user, $modelKey),
                'bulk_changes' => $this->resolveBulkChangesForFe($model, $config, $prefix, $user, $modelKey),
                'default_sort' => $config['default_sort'] ?? ['key' => 'created_at', 'direction' => 'desc'],
                'max_bulk_items' => $this->globalConfig('max_bulk_items', 100),
                'pagination' => $config['pagination'] ?? ['default_limit' => 15, 'limits' => [15, 30, 50, 100]],
            ];
        });
    }

    // ─── Columns ───────────────────────────────────────────────────

    private function resolveColumns(Model $model, array $config, Authenticatable&Authorizable $user, string $modelKey): array
    {
        // Step 1: Auto-detect base columns
        $columns = $this->autoDetectColumns($model);

        // Step 2: Merge config columns (additive)
        if (isset($config['columns'])) {
            foreach ($config['columns'] as $col) {
                $columns[$col->getKey()] = $col;
            }
        }

        // Step 3: Merge model trait columns (highest priority)
        if (method_exists($model, 'getTableColumns')) {
            foreach ($model->getTableColumns() as $col) {
                $columns[$col->getKey()] = $col;
            }
        }

        // Step 4: Enrich searchable + operator from Repository $fieldSearchable
        $searchableFields = $this->resolveSearchableFields($modelKey);
        foreach ($columns as $key => $col) {
            if (isset($searchableFields[$key])) {
                $col->searchable(true)->searchOperator($searchableFields[$key]);
            } elseif (in_array($key, $searchableFields, true)) {
                $col->searchable(true)->searchOperator('=');
            }
        }

        // Step 5: Apply user preferences (visibility + order)
        $prefs = $this->getUserColumnPreferences($user, $modelKey);

        return collect($columns)
            ->map(function (ColumnDefinition $col) use ($prefs) {
                $arr = $col->toArray();
                $key = $col->getKey();

                if (isset($prefs[$key])) {
                    $pref = $prefs[$key];
                    if (isset($pref['visible'])) {
                        $arr['visible'] = (bool) $pref['visible'];
                    }
                    if (isset($pref['order'])) {
                        $arr['priority'] = (int) $pref['order'];
                    }
                }

                return $arr;
            })
            ->sortBy('priority')
            ->values()
            ->all();
    }

    private function autoDetectColumns(Model $model): array
    {
        $columns = [];
        $fillable = $model->getFillable();
        $casts = $model->getCasts();

        // Always add ID (priority 0 — set in factory)
        $columns['id'] = ColumnDefinition::id();

        // Image column
        if (in_array('image', $fillable, true)) {
            $columns['image'] = ColumnDefinition::image(); // priority 1
        }

        // Name column (searchable, linkable to edit)
        if (in_array('name', $fillable, true)) {
            $columns['name'] = ColumnDefinition::name()->emptyState()->priority(2);
        }

        // Email column (searchable, mailto link)
        if (in_array('email', $fillable, true)) {
            $columns['email'] = ColumnDefinition::email()->emptyState()->priority(3);
        }

        // Phone column (searchable, tel link)
        if (in_array('phone', $fillable, true)) {
            $columns['phone'] = ColumnDefinition::phone()->emptyState()->priority(4);
        }

        // Status column with enum
        if (isset($casts['status']) && enum_exists($casts['status'])) {
            $columns['status'] = ColumnDefinition::status($casts['status'])->priority(5);
        }

        // Boolean columns
        if (isset($casts['is_featured']) && $casts['is_featured'] === 'bool') {
            $columns['is_featured'] = ColumnDefinition::boolean('is_featured', 'table::columns.is_featured')
                ->width(100)->priority(6);
        }

        // Always add created_at if model uses timestamps
        if ($model->usesTimestamps()) {
            $columns['created_at'] = ColumnDefinition::date('created_at', 'table::columns.created_at')
                ->width(160)->emptyState()->priority(99);
        }

        return $columns;
    }

    // ─── Search Fields ─────────────────────────────────────────────

    /**
     * Resolve $fieldSearchable from the Repository linked in config.
     * Returns associative array: ['field' => 'operator', ...]
     */
    private function resolveSearchableFields(string $modelKey): array
    {
        $repositoryClass = $this->modelConfig($modelKey, 'repository');
        if (! $repositoryClass || ! class_exists($repositoryClass)) {
            return [];
        }

        /** @var RepositoryInterface $repository */
        $repository = app($repositoryClass);

        $raw = $repository->getFieldsSearchable();

        // Normalize: support both ['field' => 'op'] and ['field'] (default '=')
        $normalized = [];
        foreach ($raw as $key => $value) {
            if (is_numeric($key)) {
                $normalized[$value] = '=';
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    // ─── Header Actions ────────────────────────────────────────────

    private function resolveHeaderActions(Model $model, array $config, string $prefix, string $fePrefix, Authenticatable&Authorizable $user): array
    {
        // Priority 1: Model trait
        if (method_exists($model, 'getTableHeaderActions') && ! empty($model->getTableHeaderActions())) {
            $actions = $model->getTableHeaderActions();
        }
        // Priority 2: Config
        elseif (isset($config['header_actions'])) {
            $actions = $config['header_actions'];
        }
        // Priority 3: Auto-generate
        else {
            $actions = $this->defaultHeaderActions($prefix, $fePrefix);
        }

        return $this->filterActionsByPermission($actions, $user);
    }

    private function defaultHeaderActions(string $prefix, string $fePrefix): array
    {
        return [
            ActionDefinition::link('create', 'table::actions.create')
                ->icon('ti-plus')->color('primary')
                ->url("{$fePrefix}/create")
                ->permission("{$prefix}.create"),
        ];
    }

    // ─── Row Actions ───────────────────────────────────────────────

    private function resolveRowActions(Model $model, array $config, string $prefix, string $apiPrefix, string $fePrefix, Authenticatable&Authorizable $user): array
    {
        if (method_exists($model, 'getTableRowActions') && ! empty($model->getTableRowActions())) {
            $actions = $model->getTableRowActions();
        } elseif (isset($config['row_actions'])) {
            $actions = $config['row_actions'];
        } else {
            $actions = $this->defaultRowActions($prefix, $apiPrefix, $fePrefix);
        }

        return $this->filterActionsByPermission($actions, $user);
    }

    private function defaultRowActions(string $prefix, string $apiPrefix, string $fePrefix): array
    {
        return [
            ActionDefinition::link('edit', 'table::actions.edit')
                ->icon('ti-edit')->color('primary')
                ->url("{$fePrefix}/{id}/edit")
                ->permission("{$prefix}.edit"),
            ActionDefinition::action('delete', 'table::actions.delete')
                ->icon('ti-trash')->color('danger')
                ->method('DELETE')->url("{$apiPrefix}/{id}")
                ->permission("{$prefix}.destroy")->priority(99)
                ->confirmation('table::actions.confirm_delete_title', 'table::actions.confirm_delete_message')
                ->confirmButton('table::actions.delete', 'danger', 'ti-trash')
                ->cancelButton('table::actions.cancel'),
        ];
    }

    // ─── Bulk Actions ──────────────────────────────────────────────

    private function resolveBulkActionsConfig(string $modelKey): array
    {
        $config = $this->getModelFullConfig($modelKey);
        $model = $this->resolveModel($modelKey);
        $prefix = $config['permission_prefix'] ?? $modelKey;

        if ($model && method_exists($model, 'getTableBulkActions') && ! empty($model->getTableBulkActions())) {
            return $model->getTableBulkActions();
        }

        return $config['bulk_actions'] ?? $this->defaultBulkActions($prefix);
    }

    private function resolveBulkChangesConfig(string $modelKey): array
    {
        $config = $this->getModelFullConfig($modelKey);
        $model = $this->resolveModel($modelKey);
        $prefix = $config['permission_prefix'] ?? $modelKey;

        if ($model && method_exists($model, 'getTableBulkChanges') && ! empty($model->getTableBulkChanges())) {
            return $model->getTableBulkChanges();
        }

        return $config['bulk_changes'] ?? $this->autoDetectBulkChanges($model, $prefix);
    }

    private function resolveBulkActionsForFe(Model $model, array $config, string $prefix, Authenticatable&Authorizable $user, string $modelKey): array
    {
        $bulkActions = $this->resolveBulkActionsConfig($modelKey);

        return collect($bulkActions)
            ->filter(fn (array $meta) => ! isset($meta['permission']) || $user->can($meta['permission']))
            ->map(function (array $meta, string $class) {
                /** @var BulkActionAbstract $instance */
                $instance = app($class);
                $result = $instance->toMeta();
                $result['permission'] = $meta['permission'] ?? null;

                return $result;
            })
            ->sortBy('priority')
            ->values()
            ->all();
    }

    // ─── Bulk Changes ──────────────────────────────────────────────

    private function resolveBulkChangesForFe(Model $model, array $config, string $prefix, Authenticatable&Authorizable $user, string $modelKey): array
    {
        $bulkChanges = $this->resolveBulkChangesConfig($modelKey);

        return collect($bulkChanges)
            ->filter(fn (array $meta) => ! isset($meta['permission']) || $user->can($meta['permission']))
            ->map(function (array $meta, string $class) use ($modelKey) {
                /** @var BulkChangeAbstract $instance */
                $instance = app($class);
                $this->configureBulkChange($instance, $modelKey);
                $result = $instance->toMeta();
                $result['permission'] = $meta['permission'] ?? null;

                return $result;
            })
            ->values()
            ->all();
    }

    private function configureBulkChange(BulkChangeAbstract $instance, string $modelKey): void
    {
        // If StatusBulkChange, auto-configure enum from model
        if ($instance instanceof StatusBulkChange) {
            $model = $this->resolveModel($modelKey);
            if ($model) {
                $casts = $model->getCasts();
                if (isset($casts['status']) && enum_exists($casts['status'])) {
                    $instance->withEnum($casts['status']);
                }
            }
        }
    }

    private function defaultBulkActions(string $prefix): array
    {
        return [
            DeleteBulkAction::class => ['permission' => "{$prefix}.destroy"],
        ];
    }

    private function autoDetectBulkChanges(Model $model, string $prefix): array
    {
        $changes = [];
        $casts = $model->getCasts();
        $fillable = $model->getFillable();

        if (isset($casts['status']) && enum_exists($casts['status'])) {
            $changes[StatusBulkChange::class] = ['permission' => "{$prefix}.edit"];
        }

        if (in_array('name', $fillable, true)) {
            $changes[NameBulkChange::class] = ['permission' => "{$prefix}.edit"];
        }

        if (in_array('email', $fillable, true)) {
            $changes[EmailBulkChange::class] = ['permission' => "{$prefix}.edit"];
        }

        if (in_array('phone', $fillable, true)) {
            $changes[PhoneBulkChange::class] = ['permission' => "{$prefix}.edit"];
        }

        if (isset($casts['is_featured']) && $casts['is_featured'] === 'bool') {
            $changes[IsFeaturedBulkChange::class] = ['permission' => "{$prefix}.edit"];
        }

        if ($model->usesTimestamps()) {
            $changes[CreatedAtBulkChange::class] = ['permission' => "{$prefix}.edit"];
        }

        return $changes;
    }

    // ─── Permission Filtering ──────────────────────────────────────

    /** @param ActionDefinition[] $actions */
    private function filterActionsByPermission(array $actions, Authenticatable&Authorizable $user): array
    {
        return collect($actions)
            ->filter(function (ActionDefinition $action) use ($user) {
                $permission = $action->getPermission();

                return $permission === null || $user->can($permission);
            })
            ->sortBy(fn (ActionDefinition $a) => $a->getPriority())
            ->map(fn (ActionDefinition $a) => $a->toArray())
            ->values()
            ->all();
    }

    // ─── Column Visibility ─────────────────────────────────────────

    /**
     * Get user column preferences (visibility + order).
     * Backward-compatible: handles old format {key: bool} and new format {key: {visible, order}}.
     */
    private function getUserColumnPreferences(Authenticatable&Authorizable $user, string $modelKey): array
    {
        $key = "user:{$user->getAuthIdentifier()}:table_columns:{$modelKey}";

        $setting = \App\Containers\AppSection\Setting\Models\Setting::query()
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return [];
        }

        $stored = json_decode($setting->value, true) ?: [];

        // Normalize: old format {key: bool} → {key: {visible: bool}}
        $prefs = [];
        foreach ($stored as $colKey => $value) {
            if (is_array($value)) {
                $prefs[$colKey] = $value; // new format
            } else {
                $prefs[$colKey] = ['visible' => (bool) $value]; // old format
            }
        }

        return $prefs;
    }

    // ─── Cache ─────────────────────────────────────────────────────

    private function cacheKey(string $modelKey, Authenticatable&Authorizable $user): string
    {
        $locale = app()->getLocale();

        return "table_meta:{$user->getAuthIdentifier()}:{$modelKey}:{$locale}";
    }
}
