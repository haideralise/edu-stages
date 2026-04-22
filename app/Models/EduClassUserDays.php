<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduClassUserDays extends Model
{
    protected $table = 'edu_class_user_days';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'class_id',
        'month',
        'user_id',
        'role',
        'days',
        'class_year',
    ];

    public function eduClass(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'class_id');
    }

    public function wpUser(): BelongsTo
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }
}
