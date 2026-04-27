<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class EduLevel extends Model
{
    use HasFactory;

    protected $table = 'edu_level';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'pid',
        'name',
        'data',
        'file_level',
        'link',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'id' => 'integer',
            'pid' => 'integer',
        ];
    }

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'pid');
    }

    // P3: recursive eager-load for level tree — used by student test results page
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    // P3: build full level tree from root nodes — used by StudentResultController
    public static function getTree(): Collection
    {
        return self::where('pid', 0)->with('descendants')->get();
    }
}
