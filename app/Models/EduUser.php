<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EduUser extends Model
{
    use HasFactory;

    protected $table = 'edu_user';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'note',
        'hourly_wage',
        'class_fee',
    ];

    public function wpUser()
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }

    public function isCoach(): bool
    {
        return ! is_null($this->hourly_wage);
    }
}
