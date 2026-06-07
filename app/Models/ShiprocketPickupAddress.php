<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class ShiprocketPickupAddress
 *
 * A vendor (or admin/in-house) pickup/warehouse address. Each record mirrors a
 * pickup location registered in the single shared Shiprocket account via its
 * unique `pickup_nickname`. Vendor isolation is enforced here through `seller_id`.
 *
 * @property int $id
 * @property int|null $seller_id
 * @property string $pickup_nickname
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $address
 * @property string|null $address_2
 * @property string $city
 * @property string $state
 * @property string $country
 * @property string $pin_code
 * @property bool $is_default
 * @property bool $is_synced
 * @property string|null $last_error
 * @property array|null $raw_response
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class ShiprocketPickupAddress extends Model
{
    protected $table = 'shiprocket_pickup_addresses';

    protected $fillable = [
        'seller_id',
        'pickup_nickname',
        'name',
        'email',
        'phone',
        'address',
        'address_2',
        'city',
        'state',
        'country',
        'pin_code',
        'is_default',
        'is_synced',
        'last_error',
        'raw_response',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'is_default' => 'boolean',
        'is_synced' => 'boolean',
        'raw_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The vendor/seller that owns this pickup address (null for admin/in-house).
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * Scope: restrict to a single owner. Pass null for admin/in-house addresses.
     *
     * This is the security boundary — every vendor-facing query MUST go through
     * here with the authenticated seller id so one vendor can never read another's.
     */
    public function scopeForSeller(Builder $query, ?int $sellerId): Builder
    {
        return $sellerId === null
            ? $query->whereNull('seller_id')
            : $query->where('seller_id', $sellerId);
    }

    /**
     * Scope: only the default address.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Make this address the default within its own owner scope, unsetting any
     * sibling default first. Default is per-owner (per seller, and the null/in-house
     * scope is treated as its own group).
     */
    public function makeDefault(): void
    {
        static::query()
            ->forSeller($this->seller_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->forceFill(['is_default' => true])->save();
    }
}
