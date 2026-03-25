<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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

    // ── Relationships ────────────────────────────────────────────

    public function eduClass()
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'class_id');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeWhereTeacher(Builder $query, int $userId): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query->where('teacher', 'LIKE', '%"'.$userId.'"%');
        }

        return $query->whereRaw('JSON_CONTAINS(teacher, ?)', [json_encode((string) $userId)]);
    }

    // ── Query helpers ────────────────────────────────────────────

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
