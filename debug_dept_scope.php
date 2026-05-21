<?php

/**
 * Debug script: check DepartmentScope for a user.
 * Usage: php artisan tinker < debug_dept_scope.php
 */

// User truc.nguyen (ID: 3 from JWT sub claim)
$userId = 3;
$user = \App\Containers\AppSection\User\Models\User::find($userId);

echo "=== User: {$user->name} (ID: {$user->id}) ===\n";

// Load roles + permissions
$user->loadMissing('roles.permissions');

echo "\n--- Roles ---\n";
foreach ($user->roles as $role) {
    echo "  Role: {$role->name} (ID: {$role->id})\n";

    $dashboardPerm = $role->permissions->firstWhere('name', 'dashboard.view');
    if ($dashboardPerm) {
        $raw = $dashboardPerm->pivot->department_ids;
        echo "    dashboard.view → department_ids (raw): " . var_export($raw, true) . "\n";
        if ($raw !== null) {
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            echo "    dashboard.view → department_ids (decoded): " . json_encode($decoded) . "\n";
        } else {
            echo "    ⚠️  department_ids is NULL → GLOBAL ACCESS!\n";
        }
    } else {
        echo "    ❌ dashboard.view permission NOT found on this role\n";
    }
}

echo "\n--- DepartmentScope::resolve() result ---\n";
$result = \App\Ship\Supports\DepartmentScope::resolve($user, 'dashboard.view');
echo "  Result: " . ($result === null ? 'NULL (global)' : json_encode($result)) . "\n";

echo "\n--- isSuperAdmin check ---\n";
echo "  isSuperAdmin: " . ($user->isSuperAdmin() ? 'YES' : 'NO') . "\n";
