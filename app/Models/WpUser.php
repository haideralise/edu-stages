<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory; // from P1
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read string|null $birthdate  billing_birthdate from usermeta
 * @property-read string|null $gender     billing_gender from usermeta
 */
class WpUser extends Authenticatable
{
    use HasApiTokens, HasFactory; // HasFactory from P1

    protected $table = 'users';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'user_login',
        'user_pass',
        'user_email',
        'user_nicename',
        'display_name',
    ];

    protected $hidden = [
        'user_pass',
    ];

    // ── from P1 ─────────────────────────────────────────────────
    public function getAuthIdentifierName(): string
    {
        return 'ID';
    }

    public function getAuthIdentifier()
    {
        return $this->ID;
    }

    public function getAuthPassword(): string
    {
        return $this->user_pass;
    }
    // ── end from P1 ─────────────────────────────────────────────

    // ── Relationships ────────────────────────────────────────────

    public function meta(): HasMany
    {
        return $this->hasMany(WpUserMeta::class, 'user_id', 'ID');
    }

    public function eduProfile(): HasOne
    {
        return $this->hasOne(EduUser::class, 'user_id', 'ID');
    }

    // ── Accessors ─────────────────────────────────────────────────

    protected function birthdate(): Attribute
    {
        return Attribute::get(fn() => $this->getMetaValue('billing_birthdate'));
    }

    protected function gender(): Attribute
    {
        return Attribute::get(fn() => $this->getMetaValue('billing_gender'));
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function getMetaValue(string $key): ?string
    {
        if ($this->relationLoaded('meta')) {
            return $this->meta->firstWhere('meta_key', $key)?->meta_value;
        }

        return $this->meta()->where('meta_key', $key)->value('meta_value');
    }

    /**
     * Resolve role per doc 08 §2.3:
     *  - admin  → wp_capabilities contains 'administrator' or 'mssc'
     *  - coach  → user_id appears in edu_class_user.teacher JSON
     *  - student → fallback
     */
    public function resolveRole(): string
    {
        $caps = $this->getMetaValue('wp_3x_capabilities');
        if ($caps) {
            $parsed = @unserialize($caps);
            if (is_array($parsed) && (isset($parsed['administrator']) || isset($parsed['mssc']))) {
                return 'admin';
            }
        }

        return EduClassUser::forCoach($this->ID)->exists() ? 'coach' : 'student';
    }

    // ── from P1 ─────────────────────────────────────────────────
    public function isAdmin(): bool
    {
        return $this->resolveRole() === 'admin';
    }

    public function isCoach(): bool
    {
        return $this->resolveRole() === 'coach';
    }

    /**
     * Get class IDs where this user appears as a teacher in edu_class_user.
     * Used by ClassMonthFacade to scope Coach's class list.
     *
     * @return array<int>
     */
    public function getCoachClassIds(): array
    {
        return EduClassUser::whereRaw(
            "teacher IS NOT NULL AND teacher != '' AND JSON_CONTAINS(teacher, ?)",
            [json_encode((string) $this->ID)]
        )
            ->distinct()
            ->pluck('class_id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }
    // ── end from P1 ─────────────────────────────────────────────
}
