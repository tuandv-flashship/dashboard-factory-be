<?php

namespace App\Containers\AppSection\Shift\Support;

use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailChange;

/**
 * Records field-level diffs when shift details are manually updated
 * via the "Xác nhận nhân sự làm việc" form.
 *
 * Supports three productivity types:
 *  - per_person:      headcount
 *  - per_machine_dtf: headcount, machine_count
 *  - per_machine_dtg: headcount + active_machines (via Hook 2)
 */
final class ShiftDetailChangeRecorder
{
    /** Fields tracked for all productivity types. */
    private const COMMON_FIELDS = ['headcount'];

    /** Additional fields for per_machine_dtf. */
    private const DTF_FIELDS = ['machine_count'];

    // ── Snapshot ───────────────────────────────────────

    /**
     * Snapshot tracked scalar fields BEFORE update.
     * per_person: 1 field | per_machine_dtf: 2 fields | per_machine_dtg: 1 field
     */
    public static function snapshot(ShiftDetail $detail): array
    {
        $fields = self::COMMON_FIELDS;

        if ($detail->department?->productivity_type?->isPerMachineDtf()) {
            $fields = array_merge($fields, self::DTF_FIELDS);
        }

        return collect($fields)
            ->mapWithKeys(fn (string $f) => [$f => $detail->getAttribute($f)])
            ->toArray();
    }

    /**
     * Snapshot active machine NAMES before pivot sync (DTG only).
     */
    public static function snapshotMachineNames(ShiftDetail $detail): array
    {
        return $detail->machines()
            ->with('machine:id,name')
            ->get()
            ->pluck('machine.name')
            ->sort()
            ->values()
            ->toArray();
    }

    // ── Recording ─────────────────────────────────────

    /**
     * Compare scalar fields old/new → store diff if anything changed.
     * Call AFTER $detail->update().
     */
    public static function recordIfChanged(
        ShiftDetail $detail,
        array $oldSnap,
        int $userId,
        string $userName,
        ?string $ip = null,
    ): void {
        $changes = self::diffScalar($detail, $oldSnap);

        if (empty($changes)) {
            return;
        }

        self::store($detail, $changes, $userId, $userName, $ip);
    }

    /**
     * Record DTG machine selection changes + auto-computed fields.
     * Call AFTER pivot sync + $detail->update(['kpi_per_hour', 'machine_count']).
     *
     * @param  array  $oldMachineNames  Machine names BEFORE sync
     * @param  array  $newMachineNames  Machine names AFTER sync
     * @param  array  $oldSnap          Snapshot {machine_count} before sync
     */
    public static function recordMachineChanges(
        ShiftDetail $detail,
        array $oldMachineNames,
        array $newMachineNames,
        array $oldSnap,
        int $userId,
        string $userName,
        ?string $ip = null,
    ): void {
        $changes = [];

        // 1. Machine names diff
        sort($oldMachineNames);
        sort($newMachineNames);

        if ($oldMachineNames !== $newMachineNames) {
            $changes['active_machines'] = [
                'old' => $oldMachineNames,
                'new' => $newMachineNames,
            ];
        }

        // 2. Auto-computed scalar field (machine_count)
        $oldCount = $oldSnap['machine_count'] ?? null;
        $newCount = $detail->getAttribute('machine_count');

        if ($oldCount != $newCount) {
            $changes['machine_count'] = ['old' => $oldCount, 'new' => $newCount];
        }

        if (empty($changes)) {
            return;
        }

        self::store($detail, $changes, $userId, $userName, $ip);
    }

    // ── Private helpers ───────────────────────────────

    private static function diffScalar(ShiftDetail $detail, array $oldSnap): array
    {
        $changes = [];

        foreach ($oldSnap as $field => $old) {
            $new = $detail->getAttribute($field);

            if ($old != $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    private static function store(
        ShiftDetail $detail,
        array $changes,
        int $userId,
        string $userName,
        ?string $ip,
    ): void {
        ShiftDetailChange::query()->create([
            'shift_detail_id' => $detail->id,
            'user_id'         => $userId,
            'user_name'       => $userName,
            'changes'         => $changes,
            'ip_address'      => $ip,
            'created_at'      => now(),
        ]);
    }
}
