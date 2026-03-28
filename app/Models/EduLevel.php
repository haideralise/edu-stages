<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EduLevel extends Model
{
    protected $table = 'edu_level';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'pid' => 'integer',
            'data' => 'array',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function parent()
    {
        return $this->belongsTo(self::class, 'pid', 'id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'pid', 'id');
    }

    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    // ── Static helpers ───────────────────────────────────────────

    /**
     * Build full level tree from root nodes (pid = 0).
     */
    public static function getTree(): Collection
    {
        return self::where('pid', 0)->with('descendants')->get();
    }
}
