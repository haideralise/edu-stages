<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EduBmi extends Model
{
    protected $table = 'wp_3x_edu_bmi';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'height',
        'weight',
        'hc',
        'bmi',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'height'  => 'float',
            'weight'  => 'float',
            'hc'      => 'float',
            'bmi'     => 'float',
            'date'    => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Calculate BMI from height (cm) and weight (kg).
     */
    public static function calculateBmi(float $height, float $weight): float
    {
        if ($height <= 0) {
            return 0;
        }

        $heightM = $height / 100;

        return round($weight / ($heightM * $heightM), 2);
    }
}
