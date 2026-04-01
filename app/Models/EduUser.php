<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EduUser extends Model
{
    protected $table = 'edu_user';

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $guarded = ['*'];

    public function wpUser()
    {
        return $this->belongsTo(WpUser::class, 'user_id', 'ID');
    }
}
