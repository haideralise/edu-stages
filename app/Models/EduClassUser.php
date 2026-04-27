<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    public function scopeForCoach(Builder $query, int $coachId): Builder
    {
        return $query->whereRaw(
            'CASE WHEN JSON_VALID(teacher) THEN JSON_CONTAINS(teacher, ?) ELSE 0 END',
            [json_encode((string) $coachId)]
        );
    }

    /**
     * OR LIKE on JSON role columns (parity with edu2 findClassUserWhere + user filter).
     */
    public function scopeWhereAnyRoleJsonLike(Builder $query, string $needle): Builder
    {
        return $query->where(function (Builder $q) use ($needle) {
            $q->where('teacher', 'like', '%' . $needle . '%')
                ->orWhere('student', 'like', '%' . $needle . '%')
                ->orWhere('student_transfer', 'like', '%' . $needle . '%');
        });
    }

    // P3: get all student IDs for a coach — used by CoachResultController and CoachHistoryController
    public static function studentIdsForTeacher(int $teacherId): Collection
    {
        return static::forCoach($teacherId)
            ->pluck('student')
            ->flatMap(fn ($s) => $s ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    // P3: get all teacher IDs across all class rows — used by WpUser::resolveRole fallback
    public static function allTeacherIds(): Collection
    {
        return static::pluck('teacher')
            ->flatMap(fn ($t) => $t ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique();
    }
}
