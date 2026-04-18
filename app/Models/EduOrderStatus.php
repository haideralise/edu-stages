<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduOrderStatus extends Model
{
    protected $table = 'edu_order_status';

    protected $primaryKey = 'order_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'status',
    ];
}
