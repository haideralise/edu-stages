<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EduOrder extends Model
{
    use HasFactory;

    protected $table = 'edu_order';

    protected $primaryKey = 'id';

    public $timestamps = false;

    const SOURCE_MANUAL = 'manual';
    const SOURCE_WOOCOMMERCE = 'woocommerce';
    const STATUS_COMPLETED = 'wc-completed';
    const STATUS_REFUNDED = 'wc-refunded';

    protected $fillable = [
        'class_id',
        'month',
        'class_year',
        'amount',
        'last_days',
        'gateway',
        'avgfee',
        'order_date',
        'created',
        'refund_fee',
        'refund_reason',
        'refund_date',
        'user_id',
        'type',
        'woo_status',
        'woo_class_name',
        'woo_order_id',
        'order_source',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'class_id' => 'integer',
            'amount' => 'float',
            'refund_fee' => 'float',
            'order_date' => 'integer',
            'created' => 'integer',
            'woo_order_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function scopeValidOnly(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q) {
                $q->whereNull('refund_fee')
                    ->orWhere('refund_fee', 0);
            })
            ->where(function (Builder $q) {
                $q->whereNull('refund_date')
                    ->orWhere('refund_date', '')
                    ->orWhere('refund_date', '0');
            })
            ->where('amount', '>', 0);
    }

    public function scopeWhereDateRange(Builder $query, int $from, int $to): Builder
    {
        return $query->where('order_date', '>=', $from)
            ->where('order_date', '<=', $to);
    }
}
