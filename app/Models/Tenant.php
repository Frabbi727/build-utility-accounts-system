<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $flat_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property Carbon|null $lease_started_on
 * @property Carbon|null $lease_ended_on
 */
#[Fillable(['flat_id', 'name', 'phone', 'email', 'lease_started_on', 'lease_ended_on'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lease_started_on' => 'date',
            'lease_ended_on' => 'date',
        ];
    }

    /** @return BelongsTo<Flat, $this> */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }
}
