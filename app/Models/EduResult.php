<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // from P2
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduResult extends Model
{
    use HasFactory; // from P2

    protected $table = 'edu_result';

    protected $primaryKey = 'id'; // from P2

    public $timestamps = false;

    // from P2
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
    // end from P2

    protected function casts(): array
    {
        return [
            'exam_lap_times' => 'array',
            'exam_history' => 'array',
            'class_id' => 'integer',
            'user_id' => 'integer',
            'exam_id' => 'integer',
            'status' => 'integer',
            // from P2
            'id' => 'integer',
            'created' => 'integer',
            'exam_fastest_lap_sec' => 'float',
            'exam_slowest_lap_sec' => 'float',
            'exam_avg_lap_sec' => 'float',
            // end from P2
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }

    public function student(): BelongsTo // from P2 (alias for user — same relationship)
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }

    public function eduClass(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'class_id');
    }

    public function examLevel(): BelongsTo // from P2
    {
        return $this->belongsTo(EduLevel::class, 'exam_id', 'id');
    }
}
