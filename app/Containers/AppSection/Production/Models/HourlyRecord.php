<?php

namespace App\Containers\AppSection\Production\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class HourlyRecord extends ParentModel
{
    use SoftDeletes;
    protected $table = 'hourly_records';

    protected $fillable = [
        'shift_id', 'department_id', 'hour_slot', 'hour_index',
        'target', 'kpi_hours', 'kpi_minutes', 'kpi_percent', 'actual', 'staff', 'staff_required', 'machine_count',
        'note', 'hour_start_inventory',
        'efficiency', 'error_rate', 'status', 'productivity_json',
    ];

    protected $casts = [
        'hour_index'           => 'integer',
        'target'               => 'integer',
        'kpi_hours'            => 'float',
        'kpi_minutes'          => 'integer',
        'kpi_percent'          => 'float',
        'actual'               => 'integer',
        'staff'                => 'integer',
        'staff_required'       => 'integer',
        'machine_count'        => 'integer',
        'hour_start_inventory' => 'integer',
        'efficiency'           => 'float',
        'error_rate'           => 'float',
        'status'               => 'string',
        'note'                 => 'string',
        'productivity_json'    => 'array',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function shiftDetail(): HasOne
    {
        return $this->hasOne(ShiftDetail::class, 'shift_id', 'shift_id')
            ->where('shift_details.department_id', $this->department_id);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(HourlyIssue::class, 'hourly_record_id');
    }

    public function hourlyMachines(): HasMany
    {
        return $this->hasMany(HourlyRecordMachine::class, 'hourly_record_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(HourlyRecordChange::class, 'hourly_record_id');
    }

    /** Most recent manual change — used for transformer inline display. */
    public function latestChange(): HasOne
    {
        return $this->hasOne(HourlyRecordChange::class, 'hourly_record_id')
                    ->latestOfMany('created_at');
    }

    // ── Productivity item IDs ───────────────────────────

    /**
     * Volatile fields excluded from ID generation.
     * These change between syncs while the identity stays the same.
     */
    private const VOLATILE_FIELDS = ['value', 'num_staff', '_id'];

    /**
     * Stamp a deterministic `_id` onto each item in productivity_json.
     *
     * The _id is an 8-char hex hash derived from the item's **identity
     * fields** only (e.g. date_hour + username, or date_hour + machine).
     * Volatile fields like `value` are excluded so the same worker/machine
     * in the same hour always produces the same _id across syncs.
     *
     * @param  array|null $items  Raw productivity_json items from FPlatform.
     * @return array|null         Same items with `_id` prepended in each entry.
     */
    public static function stampItemIds(?array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        return array_map(function (array $item) {
            // Build identity payload — only stable fields
            $identity = array_diff_key($item, array_flip(self::VOLATILE_FIELDS));
            ksort($identity);

            $item['_id'] = substr(md5(json_encode($identity)), 0, 8);

            return $item;
        }, $items);
    }
}
