<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduBmi extends Model
{
    protected $table = 'edu_bmi';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'height',
        'weight',
        'hc',
        'bmi',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'integer',
        ];
    }
}
