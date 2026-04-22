<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WpPost extends Model
{
    protected $table = 'posts';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    public function postmeta(): HasMany
    {
        return $this->hasMany(WpPostmeta::class, 'post_id', 'ID');
    }
}
