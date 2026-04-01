<?php

namespace App\Models;

use App\Casts\LegacyEduAttendanceStatusCast;
use App\ValueObjects\EduMonthWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduAttendance extends Model
{
    protected $table = 'edu_attendance';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'month',
        'date',
        'attendance',
        'class_year',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'attendance' => LegacyEduAttendanceStatusCast::class,
    ];

    public function eduClass(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id');
    }

    public function eduUser(): BelongsTo
    {
        return $this->belongsTo(EduUser::class, 'user_id');
    }

    public function scopeWhereMonthWindow(Builder $builder, EduMonthWindow $monthWindow): void
    {
        $builder
            ->where(function (Builder $whereGroup) use ($monthWindow) {
                $whereGroup->where('month', 'like', "%{$monthWindow->start}%");

                if (! $monthWindow->isSingleMonth()) {
                    $whereGroup->orWhere('month', 'like', "%{$monthWindow->end}%");
                }
            });
    }
}
