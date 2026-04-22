<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpTermTaxonomy extends Model
{
    protected $table = 'term_taxonomy';

    protected $primaryKey = 'term_taxonomy_id';

    public $timestamps = false;

    protected $fillable = [
        'term_id',
        'taxonomy',
        'description',
        'parent',
        'count',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(WpTerm::class, 'term_id', 'term_id');
    }
}
