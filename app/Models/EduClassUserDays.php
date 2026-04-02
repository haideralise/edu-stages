<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
