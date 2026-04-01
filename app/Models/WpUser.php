<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class WpUser extends Authenticatable
{
    use HasApiTokens;

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

    // ── Relationships ────────────────────────────────────────────

    public function meta()
    {
        return $this->hasMany(WpUserMeta::class, 'user_id', 'ID');
    }

    public function eduProfile()
    {
        return $this->hasOne(EduUser::class, 'user_id', 'ID');
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function getMetaValue(string $key): ?string
    {
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

        $isCoach = EduClassUser::whereRaw(
            "JSON_CONTAINS(teacher, ?)", [json_encode((string) $this->ID)]
        )->exists();

        return $isCoach ? 'coach' : 'student';
    }
}
