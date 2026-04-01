<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduClass extends Model
{
    protected $table = 'edu_class';

    protected $primaryKey = 'class_id';

    public $timestamps = false;

    public $incrementing = false;

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

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeByYear(Builder $query, string $year): Builder
    {
        return $query->where('class_year', $year);
    }

    public function scopeByDistrict(Builder $query, int|array $districtId): Builder
    {
        return is_array($districtId)
            ? $query->whereIn('district_id', $districtId)
            : $query->where('district_id', $districtId);
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function classUsers(): HasMany
    {
        return $this->hasMany(EduClassUser::class, 'class_id', 'class_id');
    }
}
