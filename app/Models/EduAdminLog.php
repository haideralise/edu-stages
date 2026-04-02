<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduAdminLog extends Model
{
    protected $table = 'edu_admin_log';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'created',
        'edu_result_id',
        'handle',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'created' => 'integer',
        ];
    }
}
