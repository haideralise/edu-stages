<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduClassUser extends Model
{
    protected $table = 'wp_3x_edu_class_user';
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'student'          => 'array',
            'student_makeup'   => 'array',
            'student_transfer' => 'array',
            'student_order'    => 'array',
            'order_id'         => 'array',
            'teacher'          => 'array',
            'class_exam'       => 'array',
        ];
    }

    public function eduClass()
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'class_id');
    }
}
