<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EduClass extends Model
{
    protected $table = 'wp_3x_edu_class';
    protected $primaryKey = 'class_id';
    public $timestamps = false;

    protected $guarded = ['*']; // readonly — no mass assignment

    protected function casts(): array
    {
        return [
            'date_month'  => 'array',
            'class_date'  => 'array',
            'class_exam'  => 'array',
            'district_id' => 'integer',
            'product_id'  => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function classUsers()
    {
        return $this->hasMany(EduClassUser::class, 'class_id', 'class_id');
    }

    // ── Query Scopes ─────────────────────────────────────────────

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

    public function scopeForYear(Builder $query, ?string $year): Builder
    {
        return $year ? $query->where('class_year', $year) : $query;
    }
}
