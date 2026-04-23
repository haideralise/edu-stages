<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\BmiForAge;
class EduBmi extends Model
{
    protected $table = 'edu_bmi';

    protected $primaryKey = 'id';

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
            'date' => 'integer',
        ];
    }

    // P3: age-aware BMI category accessor — used by student BMI page and BMI API
    protected function category(): Attribute
    {
        return Attribute::get(function (): string {
            $birthdate = null;
            $gender = null;

            if ($this->relationLoaded('user') && $this->user) {
                $birthdate = $this->user->birthdate;
                $gender = $this->user->gender;
            }

            return BmiForAge::categorize($this->bmi, $birthdate, $gender, $this->date);
        });
    }

    // P3: relationship to WpUser — used by BMI category accessor and API resource
    public function user(): BelongsTo
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }

    // P3: scope to filter by user — used by student BMI controller
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // P3: convert date string or timestamp to unix int — used by BMI store/update
    public static function normalizeDate(mixed $date): int
    {
        return is_numeric($date) ? (int) $date : (int) strtotime($date);
    }

    // P3: calculate BMI from height (cm) and weight (kg) — used by BMI store/update
    public static function calculateBmi(float $height, float $weight): float
    {
        if ($height <= 0) {
            return 0;
        }

        $heightM = $height / 100;

        return round($weight / ($heightM * $heightM), 2);
    }
}
