<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduResult extends Model
{
    protected $table = 'edu_result';
    public $timestamps = false;

    protected $guarded = ['*']; // read-only for students

    protected function casts(): array
    {
        return [
            'exam_lap_times' => 'array',
            'exam_history'   => 'array',
            'class_id'       => 'integer',
            'user_id'        => 'integer',
            'exam_id'        => 'integer',
            'status'         => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }

    public function eduClass()
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'class_id');
    }
}
