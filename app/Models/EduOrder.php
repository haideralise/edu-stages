<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EduOrder extends Model
{
    protected $table = 'edu_order';

    protected $primaryKey = 'id';

    public $timestamps = false;

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
            'amount' => 'float',
            'refund_fee' => 'float',
            'order_date' => 'integer',
            'created' => 'integer',
        ];
    }

    public function scopeValidOnly(Builder $query): Builder
    {
        return $query->where('type', '!=', 'refund');
    }
}
