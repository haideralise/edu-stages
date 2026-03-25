<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduUser extends Model
{
    protected $table = 'wp_3x_edu_user';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $guarded = ['*'];

    public function wpUser()
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }
}
