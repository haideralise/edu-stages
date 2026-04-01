<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduUser extends Model
{
    protected $table = 'edu_user';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'note',
        'hourly_wage',
        'class_fee',
    ];

    public function isCoach(): bool
    {
        return ! is_null($this->hourly_wage);
    }
}
