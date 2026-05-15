<?php

namespace App\Containers\AppSection\Production\Support;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\HourlyRecordChange;

/**
 * Records field-level diffs when hourly records are manually edited
 * via the "Sửa giờ làm việc" form.
 *
 * Supports three productivity types:
 *  - per_person:      kpi_minutes, target, staff_required, note
 *  - per_machine_dtf: + machine_count
 *  - per_machine_dtg: + active_machines (machine names), machine_count, target (auto-computed)
 */
final class HourlyRecordChangeRecorder
{
    /** Fields tracked for all productivity types. */
    private const COMMON_FIELDS = ['kpi_minutes', 'target', 'staff_required', 'note'];

    /** Additional fields for per_machine_dtf. */
    private const DTF_FIELDS = ['machine_count'];

    // ── Snapshot ───────────────────────────────────────

    /**
     * Snapshot tracked scalar fields BEFORE update.
     * per_person: 4 fields | per_machine_dtf: 5 fields | per_machine_dtg: 4 fields
     */
    public static function snapshot(HourlyRecord $record): array
    {
        $fields = self::COMMON_FIELDS;

        if ($record->department?->productivity_type?->isPerMachineDtf()) {
            $fields = array_merge($fields, self::DTF_FIELDS);
        }

        return collect($fields)
            ->mapWithKeys(fn (string $f) => [$f => $record->getAttribute($f)])
            ->toArray();
    }

    /**
     * Snapshot active machine NAMES before pivot sync (DTG only).
     */
    public static function snapshotMachineNames(HourlyRecord $record): array
    {
        return $record->hourlyMachines()
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
     * Call AFTER $record->update().
     */
    public static function recordIfChanged(
        HourlyRecord $record,
        array $oldSnap,
        int $userId,
        string $userName,
        ?string $ip = null,
    ): void {
        $changes = self::diffScalar($record, $oldSnap);

        if (empty($changes)) {
            return;
        }

        self::store($record, $changes, $userId, $userName, $ip);
    }

    /**
     * Record DTG machine selection changes + auto-computed fields.
     * Call AFTER pivot sync + $record->update(['machine_count', 'target']).
     *
     * @param  array  $oldMachineNames  Machine names BEFORE sync
     * @param  array  $newMachineNames  Machine names AFTER sync
     * @param  array  $oldSnap          Snapshot {machine_count, target} before sync
     */
    public static function recordMachineChanges(
        HourlyRecord $record,
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

        // 2. Auto-computed scalar fields (machine_count, target)
        foreach (['machine_count', 'target'] as $field) {
            $old = $oldSnap[$field] ?? null;
            $new = $record->getAttribute($field);

            if ($old != $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        if (empty($changes)) {
            return;
        }

        self::store($record, $changes, $userId, $userName, $ip);
    }

    // ── Private helpers ───────────────────────────────

    private static function diffScalar(HourlyRecord $record, array $oldSnap): array
    {
        $changes = [];

        foreach ($oldSnap as $field => $old) {
            $new = $record->getAttribute($field);

            if ($old != $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    private static function store(
        HourlyRecord $record,
        array $changes,
        int $userId,
        string $userName,
        ?string $ip,
    ): void {
        HourlyRecordChange::query()->create([
            'hourly_record_id' => $record->id,
            'user_id'          => $userId,
            'user_name'        => $userName,
            'changes'          => $changes,
            'ip_address'       => $ip,
            'created_at'       => now(),
        ]);
    }
}
