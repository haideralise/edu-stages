<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // from P2
use Illuminate\Support\Collection;

class EduClassUser extends Model
{
    protected $table = 'edu_class_user';

    protected $primaryKey = 'id'; // from P2

    public $timestamps = false;

    // from P2
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
    // end from P2

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

    // ── Relationships ────────────────────────────────────────────

    public function eduClass(): BelongsTo // type hint from P2
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'class_id');
    }

    // ── Scopes from P2 ──────────────────────────────────────────

    public function scopeForCoach(Builder $query, int $coachId): Builder // from P2
    {
        return $query->whereJsonContains('teacher', (string)$coachId);
    }

    /**
     * OR LIKE on JSON role columns (from P2).
     */
    public function scopeWhereAnyRoleJsonLike(Builder $query, string $needle): Builder // from P2
    {
        return $query->where(function (Builder $q) use ($needle) {
            $q->where('teacher', 'like', '%' . $needle . '%')
                ->orWhere('student', 'like', '%' . $needle . '%')
                ->orWhere('student_transfer', 'like', '%' . $needle . '%');
        });
    }

    // ── Scopes (P3) ────────────────────────────────────────────

    public function scopeWhereTeacher(Builder $query, int $userId): Builder
    {
        return $query->whereRaw('JSON_CONTAINS(teacher, ?)', [json_encode((string) $userId)]);
    }

    // ── Query helpers (P3) ────────────────────────────────────────

    public static function studentIdsForTeacher(int $teacherId): Collection
    {
        return static::whereTeacher($teacherId)
            ->pluck('student')
            ->flatMap(fn ($s) => $s ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public static function allTeacherIds(): Collection
    {
        return static::pluck('teacher')
            ->flatMap(fn ($t) => $t ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique();
    }
}
