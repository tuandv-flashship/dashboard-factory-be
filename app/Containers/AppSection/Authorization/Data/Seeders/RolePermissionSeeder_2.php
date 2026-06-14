<?php

namespace App\Containers\AppSection\Authorization\Data\Seeders;

use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use App\Containers\AppSection\Department\Models\Department;
use App\Ship\Parents\Seeders\Seeder as ParentSeeder;

/**
 * Seeds default roles and assigns permissions with department scope.
 *
 * Lead roles   → full department-data permissions (view + CRUD), scoped
 * Dashboard    → view-only (dashboard.view), scoped
 * Assistant    → view dept data (global) + full operation-admin
 * Admin        → SuperAdmin (already exists, no changes needed)
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Authorization\Data\Seeders\RolePermissionSeeder_2"
 */
final class RolePermissionSeeder_2 extends ParentSeeder
{
    /**
     * Full department-data permissions (view + CRUD) — used by Lead roles.
     */
    private const FULL_DEPT_PERMISSIONS = [
        'dashboard.view',
        'dashboard.view-productivity',
        'working-hours.index',
        'working-hours.create',
        'working-hours.edit',
        'working-hours.destroy',
        'hourly-issues.index',
        'hourly-issues.create',
        'hourly-issues.edit',
        'hourly-issues.destroy',
        'attendance.index',
        'attendance.edit',
    ];

    /**
     * View-only permission — used by Dashboard roles.
     */
    private const VIEW_ONLY_PERMISSIONS = [
        'dashboard.view',
    ];

    /**
     * Assistant permissions: view dept data (global) + full operation-admin.
     */
    private const ASSISTANT_PERMISSIONS = [
        // View department data (no CRUD)
        'dashboard.view',
        'dashboard.view-productivity',
        'working-hours.index',
        'hourly-issues.index',
        'attendance.index',
        // Shifts (full CRUD)
        'shifts.index',
        'shifts.create',
        'shifts.edit',
        'shifts.destroy',
        // Shift templates (full CRUD)
        'shift-templates.index',
        'shift-templates.create',
        'shift-templates.edit',
        'shift-templates.destroy',
        // KPI Rating Levels (full CRUD)
        'kpi-rating-levels.index',
        'kpi-rating-levels.create',
        'kpi-rating-levels.edit',
        'kpi-rating-levels.destroy',
        // Departments (full CRUD)
        'departments.index',
        'departments.create',
        'departments.edit',
        'departments.destroy',
    ];

    /**
     * Role definitions: enum → config.
     *
     * @return array<string, array{description: string, depts: string[]|null, permissions: string[]}>
     */
    private function definitions(): array
    {
        return [
            // ── Lead roles (full dept-data, scoped) ──
            Role::LEAD_PRINT->value => [
                'description' => 'Quản lý bộ phận In DTF & DTG',
                'depts'       => ['print', 'dtg_print'],
                'permissions' => self::FULL_DEPT_PERMISSIONS,
            ],
            Role::LEAD_PICK->value => [
                'description' => 'Quản lý bộ phận Pick DTF & DTG',
                'depts'       => ['pick', 'pick_dtg'],
                'permissions' => self::FULL_DEPT_PERMISSIONS,
            ],
            Role::LEAD_CUT_MOCKUP->value => [
                'description' => 'Quản lý bộ phận Cắt & Ráp mẫu',
                'depts'       => ['cut', 'mockup'],
                'permissions' => self::FULL_DEPT_PERMISSIONS,
            ],
            Role::LEAD_PACK_SHIP->value => [
                'description' => 'Quản lý bộ phận Đóng gói & Giao',
                'depts'       => ['pack_ship'],
                'permissions' => self::FULL_DEPT_PERMISSIONS,
            ],

            // ── Dashboard roles (view-only, scoped) ──
            Role::DASHBOARD_PRINT_DTF->value => [
                'description' => 'Xem Dashboard bộ phận In DTF',
                'depts'       => ['print'],
                'permissions' => self::VIEW_ONLY_PERMISSIONS,
            ],
            Role::DASHBOARD_PRINT_DTG->value => [
                'description' => 'Xem Dashboard bộ phận In DTG',
                'depts'       => ['dtg_print'],
                'permissions' => self::VIEW_ONLY_PERMISSIONS,
            ],
            Role::DASHBOARD_PICK->value => [
                'description' => 'Xem Dashboard bộ phận Pick',
                'depts'       => ['pick', 'pick_dtg'],
                'permissions' => self::VIEW_ONLY_PERMISSIONS,
            ],
            Role::DASHBOARD_CUT->value => [
                'description' => 'Xem Dashboard bộ phận Cắt',
                'depts'       => ['cut'],
                'permissions' => self::VIEW_ONLY_PERMISSIONS,
            ],
            Role::DASHBOARD_MOCKUP->value => [
                'description' => 'Xem Dashboard bộ phận Ráp mẫu',
                'depts'       => ['mockup'],
                'permissions' => self::VIEW_ONLY_PERMISSIONS,
            ],
            Role::DASHBOARD_PACK_SHIP->value => [
                'description' => 'Xem Dashboard bộ phận Đóng gói & Giao',
                'depts'       => ['pack_ship'],
                'permissions' => self::VIEW_ONLY_PERMISSIONS,
            ],

            // ── Assistant (global, no dept scope) ──
            Role::ASSISTANT->value => [
                'description' => 'Trợ lý vận hành — xem dữ liệu toàn bộ + quản trị vận hành',
                'depts'       => null,
                'permissions' => self::ASSISTANT_PERMISSIONS,
            ],
        ];
    }

    public function run(): void
    {
        $guards = array_keys(config('auth.guards', []));

        // Pre-load department code → ID map
        $deptMap = Department::pluck('id', 'code');

        // Pre-load permission name → ID map
        $permMap = Permission::pluck('id', 'name');

        foreach ($this->definitions() as $roleName => $config) {
            $roleEnum = Role::from($roleName);

            foreach ($guards as $guard) {
                $role = RoleModel::query()->updateOrCreate(
                    ['name' => $roleName, 'guard_name' => $guard],
                    [
                        'display_name' => $roleEnum->label(),
                        'description'  => $config['description'],
                    ],
                );

                // Always sync permissions with department_ids (re-runnable)

                // Resolve department IDs
                $deptIds = null;
                if ($config['depts'] !== null) {
                    $deptIds = collect($config['depts'])
                        ->map(fn (string $code) => $deptMap[$code] ?? null)
                        ->filter()
                        ->values()
                        ->all();
                }

                // Build sync data with department_ids pivot
                $syncData = [];
                foreach ($config['permissions'] as $permFlag) {
                    $permId = $permMap[$permFlag] ?? null;
                    if ($permId === null) {
                        continue; // Permission not synced yet
                    }

                    $syncData[$permId] = [
                        'department_ids' => $deptIds !== null ? json_encode($deptIds) : null,
                    ];
                }

                $role->permissions()->sync($syncData);
            }
        }
    }
}
