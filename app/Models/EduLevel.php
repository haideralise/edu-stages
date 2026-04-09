<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // from P2
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // from P2
use Illuminate\Database\Eloquent\Relations\HasMany; // from P2
use Illuminate\Support\Collection;

class EduLevel extends Model
{
    use HasFactory; // from P2

    protected $table = 'edu_level';

    protected $primaryKey = 'id'; // from P2

    public $timestamps = false;

    // from P2
    protected $fillable = [
        'pid',
        'name',
        'data',
        'file_level',
        'link',
    ];
    // end from P2

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'id' => 'integer',
            'pid' => 'integer',
        ];
    }

    // ── from P2 ─────────────────────────────────────────────────
    public function getParsedDataAttribute(): array
    {
        if (empty($this->data)) {
            return [];
        }
        return json_decode($this->data, true) ?? [];
    }

    public function scopeRoots($query)
    {
        return $query->where('pid', 0);
    }

    public function scopeChildrenOf($query, int $parentId)
    {
        return $query->where('pid', $parentId);
    }
    // ── end from P2 ─────────────────────────────────────────────

    // ── Relationships ────────────────────────────────────────────

    public function parent(): BelongsTo // type hint from P2
    {
        return $this->belongsTo(self::class, 'pid', 'id');
    }

    public function children(): HasMany // type hint from P2
    {
        return $this->hasMany(self::class, 'pid', 'id');
    }

    // ── P3 additions ──────────────────────────────────────────────

    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Build full level tree from root nodes (pid = 0).
     */
    public static function getTree(): Collection
    {
        return self::where('pid', 0)->with('descendants')->get();
    }
}
