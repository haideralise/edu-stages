<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduLevel extends Model
{
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
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'pid');
    }
}
