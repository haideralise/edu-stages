<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WpTerm extends Model
{
    protected $table = 'terms';

    protected $primaryKey = 'term_id';

    public $timestamps = false;
}
