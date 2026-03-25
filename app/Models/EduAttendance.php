<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduAttendance extends Model
{
    protected $table = 'wp_3x_edu_attendance';
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'user_id'  => 'integer',
        ];
    }

    /**
     * Accessor fallback: 'late' → 'leave', 'absent' → 'cancelled'.
     */
    public function getAttendanceAttribute($value): string
    {
        return match ($value) {
            'late'   => 'leave',
            'absent' => 'cancelled',
            default  => $value ?? '',
        };
    }
}
