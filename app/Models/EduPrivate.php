<?php

namespace App\Models;

use App\Enums\EduAttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EduPrivate extends Model
{
    use HasFactory;

    protected $table = 'edu_private';

    protected $primaryKey = 'id';

    const STATUS_PENDING = 'Pending';
    const STATUS_PAID = 'Paid';
    const STATUS_REFUNDED = 'Refunded';

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
            'id' => 'integer',
            'coach_id' => 'integer',
            'enrollment_id' => 'integer',
            'fee' => 'float',
            'cumulative_override' => 'integer',
            'class_date' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'refund_date' => 'date:Y-m-d',
            'attendance' => EduAttendanceStatus::class,
        ];
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeAttended(Builder $query): Builder
    {
        return $query->where('attendance', EduAttendanceStatus::Present);
    }

    public function scopeInMonth(Builder $query, string $yearMonth): Builder
    {
        return $query->whereYear('class_date', substr($yearMonth, 0, 4))
            ->whereMonth('class_date', substr($yearMonth, 5, 2));
    }

    public function scopeForCoach(Builder $query, int $coachId): Builder
    {
        return $query->where('coach_id', $coachId);
    }

    public function getDurationHoursAttribute(): float
    {
        if (empty($this->class_time) || empty($this->class_end_time)) {
            return 1.0;
        }

        $start = strtotime($this->class_time);
        $end = strtotime($this->class_end_time);

        if ($start === false || $end === false || $end <= $start) {
            return 1.0;
        }

        return round(($end - $start) / 3600, 2);
    }

    public function getTuitionAttribute(): float
    {
        return round($this->fee * $this->duration_hours, 2);
    }

    public function coach()
    {
        return $this->belongsTo(WpUser::class, 'coach_id', 'ID');
    }
}
