<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function admin(): BelongsTo
    {
        return $this->belongsTo(WpUser::class, 'admin_user_id', 'ID');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(EduResult::class, 'edu_result_id', 'id');
    }
}
