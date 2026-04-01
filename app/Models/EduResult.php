<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduResult extends Model
{
    protected $table = 'edu_result';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'class_id',
        'class_month',
        'exam_id',
        'user_id',
        'first_name',
        'last_name',
        'gender',
        'birthdate',
        'exam_type',
        'exam_name',
        'exam_data',
        'exam_lap_times',
        'exam_fastest_lap_sec',
        'exam_slowest_lap_sec',
        'exam_avg_lap_sec',
        'exam_date',
        'exam_history',
        'exam_note',
        'created',
        'status',
        'class_year',
    ];

    protected function casts(): array
    {
        return [
            'exam_lap_times' => 'array',
            'exam_history' => 'array',
        ];
    }
}
