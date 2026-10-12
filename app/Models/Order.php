<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'cashier_id', 'order_type', 'status',
        'payment_method', 'payment_status', 'payment_channel',
        'queue_number', 'pickup_time', 'promo_code',
        'subtotal', 'discount_amount', 'total',
        'midtrans_order_id', 'qr_code_url', 'va_number',
        'payment_expired_at', 'stock_restored_at', 'notes',
        'user_voucher_id', 'voucher_discount', 'points_base',
        'points_earned', 'points_awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'voucher_discount' => 'decimal:2',
            'points_base' => 'integer',
            'points_earned' => 'integer',
            'pickup_time' => 'datetime',
            'payment_expired_at' => 'datetime',
            'stock_restored_at' => 'datetime',
            'points_awarded_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(UserVoucher::class, 'user_voucher_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }
}
