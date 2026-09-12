<?php

namespace App\Models;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\MaintenanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $building_id
 * @property int $flat_id
 * @property int $user_id
 * @property string $title
 * @property string $description
 * @property MaintenanceCategory $category
 * @property MaintenancePriority $priority
 * @property MaintenanceStatus $status
 * @property int|null $assigned_staff_id
 * @property int|null $assigned_vendor_id
 * @property string|null $resolution_notes
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'building_id',
    'flat_id',
    'user_id',
    'title',
    'description',
    'category',
    'priority',
    'status',
    'assigned_staff_id',
    'assigned_vendor_id',
    'resolution_notes',
    'resolved_at',
])]
class MaintenanceRequest extends Model
{
    use Auditable;

    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => MaintenanceCategory::class,
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** @return BelongsTo<Flat, $this> */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function assignedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'assigned_vendor_id');
    }
}
