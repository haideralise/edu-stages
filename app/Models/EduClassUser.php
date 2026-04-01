<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduClassUser extends Model
{
    protected $table = 'edu_class_user';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'class_id',
        'month',
        'student',
        'student_makeup',
        'student_transfer',
        'student_order',
        'order_id',
        'teacher',
        'days',
        'class_year',
        'class_exam',
        'sort',
        'history_students_status',
    ];

    protected function casts(): array
    {
        return [
            'student' => 'array',
            'student_makeup' => 'array',
            'student_transfer' => 'array',
            'student_order' => 'array',
            'order_id' => 'array',
            'teacher' => 'array',
            'class_exam' => 'array',
        ];
    }

    public function eduClass(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'class_id');
    }
}
