<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class ShiprocketShipment
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $seller_id
 * @property string|null $shiprocket_order_id
 * @property string|null $shiprocket_shipment_id
 * @property string|null $shiprocket_channel_order_id
 * @property string|null $awb_code
 * @property int|null $courier_id
 * @property string|null $courier_name
 * @property string $shipment_status
 * @property int|null $shiprocket_status_code
 * @property string|null $tracking_url
 * @property string|null $estimated_delivery_date
 * @property Carbon|null $pickup_scheduled_date
 * @property Carbon|null $delivered_date
 * @property string|null $label_url
 * @property string|null $manifest_url
 * @property string|null $invoice_url
 * @property float|null $package_weight
 * @property float|null $package_length
 * @property float|null $package_breadth
 * @property float|null $package_height
 * @property string|null $pickup_location
 * @property float $shipping_charge
 * @property float $cod_charge
 * @property string|null $last_error
 * @property int $retry_count
 * @property array|null $raw_response
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class ShiprocketShipment extends Model
{
    protected $table = 'shiprocket_shipments';

    protected $fillable = [
        'order_id',
        'seller_id',
        'shiprocket_order_id',
        'shiprocket_shipment_id',
        'shiprocket_channel_order_id',
        'awb_code',
        'courier_id',
        'courier_name',
        'shipment_status',
        'shiprocket_status_code',
        'tracking_url',
        'estimated_delivery_date',
        'pickup_scheduled_date',
        'delivered_date',
        'label_url',
        'manifest_url',
        'invoice_url',
        'package_weight',
        'package_length',
        'package_breadth',
        'package_height',
        'pickup_location',
        'shipping_charge',
        'cod_charge',
        'last_error',
        'retry_count',
        'raw_response',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'seller_id' => 'integer',
        'courier_id' => 'integer',
        'shiprocket_status_code' => 'integer',
        'package_weight' => 'float',
        'package_length' => 'float',
        'package_breadth' => 'float',
        'package_height' => 'float',
        'shipping_charge' => 'float',
        'cod_charge' => 'float',
        'retry_count' => 'integer',
        'estimated_delivery_date' => 'date',
        'pickup_scheduled_date' => 'datetime',
        'delivered_date' => 'datetime',
        'raw_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Status constants for internal tracking
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ORDER_CREATED = 'order_created';
    const STATUS_AWB_ASSIGNED = 'awb_assigned';
    const STATUS_PICKUP_SCHEDULED = 'pickup_scheduled';
    const STATUS_PICKED_UP = 'picked_up';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_RTO_INITIATED = 'rto_initiated';
    const STATUS_RTO_DELIVERED = 'rto_delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';

    /**
     * Get the order that this shipment belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the seller/vendor that this shipment belongs to.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * Check if the shipment is in an active (non-terminal) state.
     */
    public function isActive(): bool
    {
        return !in_array($this->shipment_status, [
            self::STATUS_DELIVERED,
            self::STATUS_RTO_DELIVERED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Check if the shipment can be cancelled.
     */
    public function isCancellable(): bool
    {
        return in_array($this->shipment_status, [
            self::STATUS_PENDING,
            self::STATUS_ORDER_CREATED,
            self::STATUS_AWB_ASSIGNED,
            self::STATUS_PICKUP_SCHEDULED,
        ]);
    }

    /**
     * Scope: only active (non-delivered, non-cancelled) shipments
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('shipment_status', [
            self::STATUS_DELIVERED,
            self::STATUS_RTO_DELIVERED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Scope: filter by seller
     */
    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }
}
