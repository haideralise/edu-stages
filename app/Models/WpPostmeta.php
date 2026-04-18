<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpPostmeta extends Model
{
    protected $table = 'postmeta';

    protected $primaryKey = 'meta_id';

    public $timestamps = false;

    protected $fillable = ['post_id', 'meta_key', 'meta_value'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(WpPost::class, 'post_id', 'ID');
    }
}
