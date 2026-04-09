<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory; // from P2
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // from P2

class EduClass extends Model
{
    use HasFactory; // from P2

    protected $table = 'edu_class';

    protected $primaryKey = 'class_id';

    public $timestamps = false;

    public $incrementing = false; // from P2

    // from P2
    protected $fillable = [
        'class_id',
        'class_name',
        'district_id',
        'product_id',
        'product_name',
        'date_time',
        'date_month',
        'class_date',
        'class_exam',
        'lv3',
        'class_year',
    ];
    // end from P2

    protected function casts(): array
    {
        return [
            'date_month' => 'array',
            'class_date' => 'array',
            'class_exam' => 'array',
            'district_id' => 'integer',
            'product_id' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function classUsers(): HasMany // type hint from P2
    {
        return $this->hasMany(EduClassUser::class, 'class_id', 'class_id');
    }

    // ── Scopes from P2 ──────────────────────────────────────────

    public function scopeByYear(Builder $query, string $year): Builder // from P2
    {
        return $query->where('class_year', $year);
    }

    public function scopeByDistrict(Builder $query, int|array $districtId): Builder // from P2
    {
        return is_array($districtId)
            ? $query->whereIn('district_id', $districtId)
            : $query->where('district_id', $districtId);
    }

    // ── Scopes (P3 — null-safe variants) ────────────────────────

    public function scopeForYear(Builder $query, ?string $year): Builder
    {
        return $year ? $query->where('class_year', $year) : $query;
    }

    public function scopeForDistrict(Builder $query, null|int|array $districtId): Builder
    {
        if (is_null($districtId)) {
            return $query;
        }

        if (is_array($districtId)) {
            return $query->whereIn('district_id', $districtId);
        }

        return $query->where('district_id', $districtId);
    }
}
