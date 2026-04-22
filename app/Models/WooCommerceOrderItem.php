<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommerceOrderItem extends Model
{
    protected $table = 'woocommerce_order_items';

    protected $primaryKey = 'order_item_id';

    public $incrementing = true;

    public $timestamps = false;

    public function orderPost(): BelongsTo
    {
        return $this->belongsTo(WpPost::class, 'order_id', 'ID');
    }
}
