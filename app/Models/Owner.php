<?php

namespace App\Models;

use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $national_id
 */
#[Fillable(['user_id', 'name', 'phone', 'email', 'national_id'])]
class Owner extends Model
{
    /** @use HasFactory<OwnerFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Flat, $this> */
    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
    }
}
