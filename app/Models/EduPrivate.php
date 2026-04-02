<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduPrivate extends Model
{
    protected $table = 'edu_private';

    protected $primaryKey = 'id';

    protected $fillable = [
        'coach_id',
        'enrollment_id',
        'student_name',
        'student_phone',
        'district',
        'pool',
        'other_location',
        'class_date',
        'class_time',
        'class_end_time',
        'ratio',
        'type',
        'fee',
        'status',
        'payment_date',
        'refund_date',
        'attendance',
        'cumulative_override',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'class_date' => 'date',
        ];
    }
}
