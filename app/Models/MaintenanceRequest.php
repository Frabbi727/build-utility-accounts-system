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
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property Carbon|null $due_by
 * @property string|null $before_photo_path
 * @property string|null $after_photo_path
 * @property int|null $rating
 * @property string|null $rating_comment
 * @property string $cost
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
    'due_by',
    'before_photo_path',
    'after_photo_path',
    'rating',
    'rating_comment',
    'cost',
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
            'due_by' => 'datetime',
            'rating' => 'integer',
            'cost' => 'decimal:2',
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

    /** @return HasMany<VendorBill, $this> */
    public function vendorBills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    /** @return HasMany<MaintenanceRequestActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(MaintenanceRequestActivity::class)->latest('id');
    }

    public function isOverdue(): bool
    {
        if ($this->due_by === null) {
            return false;
        }

        if ($this->resolved_at !== null) {
            return $this->resolved_at->greaterThan($this->due_by);
        }

        if ($this->status->isClosed()) {
            return false;
        }

        return now()->greaterThan($this->due_by);
    }

    public function slaStatus(): string
    {
        if ($this->due_by === null) {
            return 'not_set';
        }

        if ($this->resolved_at !== null) {
            return $this->resolved_at->greaterThan($this->due_by) ? 'resolved_late' : 'resolved_on_time';
        }

        if ($this->status->isClosed()) {
            return 'resolved_on_time';
        }

        if (now()->greaterThan($this->due_by)) {
            return 'overdue';
        }

        // Within 4 hours of SLA breach
        if (now()->diffInHours($this->due_by, false) <= 4) {
            return 'due_soon';
        }

        return 'on_track';
    }

    public function hoursRemaining(): int
    {
        if ($this->due_by === null) {
            return 0;
        }

        return (int) now()->diffInHours($this->due_by, false);
    }
}
